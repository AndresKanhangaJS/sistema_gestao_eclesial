<?php

use App\Exports\ArrayExport;
use App\Models\Centro;
use App\Models\FielCentro;
use App\Models\Movimento;
use App\Services\BalancoReceitasDespesasService;
use App\Services\DemonstrativoArrecadacaoService;
use App\Services\FieisPorSituacaoService;
use App\Services\MatrizDizimosService;
use App\Support\RelatorioPdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Activitylog\Models\Activity;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Relatórios — exportação PDF/Excel (Módulo 7)
|--------------------------------------------------------------------------
| Protegidas por auth + verificação de role inline (mesmos critérios dos
| canAccess() das páginas Filament correspondentes). tesoureiro_centro tem
| o centro_id sempre forçado ao seu próprio, ignorando o query param, para
| não conseguir ver dados de outro centro por URL directa.
*/
Route::middleware('auth')->prefix('relatorios')->name('relatorios.')->group(function () {

    Route::get('/matriz-assiduidade/excel', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro']), 403);

        $ano = (int) $request->query('ano', now()->year);
        $centroIds = MatrizDizimosService::centrosPermitidos($user, $request->query('centro_id'));

        $linhas = MatrizDizimosService::calcular($centroIds, $ano);
        $meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        $rows = collect($linhas)->map(function ($linha) use ($meses) {
            $row = ['Fiel' => $linha['fiel']->nome];
            foreach ($linha['meses'] as $i => $estado) {
                $row[$meses[$i - 1]] = $estado;
            }
            $row['Segmento'] = $linha['segmento'] ?? $linha['total_pagos'].'/12';

            return $row;
        })->all();

        return Excel::download(
            new ArrayExport($rows, ['Fiel', ...$meses, 'Segmento']),
            'matriz-assiduidade.xlsx'
        );
    })->name('matriz-assiduidade.excel');

    Route::get('/matriz-assiduidade/pdf', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro']), 403);

        $ano = (int) $request->query('ano', now()->year);
        $centroIds = MatrizDizimosService::centrosPermitidos($user, $request->query('centro_id'));
        // "Todos os centros" (mais de um id resolvido) nao tem um Centro
        // unico para o cabecalho do PDF — so se mostra quando a consulta
        // ficou mesmo restrita a um.
        $centro = count($centroIds) === 1 ? Centro::find($centroIds[0]) : null;

        return RelatorioPdf::view('pdfs.relatorios.matriz-assiduidade', [
            'titulo' => 'Matriz de Assiduidade do Dízimo',
            'paroquia' => $centro?->paroquia ?? $user->paroquia,
            'centro' => $centro,
            'ano' => $ano,
            'linhas' => MatrizDizimosService::calcular($centroIds, $ano),
        ])->name('matriz-assiduidade.pdf');
    })->name('matriz-assiduidade.pdf');

    Route::get('/demonstrativo-arrecadacao/excel', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'consultor']), 403);

        $centroId = $user->hasRole('tesoureiro_centro') ? $user->centro_id : null;
        $ano = (int) $request->query('ano', now()->year);
        $dados = DemonstrativoArrecadacaoService::calcular($ano, $centroId);
        $meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        $rows = [];
        foreach ($meses as $i => $mesLabel) {
            $linha = $dados['por_mes_tipo'][$i + 1];
            $rows[] = [
                'Mês' => $mesLabel,
                'Dízimo' => $linha['dizimo'],
                'Ofertório' => $linha['ofertorio'],
                'Outras Contribuições' => $linha['campanha'],
                'Total' => array_sum($linha),
            ];
        }

        return Excel::download(
            new ArrayExport($rows, ['Mês', 'Dízimo', 'Ofertório', 'Outras Contribuições', 'Total']),
            'demonstrativo-arrecadacao.xlsx'
        );
    })->name('demonstrativo-arrecadacao.excel');

    Route::get('/demonstrativo-arrecadacao/pdf', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'consultor']), 403);

        $centroId = $user->hasRole('tesoureiro_centro') ? $user->centro_id : null;
        $ano = (int) $request->query('ano', now()->year);

        return RelatorioPdf::view('pdfs.relatorios.demonstrativo-arrecadacao', [
            'titulo' => 'Demonstrativo Unificado de Arrecadação',
            'paroquia' => $user->paroquia,
            'ano' => $ano,
            'dados' => DemonstrativoArrecadacaoService::calcular($ano, $centroId),
        ])->name('demonstrativo-arrecadacao.pdf');
    })->name('demonstrativo-arrecadacao.pdf');

    Route::get('/rastreabilidade-bancaria/pdf', function () {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'consultor']), 403);

        return RelatorioPdf::view('pdfs.relatorios.rastreabilidade-bancaria', [
            'titulo' => 'Rastreabilidade Bancária por Conta',
            'paroquia' => $user->paroquia,
            'movimentos' => Movimento::whereNotNull('banco_id')->with(['banco', 'centro'])->get(),
        ])->name('rastreabilidade-bancaria.pdf');
    })->name('rastreabilidade-bancaria.pdf');

    Route::get('/repasses-inter-centro/pdf', function () {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'consultor']), 403);

        $query = FielCentro::withoutGlobalScopes()->whereNotNull('motivo_transferencia')->with(['fiel', 'centro']);

        if ($user->hasRole(['administrador_paroquial', 'tesoureiro_paroquial'])) {
            $query->whereHas('fiel', fn ($q) => $q->where('paroquia_id', $user->paroquia_id));
        }

        return RelatorioPdf::view('pdfs.relatorios.repasses-inter-centro', [
            'titulo' => 'Auditoria de Repasses Inter-Centro',
            'paroquia' => $user->paroquia,
            'vinculos' => $query->get(),
        ])->name('repasses-inter-centro.pdf');
    })->name('repasses-inter-centro.pdf');

    Route::get('/balanco-receitas-despesas/excel', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'consultor']), 403);

        $centroId = $user->hasRole('tesoureiro_centro') ? $user->centro_id : null;
        $ano = (int) $request->query('ano', now()->year);
        $dados = BalancoReceitasDespesasService::calcular($ano, $centroId);

        $rows = [];
        foreach (['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'] as $i => $mesLabel) {
            $linha = $dados['por_mes'][$i + 1];
            $rows[] = ['Mês' => $mesLabel, 'Receitas' => $linha['receitas'], 'Despesas' => $linha['despesas'], 'Saldo' => $linha['saldo']];
        }

        return Excel::download(
            new ArrayExport($rows, ['Mês', 'Receitas', 'Despesas', 'Saldo']),
            'balanco-receitas-despesas.xlsx'
        );
    })->name('balanco-receitas-despesas.excel');

    Route::get('/balanco-receitas-despesas/pdf', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'consultor']), 403);

        $centroId = $user->hasRole('tesoureiro_centro') ? $user->centro_id : null;
        $ano = (int) $request->query('ano', now()->year);

        return RelatorioPdf::view('pdfs.relatorios.balanco-receitas-despesas', [
            'titulo' => 'Balanço de Receitas vs Despesas',
            'paroquia' => $user->paroquia,
            'ano' => $ano,
            'dados' => BalancoReceitasDespesasService::calcular($ano, $centroId),
        ])->name('balanco-receitas-despesas.pdf');
    })->name('balanco-receitas-despesas.pdf');

    Route::get('/fieis-por-situacao/excel', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'consultor']), 403);

        $centroId = $user->hasRole('tesoureiro_centro') ? $user->centro_id : null;
        $ano = (int) $request->query('ano', now()->year);
        $linhas = FieisPorSituacaoService::calcular($ano, $centroId);

        $rows = collect($linhas)->map(fn ($linha) => [
            'Fiel' => $linha['fiel']->nome,
            'Dízimos pagos' => $linha['total_pagos'].'/12',
            'Situação' => $linha['segmento'] ?? '—',
        ])->all();

        return Excel::download(
            new ArrayExport($rows, ['Fiel', 'Dízimos pagos', 'Situação']),
            'fieis-por-situacao.xlsx'
        );
    })->name('fieis-por-situacao.excel');

    Route::get('/fieis-por-situacao/pdf', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'consultor']), 403);

        $centroId = $user->hasRole('tesoureiro_centro') ? $user->centro_id : null;
        $ano = (int) $request->query('ano', now()->year);

        return RelatorioPdf::view('pdfs.relatorios.fieis-por-situacao', [
            'titulo' => 'Relatório de Fiéis por Situação',
            'paroquia' => $user->paroquia,
            'ano' => $ano,
            'linhas' => FieisPorSituacaoService::calcular($ano, $centroId),
        ])->name('fieis-por-situacao.pdf');
    })->name('fieis-por-situacao.pdf');

    Route::get('/log-auditoria/pdf', function () {
        $user = Auth::user();
        abort_unless($user->hasRole('admin_geral'), 403);

        return RelatorioPdf::view('pdfs.relatorios.log-auditoria', [
            'titulo' => 'Log de Auditoria do Sistema',
            'paroquia' => $user->paroquia,
            'atividades' => Activity::where('subject_type', Movimento::class)->with('causer')->latest()->get(),
        ])->name('log-auditoria.pdf');
    })->name('log-auditoria.pdf');

});
