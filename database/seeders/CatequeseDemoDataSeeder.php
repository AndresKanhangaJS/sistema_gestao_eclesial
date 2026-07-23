<?php

namespace Database\Seeders;

use App\Models\AnoCatequetico;
use App\Models\AnoLetivo;
use App\Models\Catequista;
use App\Models\Catequizando;
use App\Models\Centro;
use App\Models\Fiel;
use App\Models\Inscricao;
use App\Models\Paroquia;
use App\Models\Sacramento;
use App\Models\Turma;
use Illuminate\Database\Seeder;

/**
 * Dados de demonstracao do modulo Catequese (ano lectivo, turmas,
 * catequistas, catequizandos e inscricoes) para as listas/Resources terem
 * conteudo real antes dos testes manuais — mesmo espirito do DemoDataSeeder
 * financeiro, reutiliza a Paroquia/Centro de Teste do TestDataSeeder.
 * Idempotente: pode correr varias vezes sem duplicar nada (chaves naturais
 * fixas em vez de nomes aleatorios).
 */
class CatequeseDemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $paroquia = Paroquia::where('nome', 'Paroquia de Teste')->firstOrFail();
        $centroPrincipal = Centro::withoutGlobalScopes()
            ->where('paroquia_id', $paroquia->id)->where('nome', 'Centro de Teste')->firstOrFail();
        $centroSecundario = Centro::withoutGlobalScopes()
            ->where('paroquia_id', $paroquia->id)->where('nome', 'Centro Santo António')->firstOrFail();

        $anoLetivo = AnoLetivo::withoutGlobalScopes()->firstOrCreate(
            ['paroquia_id' => $paroquia->id, 'nome' => '2026/2027'],
            ['data_inicio' => '2026-09-01', 'data_fim' => '2027-07-31', 'status' => 'em_curso']
        );

        $catequistas = $this->seedCatequistas($paroquia, $centroPrincipal, $centroSecundario);
        $turmas = $this->seedTurmas($paroquia, $centroPrincipal, $centroSecundario, $anoLetivo, $catequistas);
        $this->seedCatequizandosEInscricoes($paroquia, $anoLetivo, $turmas, $catequistas);
        $this->seedExemploTrocaDeTurma($turmas);
    }

    /** @return array<string, Catequista> indexado por nome */
    private function seedCatequistas(Paroquia $paroquia, Centro $centroPrincipal, Centro $centroSecundario): array
    {
        // Catequista com vinculo a um Fiel ja existente (DemoDataSeeder financeiro),
        // para demonstrar o campo opcional fiel_id.
        $fielAssiduo = Fiel::withoutGlobalScopes()
            ->where('paroquia_id', $paroquia->id)->where('codigo_dizimista', 'F0001')->first();

        $dados = [
            'Ana Maria Kiala' => ['centro_id' => $centroPrincipal->id, 'telefone' => '923000001', 'email' => 'ana.kiala@sge.local', 'fiel_id' => null],
            'Manuel dos Santos' => ['centro_id' => $centroPrincipal->id, 'telefone' => '923000002', 'email' => 'manuel.santos@sge.local', 'fiel_id' => null],
            'Isabel Fernandes' => ['centro_id' => $centroPrincipal->id, 'telefone' => '923000003', 'email' => 'isabel.fernandes@sge.local', 'fiel_id' => $fielAssiduo?->id],
            'Domingos Pedro' => ['centro_id' => $centroSecundario->id, 'telefone' => '923000004', 'email' => 'domingos.pedro@sge.local', 'fiel_id' => null],
            'Teresa Neto' => ['centro_id' => null, 'telefone' => '923000005', 'email' => 'teresa.neto@sge.local', 'fiel_id' => null],
        ];

        $catequistas = [];
        foreach ($dados as $nome => $d) {
            $catequistas[$nome] = Catequista::withoutGlobalScopes()->firstOrCreate(
                ['paroquia_id' => $paroquia->id, 'nome_completo' => $nome],
                [
                    'centro_id' => $d['centro_id'],
                    'fiel_id' => $d['fiel_id'],
                    'telefone' => $d['telefone'],
                    'email' => $d['email'],
                    'ativo' => true,
                ]
            );
        }

        return $catequistas;
    }

    /** @return array<string, Turma> indexado por etiqueta interna (A/B/C/D) */
    private function seedTurmas(
        Paroquia $paroquia,
        Centro $centroPrincipal,
        Centro $centroSecundario,
        AnoLetivo $anoLetivo,
        array $catequistas
    ): array {
        $ano1 = AnoCatequetico::where('ordem', 1)->firstOrFail();
        $ano2 = AnoCatequetico::where('ordem', 2)->firstOrFail();
        $baptismo = Sacramento::where('nome', 'Baptismo')->firstOrFail();
        $comunhao = Sacramento::where('nome', 'Comunhão')->firstOrFail();
        $crisma = Sacramento::where('nome', 'Crisma')->firstOrFail();

        $definicoes = [
            'A' => [
                'centro' => $centroPrincipal, 'ano_catequetico' => $ano1, 'publico_alvo' => 'criancas',
                'periodo' => 'manha', 'hora_inicio' => '09:00', 'hora_fim' => '10:30', 'tipo' => 'normal',
                'sacramentos' => [$baptismo, $comunhao],
                'catequistas' => [['nome' => 'Ana Maria Kiala', 'papel' => 'titular'], ['nome' => 'Manuel dos Santos', 'papel' => 'auxiliar']],
            ],
            'B' => [
                'centro' => $centroPrincipal, 'ano_catequetico' => $ano1, 'publico_alvo' => 'criancas',
                'periodo' => 'tarde', 'hora_inicio' => '14:00', 'hora_fim' => '15:30', 'tipo' => 'normal',
                'sacramentos' => [$comunhao],
                'catequistas' => [['nome' => 'Manuel dos Santos', 'papel' => 'titular']],
            ],
            'C' => [
                'centro' => $centroPrincipal, 'ano_catequetico' => $ano2, 'publico_alvo' => 'adolescentes_jovens',
                'periodo' => 'noite', 'hora_inicio' => '18:30', 'hora_fim' => '20:00', 'tipo' => 'intensiva',
                'sacramentos' => [$crisma],
                'catequistas' => [['nome' => 'Isabel Fernandes', 'papel' => 'titular'], ['nome' => 'Teresa Neto', 'papel' => 'auxiliar']],
            ],
            'D' => [
                'centro' => $centroSecundario, 'ano_catequetico' => $ano1, 'publico_alvo' => 'criancas',
                'periodo' => 'manha', 'hora_inicio' => '09:00', 'hora_fim' => '10:30', 'tipo' => 'normal',
                'sacramentos' => [$baptismo],
                'catequistas' => [['nome' => 'Domingos Pedro', 'papel' => 'titular']],
            ],
        ];

        $turmas = [];
        foreach ($definicoes as $letra => $d) {
            $turma = Turma::withoutGlobalScopes()->firstOrCreate(
                [
                    'centro_id' => $d['centro']->id,
                    'ano_letivo_id' => $anoLetivo->id,
                    'ano_catequetico_id' => $d['ano_catequetico']->id,
                    'periodo' => $d['periodo'],
                ],
                [
                    'paroquia_id' => $paroquia->id,
                    'publico_alvo' => $d['publico_alvo'],
                    'hora_inicio' => $d['hora_inicio'],
                    'hora_fim' => $d['hora_fim'],
                    'tipo' => $d['tipo'],
                    'status' => 'ativo',
                ]
            );

            foreach ($d['sacramentos'] as $sacramento) {
                $turma->sacramentos()->syncWithoutDetaching([$sacramento->id]);
            }

            foreach ($d['catequistas'] as $c) {
                $jaAtribuido = $turma->catequistas()->wherePivot('catequista_id', $catequistas[$c['nome']]->id)->exists();

                if (! $jaAtribuido) {
                    $turma->catequistas()->attach($catequistas[$c['nome']]->id, [
                        'papel' => $c['papel'],
                        'data_inicio' => $anoLetivo->data_inicio,
                    ]);
                }
            }

            $turmas[$letra] = $turma;
        }

        return $turmas;
    }

    private function seedCatequizandosEInscricoes(Paroquia $paroquia, AnoLetivo $anoLetivo, array $turmas, array $catequistas): void
    {
        // Nomes fixos (nao faker) para a criacao ser idempotente por nome_completo.
        $porTurma = [
            'A' => [ // 1º Ano, crianças, Baptismo+Comunhão
                ['nome' => 'Divaldo João Bumba', 'sexo' => 'M', 'idade' => 8],
                ['nome' => 'Márcia Suzana Kiluanje', 'sexo' => 'F', 'idade' => 9],
                ['nome' => 'Edmilson Costa Neto', 'sexo' => 'M', 'idade' => 8],
                ['nome' => 'Benilde Isabel Domingos', 'sexo' => 'F', 'idade' => 9],
                ['nome' => 'Rufino Sebastião Cabinda', 'sexo' => 'M', 'idade' => 8],
                ['nome' => 'Alzira Manuela Tati', 'sexo' => 'F', 'idade' => 9],
            ],
            'B' => [ // 1º Ano, crianças, só Comunhão
                ['nome' => 'Osvaldo Miguel Panzo', 'sexo' => 'M', 'idade' => 9],
                ['nome' => 'Cátia Isabel Muanza', 'sexo' => 'F', 'idade' => 8],
                ['nome' => 'Elias Domingos Sanjo', 'sexo' => 'M', 'idade' => 9],
                ['nome' => 'Nádia Cristina Bumba', 'sexo' => 'F', 'idade' => 8],
                ['nome' => 'Faustino Alberto Neto', 'sexo' => 'M', 'idade' => 9],
            ],
            'C' => [ // 2º Ano, adolescentes/jovens, Crisma
                ['nome' => 'Wilson Manuel Kiesse', 'sexo' => 'M', 'idade' => 15],
                ['nome' => 'Larissa Fernandes Sousa', 'sexo' => 'F', 'idade' => 14],
                ['nome' => 'Décio Armando Bento', 'sexo' => 'M', 'idade' => 16],
                ['nome' => 'Ivone Marta Cangovi', 'sexo' => 'F', 'idade' => 15],
                ['nome' => 'Rui Adilson Fortunato', 'sexo' => 'M', 'idade' => 14],
                ['nome' => 'Sandra Beatriz Lussati', 'sexo' => 'F', 'idade' => 16],
            ],
            'D' => [ // 1º Ano, crianças, Centro Santo António, Baptismo
                ['nome' => 'Anacleto José Nzuzi', 'sexo' => 'M', 'idade' => 8],
                ['nome' => 'Filomena Rosa Bunga', 'sexo' => 'F', 'idade' => 9],
                ['nome' => 'Gerson Wilker Sanjo', 'sexo' => 'M', 'idade' => 8],
                ['nome' => 'Adelaide Nsimba Vunge', 'sexo' => 'F', 'idade' => 9],
                ['nome' => 'Custódio Baptista Manuel', 'sexo' => 'M', 'idade' => 8],
            ],
        ];

        $paisPool = [
            ['pai' => 'António Bumba', 'mae' => 'Josefina Kiluanje'],
            ['pai' => 'Sebastião Panzo', 'mae' => 'Rosa Muanza'],
            ['pai' => 'Fernando Kiesse', 'mae' => 'Beatriz Sousa'],
            ['pai' => 'Miguel Nzuzi', 'mae' => 'Adelaide Bunga'],
        ];
        $profissoes = ['Comerciante', 'Professor(a)', 'Enfermeiro(a)', 'Motorista', 'Funcionário Público', 'Doméstica'];
        // inscrito é o estado por omissao — os restantes aparecem em 1 caso cada, so para as listas mostrarem variedade
        $estadosVariados = [4 => 'aprovado', 9 => 'aprovado', 14 => 'reprovado', 19 => 'desistente'];

        $indiceGlobal = 0;

        foreach ($porTurma as $letra => $catequizandos) {
            $turma = $turmas[$letra];
            $catequistaAtendimento = $turma->catequistas()->wherePivot('papel', 'titular')->first();

            foreach ($catequizandos as $dados) {
                $pais = $paisPool[$indiceGlobal % count($paisPool)];

                $catequizando = Catequizando::withoutGlobalScopes()->firstOrCreate(
                    ['paroquia_id' => $paroquia->id, 'nome_completo' => $dados['nome']],
                    [
                        'centro_id' => $turma->centro_id,
                        'nome_pai' => $pais['pai'],
                        'nome_mae' => $pais['mae'],
                        'profissao' => $profissoes[$indiceGlobal % count($profissoes)],
                        'municipio_nascimento' => 'Luanda',
                        'provincia_nascimento' => 'Luanda',
                        'data_nascimento' => now()->subYears($dados['idade'])->subDays(($indiceGlobal * 17) % 300),
                        'sexo' => $dados['sexo'],
                        'residencia' => 'Bairro '.['Prenda', 'Rangel', 'Cazenga', 'Maianga', 'Sambizanga'][$indiceGlobal % 5],
                        'telefone' => '92'.str_pad((string) (4000000 + $indiceGlobal), 7, '0', STR_PAD_LEFT),
                        'status' => 'ativo',
                    ]
                );

                // Historico inicial de centro — mesmo que CreateCatequizando::afterCreate() faz no Filament.
                $catequizando->centros()->syncWithoutDetaching([
                    $turma->centro_id => ['data_inicio' => $anoLetivo->data_inicio],
                ]);

                $estado = $estadosVariados[$indiceGlobal] ?? 'inscrito';

                $inscricao = Inscricao::withoutGlobalScopes()->firstOrCreate(
                    ['paroquia_id' => $paroquia->id, 'catequizando_id' => $catequizando->id, 'ano_letivo_id' => $anoLetivo->id],
                    [
                        'centro_id' => $turma->centro_id,
                        'ano_catequetico_id' => $turma->ano_catequetico_id,
                        'catequista_id' => $catequistaAtendimento?->id,
                        'tipo' => 'nova',
                        // DatabaseSeeder usa WithoutModelEvents (desliga o hook
                        // Inscricao::booted() 'creating' durante o seed, mesmo motivo do
                        // codigo_dizimista manual em DemoDataSeeder) — chamamos o gerador
                        // directamente em vez de depender do evento.
                        'numero_ficha' => Inscricao::proximoNumeroFicha($paroquia->id, $anoLetivo->id),
                        'data_atendimento' => $anoLetivo->data_inicio,
                        'estado' => $estado,
                    ]
                );

                $inscricao->sacramentos()->syncWithoutDetaching($turma->sacramentos()->pluck('sacramentos.id'));

                $jaColocado = $inscricao->inscricaoTurmas()->where('turma_id', $turma->id)->exists();

                if (! $jaColocado) {
                    $inscricao->turmas()->attach($turma->id, [
                        'status' => 'ativo',
                        'data_inicio' => $anoLetivo->data_inicio,
                    ]);
                }

                $indiceGlobal++;
            }
        }
    }

    /**
     * Demonstra o fluxo de troca de turma (docs/modulos/catequese.md secc.
     * 7.1): fecha a colocacao activa na Turma A e abre uma nova na Turma B,
     * sem tocar em inscricoes nem turmas — so para o RelationManager
     * "Colocação em Turma" ja mostrar historico ao inves de uma linha unica.
     */
    private function seedExemploTrocaDeTurma(array $turmas): void
    {
        $catequizando = Catequizando::withoutGlobalScopes()->where('nome_completo', 'Divaldo João Bumba')->first();

        if (! $catequizando) {
            return;
        }

        $inscricao = $catequizando->inscricoes()->first();
        $activa = $inscricao?->turmaAtiva;

        if (! $activa || $activa->turma_id !== $turmas['A']->id) {
            return;
        }

        $activa->update([
            'status' => 'transferido',
            'data_fim' => now()->subMonths(2),
            'motivo' => 'Pedido do encarregado — mudança de horário.',
        ]);

        $inscricao->turmas()->attach($turmas['B']->id, [
            'status' => 'ativo',
            'data_inicio' => now()->subMonths(2)->addDay(),
            'motivo' => 'Pedido do encarregado — mudança de horário.',
        ]);

        // Turma A é Baptismo+Comunhão, Turma B é só Comunhão — sacramentos
        // diferentes, por isso a ficha tem de ser actualizada para continuar
        // consistente com a regra de conjunto exacto (secc. 12).
        $inscricao->sacramentos()->sync($turmas['B']->sacramentos()->pluck('sacramentos.id'));
    }
}
