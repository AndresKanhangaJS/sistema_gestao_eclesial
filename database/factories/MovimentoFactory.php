<?php

namespace Database\Factories;

use App\Enums\StatusConciliacao;
use App\Enums\TipoMovimento;
use App\Models\Centro;
use App\Models\MetodoPagamento;
use App\Models\Movimento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Movimento>
 */
class MovimentoFactory extends Factory
{
    public function definition(): array
    {
        $centro = Centro::factory()->create();

        return [
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
            'usuario_id' => User::factory(),
            'metodo_pagamento_id' => MetodoPagamento::factory(),
            'tipo' => TipoMovimento::Ofertorio,
            'valor' => fake()->randomFloat(2, 10, 5000),
            'data_movimento' => fake()->date(),
            'status_conciliacao' => StatusConciliacao::Pendente,
        ];
    }

    public function dizimo(): static
    {
        return $this->state(fn () => [
            'tipo' => TipoMovimento::Dizimo,
            'ano_competencia' => now()->year,
            'mes_competencia' => fake()->numberBetween(1, 12),
        ]);
    }

    public function aprovado(): static
    {
        return $this->state(fn () => ['status_conciliacao' => StatusConciliacao::Aprovado]);
    }
}
