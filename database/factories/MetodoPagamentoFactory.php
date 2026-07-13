<?php

namespace Database\Factories;

use App\Models\MetodoPagamento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MetodoPagamento>
 */
class MetodoPagamentoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nome' => fake()->unique()->randomElement(['Numerário', 'Transferência Bancária', 'TPA', 'Multicaixa']),
            'exige_comprovativo' => false,
            'status' => 'ativo',
        ];
    }

    public function exigeComprovativo(): static
    {
        return $this->state(fn () => ['exige_comprovativo' => true]);
    }
}
