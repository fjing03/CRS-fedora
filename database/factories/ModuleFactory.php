<?php

namespace Database\Factories;

use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Module>
 */
class ModuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_code' => fake()->unique()->numerify('BMCS-####'),
            'module_name' => fake()->sentence(),
            'allowed_session_types' => 'L,T',
        ];
    }
}
