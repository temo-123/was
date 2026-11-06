<?php

namespace App\Services\Pricing;

use App\Models\ClientTier;
use App\Models\VisitReward;
use Illuminate\Support\Collection;

/** What a visit costs (Checkout::calculate). `entry` / `total` are null when no price applies and none was typed. */
final class CheckoutResult
{
    /**
     * @param  Collection<int, VisitReward>  $matchingRewards  rewards this visit number gets
     * @param  Collection<int, VisitReward>  $usedRewards  …the ones that actually made something cheaper
     * @param  array<int, array>  $rentals  lines, see Checkout::line()
     */
    public function __construct(
        public readonly int $visitNumber,
        public readonly ?ClientTier $tier,
        public readonly Collection $matchingRewards,
        public readonly Collection $usedRewards,
        public readonly bool $coveredBySubscription,
        public readonly ?float $entryBase,
        public readonly float $tierPercent,
        public readonly float $tierDiscount,
        public readonly float $rewardDiscount,
        public readonly ?float $entry,
        public readonly bool $overridden,
        public readonly array $rentals,
        public readonly float $rentalTotal,
        public readonly ?float $total,
        public readonly ?array $nextReward,
    ) {}

    /** Snapshot of the used rewards, stored on the visit. */
    public function rewardsSnapshot(): ?array
    {
        return $this->usedRewards->isEmpty() ? null : $this->usedRewards->map(fn (VisitReward $r) => [
            'id' => $r->id,
            'name' => $r->name,
            'name_ka' => $r->name_ka,
            'type' => $r->type,
            'value' => $r->value !== null ? (float) $r->value : null,
        ])->all();
    }

    public function toArray(): array
    {
        $reward = fn (VisitReward $r) => ['id' => $r->id, 'name' => $r->local_name, 'type' => $r->type, 'rental_item_id' => $r->rental_item_id, 'text' => $r->describe()];

        return [
            'visit_number' => $this->visitNumber,
            'tier' => $this->tier ? [
                'id' => $this->tier->id,
                'name' => $this->tier->local_name,
                'color' => $this->tier->color,
                'discount_percent' => (float) $this->tier->discount_percent,
                'rental_discount_percent' => (float) $this->tier->rental_discount_percent,
            ] : null,
            'rewards' => $this->matchingRewards->map($reward)->values(),
            'used_reward_ids' => $this->usedRewards->pluck('id')->values(),
            'covered_by_subscription' => $this->coveredBySubscription,
            'entry_base' => $this->entryBase,
            'tier_percent' => $this->tierPercent,
            'tier_discount' => $this->tierDiscount,
            'reward_discount' => $this->rewardDiscount,
            'entry' => $this->entry,
            'overridden' => $this->overridden,
            'rentals' => $this->rentals,
            'rental_total' => $this->rentalTotal,
            'total' => $this->total,
            'next_reward' => $this->nextReward ? ['in' => $this->nextReward['in']] + $reward($this->nextReward['reward']) : null,
        ];
    }
}
