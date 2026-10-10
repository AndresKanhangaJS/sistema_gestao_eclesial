<?php

namespace App\Console\Commands;

use App\Enums\EstadoInscricao;
use App\Enums\EstadoInscricaoTurma;
use App\Enums\TipoInscricao;
use App\Models\AnoCatequetico;
use App\Models\AnoLetivo;
use App\Models\Catecismo;
use App\Models\Catequista;
use App\Models\Catequizando;
use App\Models\Centro;
use App\Models\Inscricao;
use App\Models\InscricaoTurma;
use App\Models\Paroquia;
use App\Models\Sacramento;
use App\Models\Turma;
use App\Models\TurmaCatequista;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Fase 2 da carga real da Catequese 2026/2027: le o staging.json gerado por
 * catequese:preparar-importacao e grava tudo numa unica transaccao.
 * Por omissao e um ensaio (dry-run): corre o mesmo codigo e faz rollback.
 * Idempotente: reexecutar nao duplica (chave = nome + paroquia [+ centro/ano]).
 */
class ImportarCatequese extends Command
{
    protected $signature = 'catequese:importar
        {--confirmar : Grava de facto (sem isto, e um ensaio com rollback)}
        {--paroquia=São Carlos Lwanga : Nome da paróquia}
        {--centro=Sede : Nome do centro onde ficam os dados}
        {--ano-letivo=2026/2027 : Nome do ano lectivo}
        {--inicio=2026-09-01 : Data de início do ano lectivo (AAAA-MM-DD)}
        {--fim=2027-07-31 : Data de fim do ano lectivo (AAAA-MM-DD)}';

    protected $description = 'Carrega no sistema os dados reais da Catequese 2026/2027 (a partir do staging.json)';

    /** @var array<string, int> */
    private array $contagem = [];

