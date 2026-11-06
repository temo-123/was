<?php

namespace App\Services\Pricing;

use App\Models\ClientTier;
use App\Models\RentalItem;
use App\Models\TicketType;
use App\Models\Visitor;
use App\Models\VisitorSubscription;
use App\Models\VisitReward;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * What a visit costs, on top of the price list (PriceCalculator):
 *
 *   entry   = price list price − client status discount (VIP −50 %…) − visit rewards (free / −X % every N-th visit)
 *             (0 when a subscription covers it; a manual price replaces it)
 *   rentals = rental gear × quantity − a free piece from a reward − the status' gear discount
 *   total   = entry + rentals
 *
 * Used for the price shown on the entrance screen and, with the same numbers, when the visit is saved.
 */
class Checkout
{
    /**
     * @param  array<int, array{rental_item_id: int, quantity?: int, size?: ?string}>  $rentals
     */
    public function calculate(
        ?Visitor $visitor,
        PriceQuote $quote,
        TicketType $type,
        bool $coveredBySubscription,
        array $rentals = [],
        bool $applyRewards = true,
        ?float $override = null,
        ?CarbonInterface $at = null,
    ): CheckoutResult {
        $at ??= now();
        $visitNumber = $visitor?->exists ? $visitor->visits()->count() + 1 : 1;
        $tier = $visitor?->activeTier($at);

        $matching = VisitReward::where('is_active', true)->with('rentalItem')->ordered()->get()
            ->filter(fn (VisitReward $r) => $r->appliesTo($visitNumber))->values();
        $rewards = $applyRewards ? $matching : new Collection;

        // --- Entry ---------------------------------------------------------------------------------------------
        $base = $coveredBySubscription ? 0.0 : ($quote->available ? $quote->price : null);
        $tierPercent = $tier && ! $coveredBySubscription ? min(100.0, (float) $tier->discount_percent) : 0.0;
        $entry = $base;
        $tierDiscount = 0.0;
        $rewardDiscount = 0.0;
        $usedRewards = new Collection;

        if ($base !== null && $base > 0) {
            $tierDiscount = round($base * $tierPercent / 100, 2);
            $entry = $base - $tierDiscount;

            // Entry rewards only make a single visit cheaper, not a subscription that is being bought.
            if (! $type->isSubscription()) {
                foreach ($rewards->whereIn('type', ['free_entry', 'discount']) as $reward) {
                    if ($entry <= 0) {
                        break;
                    }
                    $cut = $reward->type === 'free_entry' ? $entry : round($entry * min(100, (float) $reward->value) / 100, 2);
                    $rewardDiscount += $cut;
                    $entry -= $cut;
                    $usedRewards->push($reward);
                }
            }
            $entry = round(max(0, $entry), 2);
        }
        if ($override !== null) {
            $entry = round($override, 2);
        }

        // --- Rental gear ---------------------------------------------------------------------------------------
        $items = RentalItem::where('is_active', true)->whereIn('id', array_column($rentals, 'rental_item_id'))->get()->keyBy('id');
        $freeItems = $rewards->where('type', 'free_rental')->filter(fn (VisitReward $r) => $r->rental_item_id)->keyBy('rental_item_id');
        $gearPercent = $tier ? min(100.0, (float) $tier->rental_discount_percent) : 0.0;
        $lines = [];

        foreach ($rentals as $line) {
            $item = $items->get((int) ($line['rental_item_id'] ?? 0));
            $quantity = max(1, min(20, (int) ($line['quantity'] ?? 1)));
            if (! $item) {
                continue;
            }
            $size = $item->has_sizes && filled($line['size'] ?? null) ? mb_substr(trim((string) $line['size']), 0, 16) : null;

            // One piece free from a reward, as its own line.
            if ($reward = $freeItems->pull($item->id)) {
                $lines[] = $this->line($item, 1, $size, 0, (float) $item->price, true);
                $usedRewards->push($reward);
                if (--$quantity === 0) {
                    continue;
                }
            }

            $gross = (float) $item->price * $quantity;
            $lines[] = $this->line($item, $quantity, $size, $gearPercent, $gross, false);
        }
        $rentalTotal = round(array_sum(array_column($lines, 'price')), 2);

        return new CheckoutResult(
            visitNumber: $visitNumber,
            tier: $tier,
            matchingRewards: $matching,
            usedRewards: $usedRewards->unique('id')->values(),
            coveredBySubscription: $coveredBySubscription,
            entryBase: $base,
            tierPercent: $tierPercent,
            tierDiscount: $tierDiscount,
            rewardDiscount: round($rewardDiscount, 2),
            entry: $entry,
            overridden: $override !== null,
            rentals: $lines,
            rentalTotal: $rentalTotal,
            total: $entry === null ? null : round($entry + $rentalTotal, 2),
            nextReward: $this->nextReward($visitNumber),
        );
    }

    /** The closest reward after this visit: [reward, visits until it]. */
    public function nextReward(int $visitNumber): ?array
    {
        $next = VisitReward::where('is_active', true)->where('every_visits', '>', 0)->with('rentalItem')->get()
            ->map(fn (VisitReward $r) => [$r, $r->every_visits - ($visitNumber % $r->every_visits)])
            ->sortBy(fn ($pair) => $pair[1])
            ->first();

        return $next ? ['reward' => $next[0], 'in' => $next[1]] : null;
    }

    /** One rental line added to an existing visit (Rental gear page): the visit's client status gear discount applies. */
    public function rentalLine(RentalItem $item, int $quantity, ?string $size, ?ClientTier $tier): array
    {
        $size = $item->has_sizes && filled($size) ? mb_substr(trim($size), 0, 16) : null;

        return $this->line($item, $quantity, $size, $tier ? min(100.0, (float) $tier->rental_discount_percent) : 0.0, (float) $item->price * $quantity, false);
    }

    private function line(RentalItem $item, int $quantity, ?string $size, float $percent, float $gross, bool $reward): array
    {
        $discount = $reward ? $gross : round($gross * $percent / 100, 2);

        return [
            'rental_item_id' => $item->id,
            'name' => $item->name,
            'name_ka' => $item->name_ka,
            'quantity' => $quantity,
            'size' => $size,
            'unit_price' => (float) $item->price,
            'discount_amount' => $discount,
            'price' => round($gross - $discount, 2),
            'is_reward' => $reward,
            'is_returnable' => $item->is_returnable,
        ];
    }
}
