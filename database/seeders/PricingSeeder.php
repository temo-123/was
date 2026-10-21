<?php

namespace Database\Seeders;

use App\Models\AgeGroup;
use App\Models\PriceCoefficient;
use App\Models\PriceRule;
use App\Models\TicketType;
use App\Models\TimeSlot;
use App\Models\VisitorCategory;
use Illuminate\Database\Seeder;

/** Initial price list (from the hand-written price table). Safe to run more than once. */
class PricingSeeder extends Seeder
{
    public function run(): void
    {
        $kids = AgeGroup::updateOrCreate(['code' => 'kids'], ['name' => 'Kids 4–10', 'name_ka' => 'ბავშვები 4-10', 'min_age' => 4, 'max_age' => 10, 'sort_order' => 3]);
        $teens = AgeGroup::updateOrCreate(['code' => 'teens'], ['name' => 'Teens 10–16', 'name_ka' => 'მოზარდები 10-16', 'min_age' => 10, 'max_age' => 16, 'sort_order' => 2]);
        $adults = AgeGroup::updateOrCreate(['code' => 'adults'], ['name' => 'Adults 16+', 'name_ka' => 'ზრდასრულები 16+', 'min_age' => 16, 'max_age' => null, 'sort_order' => 1]);

        $morning = TimeSlot::updateOrCreate(['code' => 'morning'], ['name' => 'Morning', 'name_ka' => 'დილა', 'start_time' => '08:00:00', 'end_time' => '12:00:00', 'sort_order' => 1]);
        $day = TimeSlot::updateOrCreate(['code' => 'day'], ['name' => 'Day', 'name_ka' => 'დღე', 'start_time' => '12:00:00', 'end_time' => '18:00:00', 'sort_order' => 2]);
        $evening = TimeSlot::updateOrCreate(['code' => 'evening'], ['name' => 'Evening', 'name_ka' => 'საღამო', 'start_time' => '18:00:00', 'end_time' => '23:00:00', 'sort_order' => 3]);

        $single = TicketType::updateOrCreate(['code' => 'single'], ['name' => 'Single visit', 'name_ka' => 'ერთჯერადი', 'duration_days' => null, 'sort_order' => 1]);
        $monthly = TicketType::updateOrCreate(['code' => 'monthly'], ['name' => 'Monthly subscription', 'name_ka' => 'აბონემენტი', 'duration_days' => 30, 'sort_order' => 2]);

        $regular = VisitorCategory::updateOrCreate(['code' => 'regular'], ['name' => 'Regular', 'name_ka' => 'ჩვეულებრივი', 'is_default' => true, 'sort_order' => 1]);
        $member = VisitorCategory::updateOrCreate(['code' => 'member'], ['name' => 'Member 18+', 'name_ka' => 'წევრი +18', 'sort_order' => 2]);

        // [time slot, ticket type, age group, category (null = everyone), price]
        // Kids (4–10) have no evening tariff (marked "X" in the price table).
        $matrix = [
            [$morning, $single, $adults, $regular, 10],  [$morning, $single, $adults, $member, 10],
            [$morning, $single, $teens, null, 10],       [$morning, $single, $kids, null, 15],
            [$morning, $monthly, $adults, $regular, 100], [$morning, $monthly, $adults, $member, 50],
            [$morning, $monthly, $teens, null, 80],      [$morning, $monthly, $kids, null, 80],

            [$day, $single, $adults, $regular, 15],      [$day, $single, $adults, $member, 10],
            [$day, $single, $teens, null, 15],           [$day, $single, $kids, null, 10],
            [$day, $monthly, $adults, $regular, 120],    [$day, $monthly, $adults, $member, 50],
            [$day, $monthly, $teens, null, 100],         [$day, $monthly, $kids, null, 80],

            [$evening, $single, $adults, $regular, 20],  [$evening, $single, $adults, $member, 10],
            [$evening, $single, $teens, null, 15],
            [$evening, $monthly, $adults, $regular, 150], [$evening, $monthly, $adults, $member, 50],
            [$evening, $monthly, $teens, null, 120],
        ];

        foreach ($matrix as [$slot, $ticket, $group, $category, $price]) {
            PriceRule::updateOrCreate([
                'time_slot_id' => $slot->id,
                'ticket_type_id' => $ticket->id,
                'age_group_id' => $group->id,
                'visitor_category_id' => $category?->id,
            ], ['price' => $price, 'is_active' => true]);
        }

        // Global multiplier: change 1.000 to e.g. 1.100 to raise every price by 10%.
        PriceCoefficient::firstOrCreate(['name' => 'Global'], [
            'description' => 'Applies to every price. 1.000 = no change.',
            'coefficient' => 1,
            'is_active' => true,
        ]);
    }
}
