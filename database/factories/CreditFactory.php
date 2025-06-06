<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\User;
use App\Models\Event;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Credit>
 */
class CreditFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::where('role', 'member')->where('is_approved', true)->inRandomOrder()->value('id'),
            'event_id' => Event::inRandomOrder()->value('id'),
            'cm_id' => User::where('role', 'cm')->value('id'),
            'amount' => $this->faker->randomFloat(2, 5, 15),
        ];
    }
}
