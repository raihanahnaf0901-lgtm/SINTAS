<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class MapelFactory extends Factory
{
    public function definition(): array
    {
        return ['nama_mapel' => fake()->word()];
    }
}
