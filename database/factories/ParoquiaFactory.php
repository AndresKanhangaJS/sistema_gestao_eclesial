<?php

namespace Database\Factories;

use App\Models\Paroquia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Paroquia>
 */
class ParoquiaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => 'Paróquia '.fake()->unique()->city(),
            'diocese' => fake()->city(),
            'morada' => fake()->address(),
            'responsavel' => fake()->name(),
            'email_contato' => fake()->unique()->safeEmail(),
            'telefone' => fake()->phoneNumber(),
            'status' => 'ativo',
        ];
    }
}
