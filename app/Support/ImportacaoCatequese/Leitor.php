<?php

namespace App\Support\ImportacaoCatequese;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Le a "Lista Nominal" (DOCX) e a "Base de Dados" (XLSX) da Catequese
 * 2026/2027 e devolve um dataset normalizado (turmas, catequistas,
 * catequizandos, colocacoes) mais uma lista de problemas para revisao.
 *
 * Sem dependencias externas: DOCX e XLSX sao zips de XML. O XLSX e lido a
 * partir dos valores em cache (<v>) — folhas com formulas (#N/A) nao precisam
 * de motor de calculo.
 *
 * Ver docs/modulos/catequese-importacao-2026-2027.md.
 */
class Leitor
{
    private const NS_W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const NS_S = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const NS_R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const FOLHA_MESTRA = 'INSCRIÇÃO E CONFIRMAÇÃO 2026-27';

    public const ANO_REFERENCIA = 2026;

    /** @var list<array{tipo: string, detalhe: string}> */
    private array $problemas = [];

    /**
     * @return array{turmas: list<array>, catequistas: list<array>, catequizandos: list<array>, problemas: list<array>}
     */
    public function ler(string $docx, string $xlsx): array
    {
        $turmasBrutas = $this->lerDocx($docx);
        $mestra = $this->lerFolhaMestra($xlsx);

        [$catequistas, $mapaCatequistas] = $this->consolidarCatequistas($turmasBrutas);
        $turmas = $this->montarTurmas($turmasBrutas, $mapaCatequistas);
        $catequizandos = $this->consolidarCatequizandos($turmasBrutas, $turmas, $mestra);

        return [
            'turmas' => $turmas,
            'catequistas' => $catequistas,
            'catequizandos' => $catequizandos,
            'problemas' => $this->problemas,
        ];
    }

    // ------------------------------------------------------------------ DOCX

    /** @return list<array> */
    private function lerDocx(string $caminho): array
    {
        $zip = new ZipArchive;
        if ($zip->open($caminho) !== true) {
            throw new RuntimeException("Nao foi possivel abrir o DOCX: {$caminho}");
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $dom = new DOMDocument;
        $dom->loadXML($xml);
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', self::NS_W);

        $turmas = [];
        foreach ($xp->query('//w:body/w:tbl') as $tabela) {
            $linhas = [];
            foreach ($xp->query('w:tr', $tabela) as $tr) {
                $celulas = [];
                foreach ($xp->query('w:tc', $tr) as $tc) {
                    $partes = [];
                    foreach ($xp->query('w:p', $tc) as $p) {
                        $t = trim($this->textoNo($xp, $p));
                        if ($t !== '') {
                            $partes[] = $t;
                        }
                    }
                    $celulas[] = trim(implode(' ', $partes));
                }
                $linhas[] = $celulas;
            }

            $turma = $this->interpretarTabelaTurma($linhas);
            if ($turma !== null) {
                $turmas[] = $turma;
            }
        }

        return $turmas;
    }

    private function textoNo(DOMXPath $xp, DOMElement $no): string
    {
        $s = '';
        foreach ($xp->query('.//w:t', $no) as $t) {
            $s .= $t->textContent;
        }

        return $s;
    }

    /** @param list<list<string>> $linhas */
    private function interpretarTabelaTurma(array $linhas): ?array
    {
        $l1 = $linhas[0] ?? [];
        if (! isset($l1[0]) || ! preg_match('/^Catequista:\s*(.*)$/u', $l1[0], $m1)) {
            return null; // tabela de titulo de seccao ("1º BAPTISMO E COMUNHAO"), nao uma turma
        }
        $catequista = trim($m1[1]);
        $catecismo = isset($l1[1]) ? trim(preg_replace('/^Catecismo:\s*/u', '', $l1[1])) : '';

        $l2 = $linhas[1] ?? [];
        $aux = trim(preg_replace('/^Cat\.Aux:\s*/u', '', $l2[0] ?? ''));
        $horario = trim(preg_replace('/^Hor.rio:\s*/u', '', $l2[1] ?? ''));
        $ano = trim(preg_replace('/^Ano:\s*/u', '', $l2[2] ?? ''));
        $numero = trim($l2[3] ?? '');

        $alunos = [];
        foreach (array_slice($linhas, 3) as $linha) {
            $nome = trim($linha[1] ?? '');
            if ($nome === '') {
                continue;
            }
            $alunos[] = [
                'nome' => $this->limparNome($nome),
                'idade' => trim($linha[2] ?? ''),
                'genero' => strtoupper(trim($linha[3] ?? '')),
            ];
        }

        return [
            'catequista' => $catequista,
            'auxiliar' => $aux,
            'catecismo' => $catecismo,
            'horario' => $horario,
            'ano' => $ano,
            'numero' => $numero,
            'alunos' => $alunos,
        ];
    }

    // ------------------------------------------------------------------ XLSX

    /** @return list<array> */
    private function lerFolhaMestra(string $caminho): array
    {
        $zip = new ZipArchive;
        if ($zip->open($caminho) !== true) {
            throw new RuntimeException("Nao foi possivel abrir o XLSX: {$caminho}");
        }

        $partilhadas = [];
        if (($ss = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            $d = new DOMDocument;
            $d->loadXML($ss);
            $x = new DOMXPath($d);
            $x->registerNamespace('s', self::NS_S);
            foreach ($x->query('//s:si') as $si) {
                $t = '';
                foreach ($x->query('.//s:t', $si) as $n) {
                    $t .= $n->textContent;
                }
                $partilhadas[] = $t;
            }
        }

        $wb = new DOMDocument;
        $wb->loadXML($zip->getFromName('xl/workbook.xml'));
        $xw = new DOMXPath($wb);
        $xw->registerNamespace('s', self::NS_S);
        $rels = new DOMDocument;
        $rels->loadXML($zip->getFromName('xl/_rels/workbook.xml.rels'));
        $alvos = [];
        foreach ($rels->documentElement->childNodes as $r) {
            if ($r instanceof DOMElement) {
                $alvos[$r->getAttribute('Id')] = ltrim($r->getAttribute('Target'), '/');
            }
        }

        $caminhoFolha = null;
        foreach ($xw->query('//s:sheets/s:sheet') as $sheet) {
            if (trim($sheet->getAttribute('name')) === self::FOLHA_MESTRA) {
                $alvo = $alvos[$sheet->getAttributeNS(self::NS_R, 'id')];
                $caminhoFolha = str_starts_with($alvo, 'xl/') ? $alvo : 'xl/'.$alvo;
            }
        }
        if ($caminhoFolha === null) {
            throw new RuntimeException('Folha "'.self::FOLHA_MESTRA.'" nao encontrada no XLSX.');
        }

        $fd = new DOMDocument;
        $fd->loadXML($zip->getFromName($caminhoFolha));
        $zip->close();
        $xf = new DOMXPath($fd);
        $xf->registerNamespace('s', self::NS_S);

        $linhas = [];
        foreach ($xf->query('//s:sheetData/s:row') as $row) {
            $vals = [];
            foreach ($xf->query('s:c', $row) as $c) {
                $ref = $c->getAttribute('r');
                $col = $this->indiceColuna($ref);
                $t = $c->getAttribute('t');
                $vNode = $xf->query('s:v', $c)->item(0);
                if ($t === 'inlineStr') {
                    $val = '';
                    foreach ($xf->query('.//s:t', $c) as $n) {
                        $val .= $n->textContent;
                    }
                } elseif ($vNode === null) {
                    continue;
                } elseif ($t === 's') {
                    $val = $partilhadas[(int) $vNode->textContent] ?? '';
                } elseif ($t === 'e') {
                    $val = ''; // #N/A e afins
                } else {
                    $val = $vNode->textContent;
                    if (is_numeric($val) && stripos($val, 'e') !== false) {
                        $val = sprintf('%.0f', (float) $val);
                    }
                }
                $vals[$col] = trim($val);
            }
            if ($vals) {
                $linhas[(int) $row->getAttribute('r')] = $vals;
            }
        }

        // Cabecalho: linha onde a coluna B e "Nome"
        $registos = [];
        $dentro = false;
        foreach ($linhas as $linha) {
            if (! $dentro) {
                if (mb_strtolower($linha[2] ?? '') === 'nome') {
                    $dentro = true;
                }

                continue;
            }
            $nome = $this->limparNome($linha[2] ?? '');
            if ($nome === '' || ! ctype_digit($linha[1] ?? '')) {
                continue;
            }
            $registos[] = [
                'nome' => $nome,
                'servico' => $linha[3] ?? '',
                'ano_nascimento' => $linha[4] ?? '',
                'situacao' => $linha[6] ?? '',
                'telefone' => $linha[7] ?? '',
                'observacao' => $linha[8] ?? '',
            ];
        }

        return $registos;
    }

    private function indiceColuna(string $ref): int
    {
        preg_match('/^[A-Z]+/', $ref, $m);
        $n = 0;
        foreach (str_split($m[0]) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n;
    }

    // ------------------------------------------------------------ consolidar

    /**
     * @return array{0: list<array>, 1: array<string, string>} catequistas unicos e mapa nome-bruto => nome-canonico
     */
    private function consolidarCatequistas(array $turmasBrutas): array
    {
        $canonicos = []; // chave normalizada => nome
        $mapa = [];

        foreach ($turmasBrutas as $t) {
            foreach (['catequista', 'auxiliar'] as $papel) {
                $bruto = $t[$papel];
                if ($bruto === '') {
                    continue;
                }
                $nome = $this->nomeCatequista($bruto);
                if ($nome !== trim($bruto)) {
                    $this->problema('catequista_nota', "\"{$bruto}\" registado como \"{$nome}\" (nota entre parenteses descartada).");
                }
                $chave = $this->normalizar($nome);
                $existente = $this->procurarSemelhante($chave, array_keys($canonicos), 1);
                if ($existente !== null && $existente !== $chave) {
                    $this->problema('catequista_fundido', "\"{$nome}\" tratado como o mesmo catequista que \"{$canonicos[$existente]}\".");
                    $chave = $existente;
                }
                $canonicos[$chave] ??= $nome;
                $mapa[$bruto] = $canonicos[$chave];
            }
        }

        return [array_map(fn ($n) => ['nome' => $n], array_values($canonicos)), $mapa];
    }

    private function nomeCatequista(string $bruto): string
    {
        $nome = trim(preg_replace('/\s*\(.*?\)\s*/u', ' ', $bruto));

        return $this->limparNome($nome);
    }

    /** @return list<array> */
    private function montarTurmas(array $turmasBrutas, array $mapaCatequistas): array
    {
        $contagemPorAno = [];
        foreach ($turmasBrutas as $t) {
            $k = $t['ano'].'|'.$t['numero'];
            $contagemPorAno[$k] = ($contagemPorAno[$k] ?? 0) + 1;
        }

        $vistos = [];
        $turmas = [];
        foreach ($turmasBrutas as $indice => $t) {
            $k = $t['ano'].'|'.$t['numero'];
            $vistos[$k] = ($vistos[$k] ?? 0) + 1;
            $nome = 'Turma '.$t['numero'];
            if ($contagemPorAno[$k] > 1) {
                $nome .= '-'.chr(64 + $vistos[$k]);
            }

            [$anoOrdem, $sacramentos, $anoRotulo] = $this->mapearAno($t['ano']);
            [$ini, $fim] = $this->mapearHorario($t['horario']);
            $catecismo = $this->mapearCatecismo($t['catecismo']);

            $turmas[] = [
                'indice' => $indice,
                'nome' => $nome,
                'ano_catequetico_ordem' => $anoOrdem,
                'sacramentos' => $sacramentos,
                'ano_rotulo' => $anoRotulo,
                'catecismo' => $catecismo,
                'hora_inicio' => $ini,
                'hora_fim' => $fim,
                'periodo' => $ini === null ? 'manha' : $this->periodo((int) substr($ini, 0, 2)),
                'publico_alvo' => $this->publicoAlvo($catecismo, $t['alunos']),
                'titular' => $t['catequista'] !== '' ? ($mapaCatequistas[$t['catequista']] ?? null) : null,
                'auxiliar' => $t['auxiliar'] !== '' ? ($mapaCatequistas[$t['auxiliar']] ?? null) : null,
                'alunos' => $t['alunos'],
            ];
        }

        return $turmas;
    }

    /** @return array{0: int, 1: list<string>, 2: string} */
    private function mapearAno(string $ano): array
    {
        $a = $this->normalizar($ano);
        if (preg_match('/^(\d)\s*(?:o|a)?\s*b\s*c$/', $a, $m)) {
            return [(int) $m[1], ['Baptismo', 'Comunhão'], $ano];
        }
        if (preg_match('/^(\d)\s*(?:o|a)?\s*c$/', $a, $m)) {
            return [(int) $m[1], ['Comunhão'], $ano];
        }
        if (str_contains($a, '1') && str_contains($a, 'crisma')) {
            return [5, ['Crisma'], $ano];
        }
        if (str_contains($a, '2') && str_contains($a, 'crisma')) {
            return [6, ['Crisma'], $ano];
        }
        if (str_contains($a, 'inicia')) {
            return [0, [], $ano];
        }
        if (str_contains($a, 'perse') || str_contains($a, 'preser')) {
            return [7, ['Perseverança'], $ano];
        }

        throw new RuntimeException("Ano catequetico desconhecido no DOCX: \"{$ano}\"");
    }

    /** @return array{0: ?string, 1: ?string} */
    private function mapearHorario(string $h): array
    {
        if (preg_match('/(\d{1,2})\s*H\s*(\d{2})\s*-\s*(\d{1,2})\s*H\s*(\d{2})/i', $h, $m)) {
            return [sprintf('%02d:%s', $m[1], $m[2]), sprintf('%02d:%s', $m[3], $m[4])];
        }
        $this->problema('turma_horario', "Horario nao reconhecido: \"{$h}\".");

        return [null, null];
    }

    private function periodo(int $hora): string
    {
        return $hora < 12 ? 'manha' : ($hora < 18 ? 'tarde' : 'noite');
    }

    private function mapearCatecismo(string $bruto): ?string
    {
        $c = $this->normalizar($bruto);
        $mapa = [
            'deus chamanos' => 'Deus Chama-nos',
            'deus amanos' => 'Deus Ama-nos',
            'creio em jesus salvador' => 'Creio em Jesus Salvador',
            'jesus e a vidaeleitos' => 'Jesus é a Vida/Eleitos',
            'a minha primeira comunhao' => 'A Minha Primeira Comunhão',
            'discipulo de cristo' => 'Discípulo de Cristo',
            'apostolo de cristo' => 'Apóstolo de Cristo',
            'jesus 12' => 'Jesus 1/2',
            'minha historia divina' => 'Minha História Divina',
        ];
        if (str_contains($c, 'adulto')) {
            return 'Catecismo de Adulto';
        }
        if (isset($mapa[$c])) {
            return $mapa[$c];
        }
        $this->problema('turma_catecismo', "Catecismo nao reconhecido: \"{$bruto}\".");

        return null;
    }

    /** Adultos pelo catecismo; senao pela idade mediana (<=12, 13-17, >=18). */
    private function publicoAlvo(?string $catecismo, array $alunos): string
    {
        if ($catecismo === 'Catecismo de Adulto') {
            return 'adultos';
        }
        $idades = [];
        foreach ($alunos as $a) {
            $idade = $this->idadeDe($a['idade']);
            if ($idade !== null) {
                $idades[] = $idade;
            }
        }
        if (! $idades) {
            return 'criancas';
        }
        sort($idades);
        $mediana = $idades[intdiv(count($idades), 2)];

        return $mediana <= 12 ? 'criancas' : ($mediana <= 17 ? 'pre_adolescentes' : 'adolescentes_jovens');
    }

    /** O DOCX por vezes traz o ano de nascimento (ex. 2019) em vez da idade. */
    private function idadeDe(string $bruto): ?int
    {
        if (! ctype_digit($bruto)) {
            return null;
        }
        $n = (int) $bruto;
        if ($n >= 1900 && $n <= self::ANO_REFERENCIA) {
            return self::ANO_REFERENCIA - $n;
        }

        return $n >= 1 && $n <= 99 ? $n : null;
    }

    /**
     * Junta XLSX (lista mestra) e DOCX (colocacao). Nome do XLSX prevalece
     * (e a lista mestra); DOCX fornece genero e turma.
     *
     * @return list<array>
     */
    private function consolidarCatequizandos(array $turmasBrutas, array $turmas, array $mestra): array
    {
        // 1. colocacoes do DOCX, por nome normalizado (primeira ocorrencia vence)
        $colocacoes = []; // chave => [turmaIndice, aluno]
        foreach ($turmas as $t) {
            foreach ($t['alunos'] as $aluno) {
                $chave = $this->normalizar($aluno['nome']);
                if (isset($colocacoes[$chave])) {
                    $this->problema('aluno_repetido', "\"{$aluno['nome']}\" consta em duas turmas ({$turmas[$colocacoes[$chave]['turma']]['nome']} e {$t['nome']}); mantida a primeira.");

                    continue;
                }
                $colocacoes[$chave] = ['turma' => $t['indice'], 'aluno' => $aluno];
            }
        }

        // 2. emparelhar XLSX <-> DOCX (exacto, depois por grafia proxima)
        $porChave = [];
        foreach ($mestra as $i => $r) {
            $porChave[$this->normalizar($r['nome'])] = $i;
        }
        $ligacao = []; // chave docx => chave xlsx
        $docxSemPar = [];
        foreach (array_keys($colocacoes) as $k) {
            if (isset($porChave[$k])) {
                $ligacao[$k] = $k;
            } else {
                $docxSemPar[] = $k;
            }
        }
        $xlsxLivres = array_values(array_diff(array_keys($porChave), array_values($ligacao)));
        foreach ($docxSemPar as $k) {
            $par = $this->procurarSemelhante($k, $xlsxLivres, 2, 12);
            if ($par !== null) {
                $ligacao[$k] = $par;
                $xlsxLivres = array_values(array_diff($xlsxLivres, [$par]));
                $this->problema('nome_emparelhado', "DOCX \"{$colocacoes[$k]['aluno']['nome']}\" emparelhado com XLSX \"{$mestra[$porChave[$par]]['nome']}\" por grafia proxima — confirmar.");
            }
        }
        $docxParaXlsx = $ligacao;
        $xlsxParaDocx = array_flip($ligacao);

        // 3. construir registos: ordem do XLSX primeiro, depois so-DOCX
        $out = [];
        foreach ($mestra as $r) {
            $chaveX = $this->normalizar($r['nome']);
            $chaveD = $xlsxParaDocx[$chaveX] ?? null;
            $col = $chaveD !== null ? $colocacoes[$chaveD] : null;
            $out[] = $this->registo($r['nome'], $r, $col);
        }
        foreach ($colocacoes as $k => $col) {
            if (! isset($docxParaXlsx[$k])) {
                $this->problema('so_no_docx', "\"{$col['aluno']['nome']}\" so consta no DOCX (turma {$turmas[$col['turma']]['nome']}); criado como inscricao nova com dados minimos.");
                $out[] = $this->registo($col['aluno']['nome'], null, $col);
            }
        }

        return $out;
    }

    private function registo(string $nome, ?array $x, ?array $col): array
    {
        $aluno = $col['aluno'] ?? null;

        // ano de nascimento: XLSX (4 digitos plausiveis) > idade/ano do DOCX
        $ano = null;
        if ($x && preg_match('/^(19|20)\d{2}$/', $x['ano_nascimento']) && (int) $x['ano_nascimento'] <= self::ANO_REFERENCIA) {
            $ano = (int) $x['ano_nascimento'];
        } elseif ($aluno && ($idade = $this->idadeDe($aluno['idade'])) !== null) {
            $ano = self::ANO_REFERENCIA - $idade;
        }
        if ($ano === null) {
            $this->problema('sem_data_nascimento', "\"{$nome}\": sem ano de nascimento nem idade validos — fica sem data.");
        }

        $genero = $aluno['genero'] ?? '';
        if (! in_array($genero, ['M', 'F'], true)) {
            $this->problema('sexo_assumido', "\"{$nome}\": sem genero no DOCX — assumido M (editar).");
            $genero = 'M';
        }

        $servico = $x ? $this->normalizar($x['servico']) : '';
        $tipo = str_starts_with($servico, 'confirma') ? 'confirmacao' : 'nova';

        $obs = [];
        if ($x) {
            if ($x['situacao'] !== '' && ! str_contains(strtolower($x['situacao']), 'consultar')) {
                $obs[] = 'Situação 2025/2026: '.$x['situacao'];
            }
            if ($x['observacao'] !== '') {
                $obs[] = 'Obs.: '.$x['observacao'];
            }
        }

        if (! $col) {
            $this->problema('sem_turma', "\"{$nome}\": inscrito mas sem turma atribuida.");
        }

        return [
            'nome' => $nome,
            'sexo' => $genero,
            'ano_nascimento' => $ano,
            'telefone' => $x ? $this->telefone($x['telefone']) : null,
            'tipo' => $tipo,
            'observacoes' => $obs ? implode(' | ', $obs) : null,
            'turma_indice' => $col['turma'] ?? null,
        ];
    }

    private function telefone(string $bruto): ?string
    {
        $bruto = trim($bruto);
        if ($bruto === '' || str_starts_with($bruto, '#')) {
            return null;
        }
        $primeiro = preg_split('/[\/,;]/', $bruto)[0];
        $limpo = preg_replace('/[^\d+]/', '', $primeiro);

        return $limpo === '' ? null : mb_substr($limpo, 0, 20);
    }

    // --------------------------------------------------------------- apoio

    private function limparNome(string $nome): string
    {
        return trim(preg_replace('/\s+/u', ' ', $nome));
    }

    private function normalizar(string $s): string
    {
        $s = mb_strtolower($s);
        $s = preg_replace('/\p{Mn}/u', '', \Normalizer::normalize($s, \Normalizer::FORM_D));
        $s = preg_replace('/[^a-z0-9 \/]/', '', str_replace(['-', '/'], ['', ''], $s));

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    /** Procura em $candidatos uma chave a distancia de edicao <= $maximo (e comprimento minimo). */
    private function procurarSemelhante(string $chave, array $candidatos, int $maximo, int $minimoLen = 8): ?string
    {
        if (in_array($chave, $candidatos, true)) {
            return $chave;
        }
        if (strlen($chave) < $minimoLen) {
            return null;
        }
        $melhor = null;
        $melhorDist = $maximo + 1;
        foreach ($candidatos as $c) {
            if (abs(strlen($c) - strlen($chave)) > $maximo) {
                continue;
            }
            $d = levenshtein($chave, $c);
            if ($d < $melhorDist) {
                $melhor = $c;
                $melhorDist = $d;
            }
        }

        return $melhor;
    }

    private function problema(string $tipo, string $detalhe): void
    {
        $this->problemas[] = ['tipo' => $tipo, 'detalhe' => $detalhe];
    }
}
