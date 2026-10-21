<?php

namespace Database\Factories;

use App\Models\Visitor;
use App\Models\VisitorCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'personal_id' => fake()->unique()->numerify('###########'),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-5 years')->format('Y-m-d'),
            'phone' => fake()->numerify('5########'),
            'email' => fake()->optional()->safeEmail(),
            'comment' => null,
            'visitor_category_id' => VisitorCategory::default()?->id,
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
        ];
    }
}
