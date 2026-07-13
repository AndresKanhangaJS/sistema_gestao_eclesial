<?php

namespace Database\Factories;

use App\Models\Banco;
use App\Models\Paroquia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Banco>
 */
class BancoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'paroquia_id' => Paroquia::factory(),
            'nome_banco' => fake()->randomElement(['BFA', 'BAI', 'BIC', 'Standard Bank']),
            'sigla' => fake()->lexify('???'),
            'numero_conta' => fake()->unique()->numerify('##########'),
            'iban' => 'AO06'.fake()->unique()->numerify('#################'),
            'status' => 'ativo',
        ];
    }
}
