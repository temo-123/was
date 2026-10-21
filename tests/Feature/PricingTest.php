<?php

namespace Tests\Feature;

use App\Models\AgeGroup;
use App\Models\PriceCoefficient;
use App\Models\TicketType;
use App\Models\VisitorCategory;
use App\Services\Pricing\PriceCalculator;
use Database\Seeders\PricingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PricingSeeder::class);
    }

    public static function priceTable(): array
    {
        // [age, category code, ticket code, time, expected price | null = not available]
        return [
            'adult regular morning' => [30, 'regular', 'single', '09:00', 10],
            'adult regular day' => [30, 'regular', 'single', '13:00', 15],
            'adult regular evening' => [30, 'regular', 'single', '19:00', 20],
            'adult member evening' => [30, 'member', 'single', '19:00', 10],
            'adult regular evening monthly' => [30, 'regular', 'monthly', '19:00', 150],
            'adult member monthly' => [30, 'member', 'monthly', '19:00', 50],
            '16 is adult' => [16, 'regular', 'monthly', '12:00', 120],
            'teen day' => [12, 'regular', 'single', '12:30', 15],
            'teen evening monthly' => [15, 'member', 'monthly', '22:59', 120],
            'kid morning' => [6, 'regular', 'single', '08:00', 15],
            'kid day monthly' => [9, 'regular', 'monthly', '17:59', 80],
            '10 is teen' => [10, 'regular', 'single', '08:30', 10],
            'kid evening not sold' => [6, 'regular', 'single', '18:00', null],
            'under 4 not sold' => [3, 'regular', 'single', '10:00', null],
            'closed at night' => [30, 'regular', 'single', '23:30', null],
        ];
    }

    #[DataProvider('priceTable')]
    public function test_price_matrix(int $age, string $category, string $ticket, string $time, ?int $expected): void
    {
        $quote = app(PriceCalculator::class)->quote(
            $age,
            VisitorCategory::where('code', $category)->value('id'),
            TicketType::where('code', $ticket)->firstOrFail(),
            Carbon::parse("2026-10-20 {$time}"),
        );

        if ($expected === null) {
            $this->assertFalse($quote->available, 'Expected no tariff');
        } else {
            $this->assertTrue($quote->available, (string) $quote->message);
            $this->assertEquals($expected, $quote->price);
        }
    }

    public function test_coefficients_are_multiplied(): void
    {
        PriceCoefficient::where('name', 'Global')->update(['coefficient' => 1.1]);
        PriceCoefficient::create([
            'name' => 'Weekend adults',
            'coefficient' => 2,
            'age_group_id' => AgeGroup::where('code', 'adults')->value('id'),
            'weekdays' => [6, 7],
            'is_active' => true,
        ]);

        $single = TicketType::where('code', 'single')->first();
        $regular = VisitorCategory::where('code', 'regular')->value('id');
        $calc = app(PriceCalculator::class);

        // Tuesday: only the global +10%.
        $this->assertEquals(22, $calc->quote(30, $regular, $single, Carbon::parse('2026-10-20 19:00'))->price);
        // Saturday: 20 × 1.1 × 2.
        $this->assertEquals(44, $calc->quote(30, $regular, $single, Carbon::parse('2026-10-24 19:00'))->price);
        // Kids are not affected by the adults coefficient.
        $this->assertEquals(11, $calc->quote(6, $regular, $single, Carbon::parse('2026-10-24 13:00'))->price);
    }
}
