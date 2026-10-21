<?php

namespace App\Services\Pricing;

use App\Models\AgeGroup;
use App\Models\PriceCoefficient;
use App\Models\TicketType;
use App\Models\TimeSlot;
use Illuminate\Support\Collection;

/** Result of a price calculation. `available = false` means no tariff applies (see `message`). */
final class PriceQuote
{
    /**
     * @param  Collection<int, PriceCoefficient>  $coefficients
     */
    public function __construct(
        public readonly bool $available,
        public readonly int $age,
        public readonly TicketType $ticketType,
        public readonly ?int $categoryId = null,
        public readonly ?AgeGroup $ageGroup = null,
        public readonly ?TimeSlot $timeSlot = null,
        public readonly float $basePrice = 0,
        public readonly float $coefficient = 1,
        public readonly float $price = 0,
        public readonly Collection $coefficients = new Collection,
        public readonly ?string $message = null,
    ) {}

    public static function unavailable(int $age, TicketType $ticketType, ?int $categoryId, string $message, ?AgeGroup $ageGroup = null, ?TimeSlot $timeSlot = null): self
    {
        return new self(false, $age, $ticketType, $categoryId, $ageGroup, $timeSlot, message: $message);
    }

    public function toArray(): array
    {
        return [
            'available' => $this->available,
            'message' => $this->message,
            'age' => $this->age,
            'category_id' => $this->categoryId,
            'age_group' => $this->ageGroup ? $this->ageGroup->only(['id', 'code', 'name', 'name_ka']) + ['label' => $this->ageGroup->label()] : null,
            'time_slot' => $this->timeSlot ? $this->timeSlot->only(['id', 'code', 'name', 'name_ka']) + ['label' => $this->timeSlot->label()] : null,
            'ticket_type' => $this->ticketType->only(['id', 'code', 'name', 'name_ka', 'duration_days', 'visits_limit']),
            'base_price' => round($this->basePrice, 2),
            'coefficient' => round($this->coefficient, 3),
            'price' => round($this->price, 2),
            'coefficients' => $this->coefficients->map(fn (PriceCoefficient $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'coefficient' => (float) $c->coefficient,
            ])->values(),
        ];
    }
}
