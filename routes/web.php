<?php

use App\Enums\EstadoInscricaoTurma;
use App\Exports\ArrayExport;
use App\Models\AnoLetivo;
use App\Models\Catequista;
use App\Models\Catequizando;
use App\Models\Centro;
use App\Models\FielCentro;
use App\Models\Inscricao;
use App\Models\Movimento;
use App\Models\Turma;
use Illuminate\Support\Str;
use App\Services\BalancoReceitasDespesasService;
use App\Services\DemonstrativoArrecadacaoService;
use App\Services\DemonstrativoDespesasService;
use App\Services\FieisPorSituacaoService;
use App\Services\MatrizDizimosService;
use App\Support\RelatorioPdf;
use App\Support\ResolveCentroExportacao;
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
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'coordenador_centro']), 403);

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
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'coordenador_centro']), 403);

        $ano = (int) $request->query('ano', now()->year);
        $centroIds = MatrizDizimosService::centrosPermitidos($user, $request->query('centro_id'));
        // "Todos os centros" (mais de um id resolvido) nao tem um Centro
        // unico para o cabecalho do PDF — so se mostra quando a consulta
        // ficou mesmo restrita a um.
        $centro = count($centroIds) === 1 ? Centro::find($centroIds[0]) : null;

        return RelatorioPdf::view('pdfs.relatorios.matriz-assiduidade', [
            'titulo' => 'Matriz de Assiduidade do Dízimo',
            'paroquia' => $user->paroquiaParaExportacao($centro),
            'centro' => $centro,
            'ano' => $ano,
            'linhas' => MatrizDizimosService::calcular($centroIds, $ano),
        ])->name('matriz-assiduidade.pdf');
    })->name('matriz-assiduidade.pdf');

    Route::get('/demonstrativo-arrecadacao/excel', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'coordenador_centro', 'consultor']), 403);

        $centro = ResolveCentroExportacao::centro($user, $request);
        $ano = (int) $request->query('ano', now()->year);
        $dados = DemonstrativoArrecadacaoService::calcular($ano, $centro?->id);
        $meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        $rows = [];
        foreach ($meses as $i => $mesLabel) {
            $linha = $dados['por_mes_categoria'][$i + 1];
            $row = ['Mês' => $mesLabel];
            foreach ($dados['categorias'] as $categoria) {
                $row[$categoria['nome']] = $linha[$categoria['chave']];
            }
            $row['Total'] = array_sum($linha);
            $rows[] = $row;
        }

        return Excel::download(
            new ArrayExport($rows, ['Mês', ...$dados['categorias']->pluck('nome')->all(), 'Total']),
            'demonstrativo-arrecadacao.xlsx'
        );
    })->name('demonstrativo-arrecadacao.excel');

    Route::get('/demonstrativo-arrecadacao/pdf', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'coordenador_centro', 'consultor']), 403);

        $centro = ResolveCentroExportacao::centro($user, $request);
        $ano = (int) $request->query('ano', now()->year);

        return RelatorioPdf::view('pdfs.relatorios.demonstrativo-arrecadacao', [
            'titulo' => 'Demonstrativo Unificado de Receitas (Arrecadação)',
            'paroquia' => $user->paroquiaParaExportacao($centro),
            'ano' => $ano,
            'dados' => DemonstrativoArrecadacaoService::calcular($ano, $centro?->id),
        ])->name('demonstrativo-arrecadacao.pdf');
    })->name('demonstrativo-arrecadacao.pdf');

    Route::get('/demonstrativo-despesas/excel', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'coordenador_centro', 'consultor']), 403);

        $centro = ResolveCentroExportacao::centro($user, $request);
        $ano = (int) $request->query('ano', now()->year);
        $dados = DemonstrativoDespesasService::calcular($ano, $centro?->id);
        $meses = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];

        $rows = [];
        foreach ($meses as $i => $mesLabel) {
            $linha = $dados['por_mes_categoria'][$i + 1];
            $row = ['Mês' => $mesLabel];
            foreach ($dados['categorias'] as $categoria) {
                $row[$categoria->nome] = $linha[$categoria->id];
            }
            $row['Total'] = array_sum($linha);
            $rows[] = $row;
        }

        return Excel::download(
            new ArrayExport($rows, ['Mês', ...$dados['categorias']->pluck('nome')->all(), 'Total']),
            'demonstrativo-despesas.xlsx'
        );
    })->name('demonstrativo-despesas.excel');

    Route::get('/demonstrativo-despesas/pdf', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'coordenador_centro', 'consultor']), 403);

        $centro = ResolveCentroExportacao::centro($user, $request);
        $ano = (int) $request->query('ano', now()->year);

        return RelatorioPdf::view('pdfs.relatorios.demonstrativo-despesas', [
            'titulo' => 'Demonstrativo Unificado de Despesas',
            'paroquia' => $user->paroquiaParaExportacao($centro),
            'ano' => $ano,
            'dados' => DemonstrativoDespesasService::calcular($ano, $centro?->id),
        ])->name('demonstrativo-despesas.pdf');
    })->name('demonstrativo-despesas.pdf');

    Route::get('/rastreabilidade-bancaria/pdf', function () {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'consultor']), 403);

        return RelatorioPdf::view('pdfs.relatorios.rastreabilidade-bancaria', [
            'titulo' => 'Rastreabilidade Bancária por Conta',
            'paroquia' => $user->paroquiaParaExportacao(),
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
            'paroquia' => $user->paroquiaParaExportacao(),
            'vinculos' => $query->get(),
        ])->name('repasses-inter-centro.pdf');
    })->name('repasses-inter-centro.pdf');

    Route::get('/balanco-receitas-despesas/excel', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'coordenador_centro', 'consultor']), 403);

        $centro = ResolveCentroExportacao::centro($user, $request);
        $ano = (int) $request->query('ano', now()->year);
        $dados = BalancoReceitasDespesasService::calcular($ano, $centro?->id);

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
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'coordenador_centro', 'consultor']), 403);

        $centro = ResolveCentroExportacao::centro($user, $request);
        $ano = (int) $request->query('ano', now()->year);

        return RelatorioPdf::view('pdfs.relatorios.balanco-receitas-despesas', [
            'titulo' => 'Balanço de Receitas vs Despesas',
            'paroquia' => $user->paroquiaParaExportacao($centro),
            'ano' => $ano,
            'dados' => BalancoReceitasDespesasService::calcular($ano, $centro?->id),
        ])->name('balanco-receitas-despesas.pdf');
    })->name('balanco-receitas-despesas.pdf');

    Route::get('/fieis-por-situacao/excel', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'coordenador_centro', 'consultor']), 403);

        $centro = ResolveCentroExportacao::centro($user, $request);
        $ano = (int) $request->query('ano', now()->year);
        $linhas = FieisPorSituacaoService::calcular($ano, $centro?->id);

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
        abort_unless($user->hasRole(['admin_geral', 'administrador_paroquial', 'tesoureiro_paroquial', 'tesoureiro_centro', 'coordenador_centro', 'consultor']), 403);

        $centro = ResolveCentroExportacao::centro($user, $request);
        $ano = (int) $request->query('ano', now()->year);

        return RelatorioPdf::view('pdfs.relatorios.fieis-por-situacao', [
            'titulo' => 'Relatório de Fiéis por Situação',
            'paroquia' => $user->paroquiaParaExportacao($centro),
            'ano' => $ano,
            'linhas' => FieisPorSituacaoService::calcular($ano, $centro?->id),
        ])->name('fieis-por-situacao.pdf');
    })->name('fieis-por-situacao.pdf');

    Route::get('/log-auditoria/pdf', function () {
        $user = Auth::user();
        abort_unless($user->hasRole('admin_geral'), 403);

        return RelatorioPdf::view('pdfs.relatorios.log-auditoria', [
            'titulo' => 'Log de Auditoria do Sistema',
            'paroquia' => $user->paroquiaParaExportacao(),
            'atividades' => Activity::where('subject_type', Movimento::class)->with('causer')->latest()->get(),
        ])->name('log-auditoria.pdf');
    })->name('log-auditoria.pdf');

    // Exportacoes Excel/PDF do modulo Catequese — mesmos criterios de papel
    // dos viewAny() das Policies correspondentes (TurmaPolicy/
    // CatequizandoPolicy/InscricaoPolicy/CatequistaPolicy), com o mesmo
    // reforco de centro_id para quem so gere/le o seu proprio centro. Todas
    // aceitam ?estado= (ver App\Filament\Concerns\TemExportacaoComEstado, que
    // monta o link a partir do modal "Exportar" nos Resources/RelationManagers)
    // — "todos" nao filtra, qualquer outro valor filtra pela coluna de estado
    // relevante a cada lista.
    Route::get('/turma/{turma}/catequizandos/excel', function (Turma $turma, Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'coordenador_catequese_paroquia', 'coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese']), 403);

        if ($user->hasRole(['coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese'])) {
            abort_unless($turma->centro_id === $user->centro_id, 403);
        }

        $estado = $request->query('estado', EstadoInscricaoTurma::Ativo->value);

        $inscricoes = $turma->inscricoes()
            ->with('catequizando')
            ->when($estado !== 'todos', fn ($q) => $q->where('inscricao_turma.status', $estado))
            ->get();

        $rows = $inscricoes->values()->map(fn ($inscricao, $indice) => [
            'Nº' => $indice + 1,
            'Catequizando' => $inscricao->catequizando->nome_completo,
            'Nº Ficha' => $inscricao->numero_ficha,
            'Início' => $inscricao->pivot->data_inicio->format('d/m/Y'),
            'Estado' => $inscricao->pivot->status->label(),
        ])->all();

        $nomeFicheiro = 'catequizandos_'.Str::slug($turma->descricaoCurta()).'_'.$estado;

        return Excel::download(
            new ArrayExport($rows, ['Nº', 'Catequizando', 'Nº Ficha', 'Início', 'Estado']),
            "{$nomeFicheiro}.xlsx"
        );
    })->name('turma-catequizandos.excel');

    Route::get('/turma/{turma}/catequizandos/pdf', function (Turma $turma, Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'coordenador_catequese_paroquia', 'coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese']), 403);

        if ($user->hasRole(['coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese'])) {
            abort_unless($turma->centro_id === $user->centro_id, 403);
        }

        $estado = $request->query('estado', EstadoInscricaoTurma::Ativo->value);
        $nomeFicheiro = 'catequizandos_'.Str::slug($turma->descricaoCurta()).'_'.$estado;

        return RelatorioPdf::view('pdfs.relatorios.turma-catequizandos', [
            'titulo' => 'Catequizandos da Turma',
            'paroquia' => $user->paroquiaParaExportacao($turma->centro),
            'turma' => $turma,
            'sacramentos' => $turma->sacramentos,
            'catequistas' => $turma->catequistas()->wherePivotNull('data_fim')->get(),
            'inscricoes' => $turma->inscricoes()
                ->with('catequizando')
                ->when($estado !== 'todos', fn ($q) => $q->where('inscricao_turma.status', $estado))
                ->get(),
        ])->name("{$nomeFicheiro}.pdf");
    })->name('turma-catequizandos.pdf');

    Route::get('/catequizandos/excel', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'coordenador_catequese_paroquia', 'coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese']), 403);

        $estado = $request->query('estado', 'ativo');
        $anoLetivo = $request->query('ano_letivo', 'todos');
        $centro = ResolveCentroExportacao::centroCatequese($user, $request);

        $query = Catequizando::query()->with('centro');

        if ($centro) {
            $query->where('centro_id', $centro->id);
        }

        if ($estado !== 'todos') {
            $query->where('status', $estado);
        }

        if ($anoLetivo !== 'todos') {
            $query->whereHas('inscricoes', fn ($q) => $q->where('ano_letivo_id', $anoLetivo));
        }

        $rows = $query->orderBy('nome_completo')->get()->values()->map(fn ($catequizando, $indice) => [
            'Nº' => $indice + 1,
            'Nome' => $catequizando->nome_completo,
            'Centro' => $catequizando->centro->nome,
            'Data de Nascimento' => $catequizando->data_nascimento->format('d/m/Y'),
            'Sexo' => $catequizando->sexo === 'M' ? 'Masculino' : 'Feminino',
            'Telefone' => $catequizando->telefone,
            'Estado' => $catequizando->status === 'ativo' ? 'Activo' : 'Inactivo',
        ])->all();

        $nomeFicheiro = 'catequizandos_'.AnoLetivo::slugParaExportacao($anoLetivo).'_'.$estado;

        return Excel::download(
            new ArrayExport($rows, ['Nº', 'Nome', 'Centro', 'Data de Nascimento', 'Sexo', 'Telefone', 'Estado']),
            "{$nomeFicheiro}.xlsx"
        );
    })->name('catequizandos.excel');

    Route::get('/catequizandos/pdf', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'coordenador_catequese_paroquia', 'coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese']), 403);

        $estado = $request->query('estado', 'ativo');
        $anoLetivo = $request->query('ano_letivo', 'todos');
        $centro = ResolveCentroExportacao::centroCatequese($user, $request);

        $query = Catequizando::query()->with('centro');

        if ($centro) {
            $query->where('centro_id', $centro->id);
        }

        if ($estado !== 'todos') {
            $query->where('status', $estado);
        }

        if ($anoLetivo !== 'todos') {
            $query->whereHas('inscricoes', fn ($q) => $q->where('ano_letivo_id', $anoLetivo));
        }

        $nomeFicheiro = 'catequizandos_'.AnoLetivo::slugParaExportacao($anoLetivo).'_'.$estado;

        return RelatorioPdf::view('pdfs.relatorios.catequizandos', [
            'titulo' => 'Lista de Catequizandos',
            'paroquia' => $user->paroquiaParaExportacao($centro),
            'catequizandos' => $query->orderBy('nome_completo')->get(),
        ])->name("{$nomeFicheiro}.pdf");
    })->name('catequizandos.pdf');

    Route::get('/inscricoes/excel', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'coordenador_catequese_paroquia', 'coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese']), 403);

        $estado = $request->query('estado', 'todos');
        $anoLetivo = $request->query('ano_letivo', 'todos');
        $centro = ResolveCentroExportacao::centroCatequese($user, $request);

        $query = Inscricao::query()->with(['catequizando', 'centro', 'anoLetivo', 'anoCatequetico', 'sacramentos']);

        if ($centro) {
            $query->where('centro_id', $centro->id);
        }

        if ($estado !== 'todos') {
            $query->where('estado', $estado);
        }

        if ($anoLetivo !== 'todos') {
            $query->where('ano_letivo_id', $anoLetivo);
        }

        $rows = $query->orderBy('data_atendimento', 'desc')->get()->values()->map(fn ($inscricao, $indice) => [
            'Nº' => $indice + 1,
            'Catequizando' => $inscricao->catequizando->nome_completo,
            'Centro' => $inscricao->centro->nome,
            'Ano Lectivo' => $inscricao->anoLetivo->nome,
            'Ano Catequese' => $inscricao->anoCatequetico->nome ?? '',
            'Sacramento(s)' => $inscricao->sacramentos->pluck('nome')->implode(', '),
            'Nº Ficha' => $inscricao->numero_ficha,
            'Data' => $inscricao->data_atendimento->format('d/m/Y'),
            'Estado' => ucfirst($inscricao->estado->value),
        ])->all();

        $nomeFicheiro = 'inscricoes_'.AnoLetivo::slugParaExportacao($anoLetivo).'_'.$estado;

        return Excel::download(
            new ArrayExport($rows, ['Nº', 'Catequizando', 'Centro', 'Ano Lectivo', 'Ano Catequese', 'Sacramento(s)', 'Nº Ficha', 'Data', 'Estado']),
            "{$nomeFicheiro}.xlsx"
        );
    })->name('inscricoes.excel');

    Route::get('/inscricoes/pdf', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'coordenador_catequese_paroquia', 'coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese']), 403);

        $estado = $request->query('estado', 'todos');
        $anoLetivo = $request->query('ano_letivo', 'todos');
        $centro = ResolveCentroExportacao::centroCatequese($user, $request);

        $query = Inscricao::query()->with(['catequizando', 'centro', 'anoLetivo', 'anoCatequetico', 'sacramentos']);

        if ($centro) {
            $query->where('centro_id', $centro->id);
        }

        if ($estado !== 'todos') {
            $query->where('estado', $estado);
        }

        if ($anoLetivo !== 'todos') {
            $query->where('ano_letivo_id', $anoLetivo);
        }

        $nomeFicheiro = 'inscricoes_'.AnoLetivo::slugParaExportacao($anoLetivo).'_'.$estado;

        return RelatorioPdf::view('pdfs.relatorios.inscricoes', [
            'titulo' => 'Lista de Inscrições',
            'paroquia' => $user->paroquiaParaExportacao($centro),
            'inscricoes' => $query->orderBy('data_atendimento', 'desc')->get(),
        ])->name("{$nomeFicheiro}.pdf");
    })->name('inscricoes.pdf');

    Route::get('/catequistas/excel', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'coordenador_catequese_paroquia', 'coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese']), 403);

        $estado = $request->query('estado', 'ativo');
        $anoLetivo = $request->query('ano_letivo', 'todos');
        $centro = ResolveCentroExportacao::centroCatequese($user, $request);

        $query = Catequista::query()->with('centro');

        if ($centro) {
            $query->where('centro_id', $centro->id);
        }

        if ($estado !== 'todos') {
            $query->where('ativo', $estado === 'ativo');
        }

        if ($anoLetivo !== 'todos') {
            $query->whereHas('turmas', fn ($q) => $q->where('ano_letivo_id', $anoLetivo));
        }

        $rows = $query->orderBy('nome_completo')->get()->values()->map(fn ($catequista, $indice) => [
            'Nº' => $indice + 1,
            'Nome' => $catequista->nome_completo,
            'Centro' => $catequista->centro?->nome,
            'Telefone' => $catequista->telefone,
            'Email' => $catequista->email,
            'Estado' => $catequista->ativo ? 'Activo' : 'Inactivo',
        ])->all();

        $nomeFicheiro = 'catequistas_'.AnoLetivo::slugParaExportacao($anoLetivo).'_'.$estado;

        return Excel::download(
            new ArrayExport($rows, ['Nº', 'Nome', 'Centro', 'Telefone', 'Email', 'Estado']),
            "{$nomeFicheiro}.xlsx"
        );
    })->name('catequistas.excel');

    Route::get('/catequistas/pdf', function (Request $request) {
        $user = Auth::user();
        abort_unless($user->hasRole(['admin_geral', 'coordenador_catequese_paroquia', 'coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese']), 403);

        $estado = $request->query('estado', 'ativo');
        $anoLetivo = $request->query('ano_letivo', 'todos');
        $centro = ResolveCentroExportacao::centroCatequese($user, $request);

        $query = Catequista::query()->with('centro');

        if ($centro) {
            $query->where('centro_id', $centro->id);
        }

        if ($estado !== 'todos') {
            $query->where('ativo', $estado === 'ativo');
        }

        if ($anoLetivo !== 'todos') {
            $query->whereHas('turmas', fn ($q) => $q->where('ano_letivo_id', $anoLetivo));
        }

        $nomeFicheiro = 'catequistas_'.AnoLetivo::slugParaExportacao($anoLetivo).'_'.$estado;

        return RelatorioPdf::view('pdfs.relatorios.catequistas', [
            'titulo' => 'Lista de Catequistas',
            'paroquia' => $user->paroquiaParaExportacao($centro),
            'catequistas' => $query->orderBy('nome_completo')->get(),
        ])->name("{$nomeFicheiro}.pdf");
    })->name('catequistas.pdf');

});
