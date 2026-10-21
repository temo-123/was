<?php

namespace Database\Seeders;

use App\Models\TicketType;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorSubscription;
use App\Models\VisitorVisit;
use App\Services\Pricing\PriceCalculator;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * ~500 example visits over the last 60 days, priced by the real PriceCalculator.
 * Some visitors get a monthly subscription; their entries during it are free.
 */
class VisitSeeder extends Seeder
{
    protected PriceCalculator $calculator;

    protected array $staffIds = [];

    /** visitorId => [date => true] — one entry per visitor per day. */
    protected array $visitedOn = [];

    public function run(PriceCalculator $calculator, int $count = 500, int $days = 60): void
    {
        $this->calculator = $calculator;
        $this->staffIds = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['worker', 'admin']))->pluck('id')->all() ?: [null];

        $visitors = Visitor::all();
        if ($visitors->isEmpty()) {
            $this->command?->warn('No visitors — run VisitorSeeder first.');

            return;
        }

        $single = TicketType::where('code', 'single')->firstOrFail();
        $monthly = TicketType::where('code', 'monthly')->firstOrFail();

        // Regulars come much more often than occasional visitors.
        $weights = $visitors->mapWithKeys(fn (Visitor $v) => [$v->id => fake()->randomElement([1, 1, 2, 3, 6, 10])]);
        $pool = $weights->flatMap(fn (int $w, int $id) => array_fill(0, $w, $id))->values()->all();

        // 1) Subscriptions for ~1/4 of the visitors (each purchase is also a visit).
        $created = 0;
        foreach ($visitors->random(max(1, intdiv($visitors->count(), 4))) as $visitor) {
            $at = $this->randomMoment($days, $visitor);
            $quote = $this->quote($visitor, $monthly, $at);
            if (! $quote?->available) {
                continue;
            }

            $subscription = VisitorSubscription::create([
                'visitor_id' => $visitor->id,
                'ticket_type_id' => $monthly->id,
                'time_slot_id' => $quote->timeSlot->id,
                'age_group_id' => $quote->ageGroup->id,
                'visitor_category_id' => $visitor->visitor_category_id,
                'starts_on' => $at->toDateString(),
                'ends_on' => $at->copy()->addDays($monthly->duration_days - 1)->toDateString(),
                'visits_limit' => $monthly->visits_limit,
                'visits_used' => 1,
                'base_price' => $quote->basePrice,
                'coefficient' => $quote->coefficient,
                'price' => $quote->price,
                'payment_method' => fake()->randomElement(['cash', 'card', 'card', 'transfer']),
                'created_by' => fake()->randomElement($this->staffIds),
                'created_at' => $at,
                'updated_at' => $at,
            ]);

            $this->storeVisit($visitor, $at, $quote, $subscription->ticket_type_id, $subscription->id);
            $created++;
        }

        // 2) Regular entries.
        $attempts = 0;
        while ($created < $count && $attempts++ < $count * 10) {
            $visitor = $visitors->find(fake()->randomElement($pool));
            $at = $this->randomMoment($days, $visitor);
            if (isset($this->visitedOn[$visitor->id][$at->toDateString()])) {
                continue;
            }

            $quote = $this->quote($visitor, $single, $at);
            if (! $quote?->available) {
                continue; // e.g. kids in the evening, under 4
            }

            $subscription = $visitor->subscriptions()->active($at)->with('timeSlot')->get()
                ->first(fn (VisitorSubscription $s) => $s->starts_on->lte($at) && $s->coversTimeSlot($quote->timeSlot));

            if ($subscription) {
                $subscription->increment('visits_used');
                $this->storeVisit($visitor, $at, $quote, $subscription->ticket_type_id, $subscription->id);
            } else {
                $this->storeVisit($visitor, $at, $quote, $single->id);
            }
            $created++;
        }

        $this->command?->info("{$created} visits created.");
    }

    protected function quote(Visitor $visitor, TicketType $type, CarbonInterface $at)
    {
        return $this->calculator->quote($visitor->ageAt($at), $visitor->visitor_category_id, $type, $at);
    }

    /** A random moment during opening hours in the last $days days (never in the future). */
    protected function randomMoment(int $days, Visitor $visitor): Carbon
    {
        $slots = TimeSlot::where('is_active', true)->ordered()->get();
        $kid = $visitor->ageAt() < 10;

        for ($try = 0; $try < 20; $try++) {
            // Evenings are the busiest time; kids come in the morning or the afternoon.
            $slot = $kid
                ? $slots->whereIn('code', ['morning', 'day'])->random()
                : fake()->randomElement([$slots[0], $slots[1], $slots[1], $slots[2], $slots[2], $slots[2]]);

            [$sh, $sm] = array_map('intval', explode(':', $slot->start_time));
            [$eh, $em] = array_map('intval', explode(':', $slot->end_time));
            $minutes = fake()->numberBetween($sh * 60 + $sm, $eh * 60 + $em - 15);

            $at = today()->subDays(fake()->numberBetween(0, $days))->addMinutes($minutes);
            if ($at->lte(now()) && $at->gte($visitor->created_at->copy()->startOfDay())) {
                return $at;
            }
        }

        return now()->subDay()->setTime(13, 0);
    }

    protected function storeVisit(Visitor $visitor, Carbon $at, $quote, int $ticketTypeId, ?int $subscriptionId = null): void
    {
        $this->visitedOn[$visitor->id][$at->toDateString()] = true;

        VisitorVisit::create([
            'visitor_id' => $visitor->id,
            'entered_at' => $at,
            'age_at_visit' => $visitor->ageAt($at),
            'age_group_id' => $quote->ageGroup->id,
            'time_slot_id' => $quote->timeSlot->id,
            'ticket_type_id' => $ticketTypeId,
            'visitor_category_id' => $visitor->visitor_category_id,
            'visitor_subscription_id' => $subscriptionId,
            'base_price' => $quote->basePrice,
            'coefficient' => $quote->coefficient,
            'price' => $subscriptionId ? 0 : $quote->price,
            'payment_method' => $subscriptionId ? 'subscription' : fake()->randomElement(['cash', 'cash', 'cash', 'card', 'card', 'transfer']),
            'created_by' => fake()->randomElement($this->staffIds),
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }
}
