<?php

namespace Database\Factories;

use App\Models\ClassSession;
use App\Models\Module;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClassSession>
 */
class ClassSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'semester_id' => 1,
            'module_id' => Module::factory(),
            'lecturer_id' => 1,
            'day_of_week' => 1,
            'start_time' => '09:00:00',
            'end_time' => '11:00:00',
            'venue_id' => Venue::factory(),
            'session_type' => 'L',
        ];
    }
}
