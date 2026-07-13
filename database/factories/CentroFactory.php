<?php

namespace Database\Factories;

use App\Models\Centro;
use App\Models\Paroquia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Centro>
 */
class CentroFactory extends Factory
{
    public function definition(): array
    {
        return [
            'paroquia_id' => Paroquia::factory(),
            'nome' => 'Centro '.fake()->unique()->streetName(),
            'localizacao' => fake()->address(),
            'responsavel_local' => fake()->name(),
            'status' => 'ativo',
        ];
    }
}
