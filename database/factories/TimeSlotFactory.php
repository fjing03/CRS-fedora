<?php

namespace Database\Factories;

use App\Models\TimeSlot;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TimeSlot>
 */
class TimeSlotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'semester_id' => 1,
            'class_session_id' => null,
            'week_number' => 1,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'venue_id' => Venue::factory(),
            'status' => 'available',
            'version' => 1,
        ];
    }
}
