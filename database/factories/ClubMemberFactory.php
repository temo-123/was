<?php

namespace Database\Factories;

use App\Models\ClubMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubMember>
 */
class ClubMemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'personal_id' => fake()->unique()->numerify('###########'),
            'phone' => fake()->unique()->numerify('5########'),
            'email' => fake()->optional()->safeEmail(),
            'birth_date' => fake()->optional()->dateTimeBetween('-60 years', '-10 years')?->format('Y-m-d'),
            'joined_on' => null,
            'comment' => null,
            'is_active' => true,
        ];
    }
}
