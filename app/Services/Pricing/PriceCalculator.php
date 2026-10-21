<?php

namespace App\Services\Pricing;

use App\Models\AgeGroup;
use App\Models\PriceCoefficient;
use App\Models\PriceRule;
use App\Models\TicketType;
use App\Models\TimeSlot;
use Carbon\CarbonInterface;

/**
 * price = price_rules matrix value (age group × time slot × ticket type × category)
 *         × every active price_coefficient whose conditions match.
 */
class PriceCalculator
{
    public function quote(int $age, ?int $categoryId, TicketType $ticketType, ?CarbonInterface $at = null, ?TimeSlot $timeSlot = null): PriceQuote
    {
        $at ??= now();

        $ageGroup = AgeGroup::forAge($age);
        if (! $ageGroup) {
            return PriceQuote::unavailable($age, $ticketType, $categoryId, __('There is no tariff for age :age.', ['age' => $age]));
        }

        $timeSlot ??= TimeSlot::forTime($at);
        if (! $timeSlot) {
            $open = TimeSlot::where('is_active', true)->min('start_time');
            $close = TimeSlot::where('is_active', true)->max('end_time');

            return PriceQuote::unavailable($age, $ticketType, $categoryId, __('The gym is closed at :time. Working hours: :open–:close.', [
                'time' => $at->format('H:i'),
                'open' => substr((string) $open, 0, 5),
                'close' => substr((string) $close, 0, 5),
            ]), $ageGroup);
        }

        // A rule for the visitor's category wins over a generic (category = null) rule.
        $rule = PriceRule::where('is_active', true)
            ->where('age_group_id', $ageGroup->id)
            ->where('time_slot_id', $timeSlot->id)
            ->where('ticket_type_id', $ticketType->id)
            ->where(fn ($q) => $q->whereNull('visitor_category_id')->when($categoryId, fn ($q) => $q->orWhere('visitor_category_id', $categoryId)))
            ->orderByRaw('visitor_category_id is null')
            ->first();

        if (! $rule) {
            return PriceQuote::unavailable($age, $ticketType, $categoryId, __(':ticket is not available for age group :group during :slot.', [
                'ticket' => $ticketType->name,
                'group' => $ageGroup->label(),
                'slot' => $timeSlot->label(),
            ]), $ageGroup, $timeSlot);
        }

        $coefficients = PriceCoefficient::where('is_active', true)->get()
            ->filter(fn (PriceCoefficient $c) => $c->appliesTo($ageGroup->id, $timeSlot->id, $ticketType->id, $categoryId, $at))
            ->values();

        $coefficient = $coefficients->reduce(fn (float $carry, PriceCoefficient $c) => $carry * (float) $c->coefficient, 1.0);
        $basePrice = (float) $rule->price;

        return new PriceQuote(
            available: true,
            age: $age,
            ticketType: $ticketType,
            categoryId: $categoryId,
            ageGroup: $ageGroup,
            timeSlot: $timeSlot,
            basePrice: $basePrice,
            coefficient: $coefficient,
            price: round($basePrice * $coefficient, 2),
            coefficients: $coefficients,
        );
    }
}
