<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\TicketType;
use App\Models\TimeSlot;
use App\Models\User;
use App\Models\Visitor;
use App\Models\VisitorCategory;
use App\Models\VisitorSubscription;
use App\Models\VisitorVisit;
use App\Services\Pricing\PriceCalculator;
use App\Services\Pricing\PriceQuote;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Registers a visitor entrance: finds/creates the visitor, prices the entry, stores the visit. */
class EntryService
{
    public function __construct(
        protected PriceCalculator $calculator,
        protected VisitorLinker $linker,
    ) {}

    /** An active subscription of the visitor that is valid at this time slot. */
    public function usableSubscription(Visitor $visitor, ?TimeSlot $slot, ?CarbonInterface $at = null): ?VisitorSubscription
    {
        if (! $slot || ! $visitor->exists) {
            return null;
        }

        return $visitor->subscriptions()->active($at)->with(['timeSlot', 'ticketType'])->orderBy('ends_on')->get()
            ->first(fn (VisitorSubscription $s) => $s->coversTimeSlot($slot));
    }

    /**
     * @return array{quote: PriceQuote, subscription: ?VisitorSubscription, amount: float}
     */
    public function preview(?Visitor $visitor, int $age, ?int $categoryId, TicketType $type, ?CarbonInterface $at = null): array
    {
        $at ??= now();
        $quote = $this->calculator->quote($age, $categoryId, $type, $at);

        $subscription = ($visitor && ! $type->isSubscription())
            ? $this->usableSubscription($visitor, $quote->timeSlot ?? TimeSlot::forTime($at), $at)
            : null;

        return [
            'quote' => $quote,
            'subscription' => $subscription,
            'amount' => $subscription ? 0.0 : $quote->price,
        ];
    }

    public function createVisitor(array $data, ?User $by = null): Visitor
    {
        $visitor = Visitor::create([
            ...$data,
            'visitor_category_id' => $data['visitor_category_id'] ?? VisitorCategory::default()?->id,
            'terms_accepted' => (bool) ($data['terms_accepted'] ?? false),
            'terms_accepted_at' => ! empty($data['terms_accepted']) ? now() : null,
            'created_by' => $by?->id,
        ]);

        ActivityLog::record('visitor.created', $visitor);
        $this->linker->linkVisitorToMatchingUser($visitor);

        return $visitor;
    }

    /**
     * @param  array{visitor_id?: int, visitor?: array, ticket_type_id: int, visitor_category_id?: ?int,
     *               payment_method?: string, use_subscription?: bool, price_override?: ?float, comment?: ?string}  $data
     */
    public function register(array $data, User $by): VisitorVisit
    {
        return DB::transaction(function () use ($data, $by) {
            $at = now();

            if (! empty($data['visitor_id'])) {
                $visitor = Visitor::lockForUpdate()->findOrFail($data['visitor_id']);
            } else {
                $visitor = $this->createVisitor($data['visitor'], $by);
            }

            if (! $visitor->terms_accepted) {
                if (empty($data['terms_accepted'])) {
                    throw ValidationException::withMessages(['terms_accepted' => __('The visitor must accept the gym terms before entering.')]);
                }
                $visitor->update(['terms_accepted' => true, 'terms_accepted_at' => $at]);
            }

            // Protects against double clicks / double submits.
            if ($visitor->visits()->where('entered_at', '>=', $at->copy()->subMinute())->exists()) {
                throw ValidationException::withMessages(['visitor_id' => __('This visitor was registered less than a minute ago.')]);
            }

            if (! empty($data['visitor_category_id']) && (int) $data['visitor_category_id'] !== (int) $visitor->visitor_category_id) {
                $visitor->update(['visitor_category_id' => $data['visitor_category_id']]);
            }

            $type = TicketType::where('is_active', true)->findOrFail($data['ticket_type_id']);
            $age = $visitor->ageAt($at);
            ['quote' => $quote, 'subscription' => $subscription] = $this->preview($visitor, $age, $visitor->visitor_category_id, $type, $at);

            $visit = new VisitorVisit([
                'visitor_id' => $visitor->id,
                'entered_at' => $at,
                'age_at_visit' => $age,
                'age_group_id' => $quote->ageGroup?->id,
                'time_slot_id' => $quote->timeSlot?->id,
                'ticket_type_id' => $type->id,
                'visitor_category_id' => $visitor->visitor_category_id,
                'comment' => $data['comment'] ?? null,
                'created_by' => $by->id,
            ]);

            // 1) Entry covered by an existing subscription.
            if ($subscription && ($data['use_subscription'] ?? true)) {
                $subscription->increment('visits_used');
                $visit->fill([
                    'ticket_type_id' => $subscription->ticket_type_id,
                    'visitor_subscription_id' => $subscription->id,
                    'payment_method' => 'subscription',
                ])->save();

                ActivityLog::record('visit.created', $visit, ['subscription_id' => $subscription->id]);

                return $visit;
            }

            // 2) Paid entry or new subscription.
            $override = $data['price_override'] ?? null;
            $override = ($override === null || $override === '') ? null : round((float) $override, 2);

            if ($override !== null && ! $by->hasPermission('visits.override_price')) {
                throw ValidationException::withMessages(['price_override' => __('You are not allowed to change the price.')]);
            }
            if (! $quote->available && $override === null) {
                throw ValidationException::withMessages(['ticket_type_id' => $quote->message]);
            }
            $price = $override ?? $quote->price;
            $paymentMethod = $price > 0 ? ($data['payment_method'] ?? 'cash') : 'free';

            if ($type->isSubscription()) {
                $slot = $quote->timeSlot ?? TimeSlot::ordered()->get()->last();
                $subscription = VisitorSubscription::create([
                    'visitor_id' => $visitor->id,
                    'ticket_type_id' => $type->id,
                    'time_slot_id' => $slot->id,
                    'age_group_id' => $quote->ageGroup?->id,
                    'visitor_category_id' => $visitor->visitor_category_id,
                    'starts_on' => $at->toDateString(),
                    'ends_on' => $at->copy()->addDays($type->duration_days - 1)->toDateString(),
                    'visits_limit' => $type->visits_limit,
                    'visits_used' => 1,
                    'base_price' => $quote->basePrice,
                    'coefficient' => $quote->coefficient,
                    'price' => $price,
                    'payment_method' => $paymentMethod,
                    'created_by' => $by->id,
                ]);
                ActivityLog::record('subscription.created', $subscription, ['price' => $price]);

                // The money is recorded on the subscription; the visit itself costs 0.
                $visit->fill([
                    'visitor_subscription_id' => $subscription->id,
                    'base_price' => $quote->basePrice,
                    'coefficient' => $quote->coefficient,
                    'price' => 0,
                    'price_overridden' => $override !== null,
                    'payment_method' => 'subscription',
                ])->save();
            } else {
                $visit->fill([
                    'base_price' => $quote->basePrice,
                    'coefficient' => $quote->coefficient,
                    'price' => $price,
                    'price_overridden' => $override !== null,
                    'payment_method' => $paymentMethod,
                ])->save();
            }

            ActivityLog::record('visit.created', $visit, ['price' => $price]);

            return $visit;
        });
    }
}
