<?php

namespace App\Policies;

use App\Models\AnoLetivo;
use App\Models\User;

/**
 * admin_geral tem acesso total via Gate::before (AppServiceProvider).
 * coordenador_catequese_paroquia: CRUD do ciclo anual da propria paroquia —
 * decisao ao nivel da paroquia, nao do centro (docs/modulos/catequese.md
 * seccao 3).
 * coordenador_catequese_centro, secretario_catequese e tesoureiro_catequese:
 * so leitura, da propria paroquia.
 * Sem soft delete nesta tabela (mesmo padrao de Centro/Paroquia): delete
 * nunca e permitido pela UI.
 */
class AnoLetivoPolicy
{
    private const GESTORES_PAROQUIA = ['coordenador_catequese_paroquia'];

    private const LEITORES = ['coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese'];

    public function viewAny(User $user): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, ...self::LEITORES]);
    }

    public function view(User $user, AnoLetivo $anoLetivo): bool
    {
        return $user->hasRole([...self::GESTORES_PAROQUIA, ...self::LEITORES])
            && $anoLetivo->paroquia_id === $user->paroquia_id;
    }

    public function create(User $user): bool
    {
        return $user->hasRole(self::GESTORES_PAROQUIA);
    }

    public function update(User $user, AnoLetivo $anoLetivo): bool
    {
        return $user->hasRole(self::GESTORES_PAROQUIA) && $anoLetivo->paroquia_id === $user->paroquia_id;
    }

    public function delete(User $user, AnoLetivo $anoLetivo): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, AnoLetivo $anoLetivo): bool
    {
        return false;
    }

    public function forceDelete(User $user, AnoLetivo $anoLetivo): bool
    {
        return false;
    }
}
