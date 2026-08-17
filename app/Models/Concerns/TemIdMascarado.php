<?php

namespace App\Models\Concerns;

use Hashids\Hashids;

/**
 * Mascara o ID real do model nos URLs (route model binding), usando um
 * hashid reversivel em vez do id sequencial da BD — pedido do utilizador,
 * para nao expor ids incrementais nos URLs do Filament (ex.:
 * /admin/catequizandos/{record}/edit) nem nas rotas de exportacao proprias
 * (ex.: /relatorios/turma/{turma}/...).
 *
 * So mascara o parametro do URL — a query, as Policies e o resto da app
 * continuam a trabalhar com o id real (`getKey()`), sem qualquer mudanca.
 *
 * Sal por classe (hash do FQCN do model + salt do .env): o mesmo id numerico
 * produz hashids diferentes em models diferentes, para nao correlacionar
 * hashids entre tabelas nem reaproveitar um hashid de um model no lugar de
 * outro.
 *
 * Armadilha corrigida: concatenar o FQCN a seguir a um salt ja longo
 * (config('hashids.salt') tem 32 caracteres) nao funciona — o shuffle do
 * hashids/hashids so le um numero limitado de caracteres do sal, por isso
 * tudo o que vier depois desse limite e ignorado em silencio (testado:
 * "{salt}App\Models\User" e "{salt}App\Models\CategoriaReceita" davam
 * exactamente o mesmo hashid para o id 1). Um hash sha256 do FQCN+salt tem
 * sempre o mesmo comprimento (64 caracteres) e distribui a diferenca por
 * todo o sal, por isso funciona correctamente.
 */
trait TemIdMascarado
{
    public function getRouteKeyName(): string
    {
        return 'hashid';
    }

    public function getHashidAttribute(): string
    {
        return static::hashidsParaEsteModel()->encode($this->getKey());
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<static>  $query
     * @return \Illuminate\Database\Eloquent\Builder<static>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        if ($field !== 'hashid') {
            return parent::resolveRouteBindingQuery($query, $value, $field);
        }

        $id = static::hashidsParaEsteModel()->decode((string) $value)[0] ?? 0;

        return $query->where($this->getKeyName(), $id);
    }

    protected static function hashidsParaEsteModel(): Hashids
    {
        return new Hashids(
            hash('sha256', static::class.config('hashids.salt')),
            (int) config('hashids.min_length', 8),
        );
    }
}
