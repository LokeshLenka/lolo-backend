<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => fake()->randomElement([5, 35, 34]),
            'coordinator1' => fake()->randomElement([5, 35, 34]),
            'coordinator2' => fake()->randomElement([5, 35, 34]),
            'coordinator3' => null,
            'name' => fake()->text(50),
            'description' => fake()->text(50),
            'type' => fake()->randomElement(['all', 'members', 'club']),
            'timings' => fake()->dateTime($timezone = 'Asia/Kolkata'),
            'venue' => fake()->address(),
            'status' => fake()->randomElement(['upcoming', 'completed']),
            'credits_awarded' => fake()->randomFloat(null, 5, 25),
            'registration_deadline' => fake()->dateTimeInInterval('0 years', '+7 days'),
            'max_participants' => fake()->randomFloat(30, 70),
            'registration_mode' => fake()->randomElement(['online', 'offline']),
            'registration_place' => fake()->streetAddress(),
        ];
    }
}
