<?php

namespace Database\Factories;

use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Venue>
 */
class VenueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_code' => 'B'.fake()->unique()->numberBetween(1, 999),
            'capacity' => 35,
            'room_type' => 'tutorial',
            'allowed_session_types' => 'L,T',
        ];
    }
}
