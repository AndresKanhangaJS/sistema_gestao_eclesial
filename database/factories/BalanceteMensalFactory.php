<?php

namespace Database\Factories;

use App\Models\BalanceteMensal;
use App\Models\Centro;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BalanceteMensal>
 */
class BalanceteMensalFactory extends Factory
{
    public function definition(): array
    {
        $centro = Centro::factory()->create();

        return [
            'paroquia_id' => $centro->paroquia_id,
            'centro_id' => $centro->id,
            'usuario_id' => User::factory(),
            'ano' => now()->year,
            'mes' => fake()->numberBetween(1, 12),
            'saldo_mes_anterior' => 0,
            'total_receitas' => 0,
            'total_despesas' => 0,
            'saldo_mes' => 0,
            'percentual_bispado_usado' => 20,
            'percentual_dizimo_usado' => 60,
            'fechado_em' => now(),
        ];
    }
}
