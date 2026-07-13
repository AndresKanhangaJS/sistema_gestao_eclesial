<?php

namespace Database\Factories;

use App\Models\Fiel;
use App\Models\Paroquia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Fiel>
 */
class FielFactory extends Factory
{
    public function definition(): array
    {
        return [
            'paroquia_id' => Paroquia::factory(),
            'nome' => fake()->name(),
            'codigo_dizimista' => fake()->unique()->numerify('DZ-#####'),
            'telefone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'data_nascimento' => fake()->date(),
            'status' => 'ativo',
        ];
    }
}
