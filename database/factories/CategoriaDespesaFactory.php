<?php

namespace Database\Factories;

use App\Models\CategoriaDespesa;
use App\Models\Paroquia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoriaDespesa>
 */
class CategoriaDespesaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'paroquia_id' => Paroquia::factory(),
            'nome' => fake()->unique()->words(2, true),
            'descricao' => fake()->sentence(),
            'status' => 'ativo',
        ];
    }
}