    public function handle(): int
    {
        $ficheiro = PrepararImportacaoCatequese::diretorio().'/staging.json';
        if (! is_file($ficheiro)) {
            $this->error('staging.json não encontrado — corra primeiro catequese:preparar-importacao.');

            return self::FAILURE;
        }
        $dados = json_decode(file_get_contents($ficheiro), true, 512, JSON_THROW_ON_ERROR);

        $confirmar = (bool) $this->option('confirmar');

        DB::beginTransaction();
        try {
            $this->carregar($dados);
            $confirmar ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Falhou, nada foi gravado: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->table(['Entidade', 'Criados', 'Já existiam'], collect($this->contagem)
            ->groupBy(fn ($v, $k) => explode(':', $k)[0])
            ->map(fn ($g, $nome) => [$nome, $this->contagem[$nome.':novo'] ?? 0, $this->contagem[$nome.':existente'] ?? 0])
            ->values()->all());
        $this->{$confirmar ? 'info' : 'warn'}($confirmar
            ? 'Importação concluída e gravada.'
            : 'ENSAIO — nada foi gravado. Repita com --confirmar para gravar.');

        return self::SUCCESS;
    }

    private function carregar(array $dados): void
    {
        $paroquia = Paroquia::where('nome', $this->option('paroquia'))->first()
            ?? throw new RuntimeException('Paróquia "'.$this->option('paroquia').'" não existe — corra: php artisan db:seed --class=ParoquiaRealSeeder');
        $centro = Centro::withoutGlobalScopes()->where('paroquia_id', $paroquia->id)->where('nome', $this->option('centro'))->first()
            ?? throw new RuntimeException('Centro "'.$this->option('centro').'" não existe na paróquia.');

        $anoLetivo = AnoLetivo::withoutGlobalScopes()->firstOrCreate(
            ['paroquia_id' => $paroquia->id, 'nome' => $this->option('ano-letivo')],
            ['data_inicio' => $this->option('inicio'), 'data_fim' => $this->option('fim'), 'status' => 'em_curso'],
        );
        $this->contar('Ano lectivo', $anoLetivo->wasRecentlyCreated);
        $inicio = $this->option('inicio');

        // catequistas
        $catequistas = [];
        foreach ($dados['catequistas'] as $c) {
            $m = Catequista::withoutGlobalScopes()->withTrashed()->firstOrCreate(
                ['paroquia_id' => $paroquia->id, 'nome_completo' => $c['nome']],
                ['centro_id' => $centro->id, 'ativo' => true],
            );
            $this->contar('Catequistas', $m->wasRecentlyCreated);
            $catequistas[$c['nome']] = $m;
        }

        // turmas
        $turmas = [];
        foreach ($dados['turmas'] as $t) {
            $ano = AnoCatequetico::where('ordem', $t['ano_catequetico_ordem'])->first()
                ?? throw new RuntimeException('Ano catequético de ordem '.$t['ano_catequetico_ordem'].' não existe — corra CatequeseSeeder.');
            $catecismo = $t['catecismo'] ? (Catecismo::where('nome', $t['catecismo'])->first()
                ?? throw new RuntimeException('Catecismo "'.$t['catecismo'].'" não existe — corra CatequeseSeeder.')) : null;

            $turma = Turma::withoutGlobalScopes()->withTrashed()->firstOrCreate(
                [
                    'paroquia_id' => $paroquia->id,
                    'centro_id' => $centro->id,
                    'ano_letivo_id' => $anoLetivo->id,
                    'ano_catequetico_id' => $ano->id,
                    'nome' => $t['nome'],
                ],
                [
                    'catecismo_id' => $catecismo?->id,
                    'publico_alvo' => $t['publico_alvo'],
                    'periodo' => $t['periodo'],
                    'hora_inicio' => $t['hora_inicio'] ?? '00:00',
                    'hora_fim' => $t['hora_fim'] ?? '00:00',
                    'tipo' => 'normal',
                    'status' => 'ativo',
                ],
            );
            $this->contar('Turmas', $turma->wasRecentlyCreated);

            $ids = Sacramento::whereIn('nome', $t['sacramentos'])->pluck('id')->all();
            if (count($ids) !== count($t['sacramentos'])) {
                throw new RuntimeException('Sacramento em falta para a turma '.$t['nome'].' — corra CatequeseSeeder.');
            }
            $turma->sacramentos()->syncWithoutDetaching($ids);

            foreach (['titular' => 'titular', 'auxiliar' => 'auxiliar'] as $campo => $papel) {
                if (! empty($t[$campo])) {
                    $v = TurmaCatequista::firstOrCreate(
                        ['turma_id' => $turma->id, 'catequista_id' => $catequistas[$t[$campo]]->id, 'data_inicio' => $inicio],
                        ['papel' => $papel],
                    );
                    $this->contar('Atribuições catequista↔turma', $v->wasRecentlyCreated);
                }
            }
            $turmas[$t['indice']] = ['modelo' => $turma, 'sacramentos' => $ids, 'ano' => $ano];
        }

        // catequizandos + inscrições + colocações
        foreach ($dados['catequizandos'] as $c) {
            $cat = Catequizando::withoutGlobalScopes()->withTrashed()->firstOrCreate(
                ['paroquia_id' => $paroquia->id, 'nome_completo' => $c['nome']],
                [
                    'centro_id' => $centro->id,
                    'sexo' => $c['sexo'],
                    'data_nascimento' => $c['ano_nascimento'] ? $c['ano_nascimento'].'-07-01' : null,
                    'data_nascimento_aproximada' => $c['ano_nascimento'] !== null,
                    'telefone' => $c['telefone'],
                    'pais_nascimento' => 'Angola',
                    'status' => 'ativo',
                ],
            );
            $this->contar('Catequizandos', $cat->wasRecentlyCreated);
            if ($cat->wasRecentlyCreated) {
                $cat->centros()->attach($centro->id, ['data_inicio' => $inicio]);
            }

            $turma = $c['turma_indice'] !== null ? $turmas[$c['turma_indice']] : null;

            $insc = Inscricao::withoutGlobalScopes()->withTrashed()
                ->where('catequizando_id', $cat->id)->where('ano_letivo_id', $anoLetivo->id)->first();
            $nova = $insc === null;
            if ($nova) {
                $insc = Inscricao::create([
                    'paroquia_id' => $paroquia->id,
                    'centro_id' => $centro->id,
                    'catequizando_id' => $cat->id,
                    'ano_letivo_id' => $anoLetivo->id,
                    'ano_catequetico_id' => $turma['ano']->id ?? null,
                    'tipo' => TipoInscricao::from($c['tipo']),
                    'data_atendimento' => $inicio,
                    'estado' => EstadoInscricao::Inscrito,
                    'observacoes' => $c['observacoes'],
                ]);
                if ($turma) {
                    $insc->sacramentos()->sync($turma['sacramentos']);
                }
            }
            $this->contar('Inscrições', $nova);

            if ($turma && ! $insc->turmaAtiva()->exists()) {
                InscricaoTurma::create([
                    'inscricao_id' => $insc->id,
                    'turma_id' => $turma['modelo']->id,
                    'status' => EstadoInscricaoTurma::Ativo,
                    'data_inicio' => $inicio,
                ]);
                $this->contar('Colocações em turma', true);
            }
        }
    }

    private function contar(string $entidade, bool $novo): void
    {
        $k = $entidade.($novo ? ':novo' : ':existente');
        $this->contagem[$k] = ($this->contagem[$k] ?? 0) + 1;
    }
}
