<?php

namespace Database\Factories;

use App\Models\ClassSession;
use App\Models\ReplacementRequest;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReplacementRequest>
 */
class ReplacementRequestFactory extends Factory
{
    protected $model = ReplacementRequest::class;

    public function definition(): array
    {
        return [
            'semester_id' => 1,
            'proposer_id' => User::factory(),
            'class_session_id' => ClassSession::factory(),
            'week_number' => fake()->numberBetween(1, 14),
            'replacement_time_slot_id' => TimeSlot::factory(),
            'status' => 'pending',
            'submitted_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }
}
