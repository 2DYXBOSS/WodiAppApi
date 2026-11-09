<?php

namespace Database\Factories;

use App\Models\Professional;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Professional> */
class ProfessionalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => '+22505'.fake()->numerify('########'),
            'email' => fake()->safeEmail(),
            'job_title' => 'Barbier',
            'specialties' => ['Dégradés'],
            'is_active' => true,
            'is_bookable' => true,
            'is_public' => true,
        ];
    }

    /** Travaille du mardi au dimanche, 09:00–19:00. */
    public function withSchedule(string $start = '09:00', string $end = '19:00'): static
    {
        return $this->afterCreating(function (Professional $pro) use ($start, $end) {
            foreach (range(1, 7) as $day) {
                $pro->workingHours()->create([
                    'iso_day' => $day, 'is_working' => $day !== 1,
                    'start_time' => $day === 1 ? null : $start, 'end_time' => $day === 1 ? null : $end,
                ]);
            }
        });
    }
}
