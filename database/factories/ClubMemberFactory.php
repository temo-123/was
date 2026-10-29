<?php

namespace Database\Factories;

use App\Models\ClubMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubMember>
 */
class ClubMemberFactory extends Factory
{
    /** A member card with every ClubMember::PROFILE_FIELDS filled in. */
    public function complete(): static
    {
        return $this->state(fn () => [
            'email' => fake()->unique()->safeEmail(),
            'birth_date' => fake()->dateTimeBetween('-60 years', '-10 years')->format('Y-m-d'),
            'gender' => fake()->randomElement(array_keys(ClubMember::GENDERS)),
            'emergency_contact_name' => fake()->firstName(),
            'emergency_contact_phone' => fake()->numerify('5########'),
        ]);
    }

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'personal_id' => fake()->unique()->numerify('###########'),
            'phone' => fake()->unique()->numerify('5########'),
            'email' => fake()->optional()->safeEmail(),
            'birth_date' => fake()->optional()->dateTimeBetween('-60 years', '-10 years')?->format('Y-m-d'),
            'gender' => fake()->optional()->randomElement(array_keys(ClubMember::GENDERS)),
            'emergency_contact_name' => null,
            'emergency_contact_phone' => null,
            'joined_on' => null,
            'comment' => null,
            'is_active' => true,
        ];
    }
}
