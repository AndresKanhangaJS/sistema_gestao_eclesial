@extends('pdfs.layout')

@section('conteudo')
    <p>
        <strong>Mês:</strong> {{ $mesLabel }} &middot;
        <strong>Ano:</strong> {{ $ano }} &middot;
        <strong>Centro:</strong> {{ $centro->nome }}
    </p>

    <table>
        <thead>
            <tr>
                <th colspan="2">1 - RECEITAS</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>1.1. Receitas ordinárias</td><td></td></tr>
            <tr><td>&nbsp;&nbsp;1.1.1. Subsídios</td><td class="text-right">{{ number_format($dados['receitas']['subsidios'], 2, ',', '.') }}</td></tr>
            <tr><td>&nbsp;&nbsp;1.1.2. Donativos</td><td class="text-right">{{ number_format($dados['receitas']['donativos'], 2, ',', '.') }}</td></tr>
            <tr><td>&nbsp;&nbsp;1.1.3. Peditórios</td><td class="text-right">{{ number_format($dados['receitas']['peditorios'], 2, ',', '.') }}</td></tr>
            <tr><td>&nbsp;&nbsp;1.1.4. Entradas do Cartório</td><td class="text-right">{{ number_format($dados['receitas']['cartorio'], 2, ',', '.') }}</td></tr>
            <tr><td>&nbsp;&nbsp;1.1.5. Receitas Diversas</td><td></td></tr>
            @forelse ($dados['receitas']['diversas'] as $i => $linha)
                <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;1.1.5.{{ $i + 1 }}. {{ $linha['nome'] }}</td><td class="text-right">{{ number_format($linha['valor'], 2, ',', '.') }}</td></tr>
            @empty
                <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;1.1.5.1._________________</td><td></td></tr>
            @endforelse
            <tr class="total-row"><td>Sub total</td><td class="text-right">{{ number_format($dados['receitas']['subtotal_ordinarias'], 2, ',', '.') }}</td></tr>

            <tr><td>1.2. Côngruas</td><td></td></tr>
            <tr class="total-row"><td>Sub total</td><td class="text-right">{{ number_format($dados['receitas']['congruas'], 2, ',', '.') }}</td></tr>

            <tr><td>1.3. Intenções de Missa</td><td></td></tr>
            <tr class="total-row"><td>Sub total</td><td class="text-right">{{ number_format($dados['receitas']['intencoes_missa'], 2, ',', '.') }}</td></tr>

            <tr><td>SALDO MÊS ANTERIOR</td><td></td></tr>
            <tr class="total-row"><td>Sub total</td><td class="text-right">{{ number_format($dados['receitas']['saldo_mes_anterior'], 2, ',', '.') }}</td></tr>

            <tr class="total-row"><td><strong>TOTAL DE RECEITAS</strong></td><td class="text-right"><strong>{{ number_format($dados['receitas']['total'], 2, ',', '.') }}</strong></td></tr>
        </tbody>
    </table>

    <table style="margin-top: 16px;">
        <thead>
            <tr>
                <th colspan="2">2 - DESPESAS</th>
            </tr>
        </thead>
        <tbody>
            <tr><td>2.1. Subsídios ao Clero</td><td></td></tr>
            <tr><td>&nbsp;&nbsp;2.1.1. Subsídio aos Padres</td><td class="text-right">{{ number_format($dados['despesas']['subsidio_padres'], 2, ',', '.') }}</td></tr>
            <tr><td>&nbsp;&nbsp;2.1.2. Estipêndios</td><td class="text-right">{{ number_format($dados['despesas']['estipendios'], 2, ',', '.') }}</td></tr>
            <tr><td>&nbsp;&nbsp;2.1.3. Subsídio Casa Paroquial</td><td class="text-right">{{ number_format($dados['despesas']['subsidio_casa_paroquial'], 2, ',', '.') }}</td></tr>
            <tr class="total-row"><td>SUB TOTAL</td><td class="text-right">{{ number_format($dados['despesas']['subtotal_clero'], 2, ',', '.') }}</td></tr>

            <tr><td>2.2. Despesas correntes da Paróquia</td><td></td></tr>
            <tr><td>&nbsp;&nbsp;2.2.1. Salário Funcionários + INSS</td><td class="text-right">{{ number_format($dados['despesas']['salario_funcionarios'], 2, ',', '.') }}</td></tr>
            <tr><td>&nbsp;&nbsp;2.2.2. Despesas de Culto</td><td class="text-right">{{ number_format($dados['despesas']['despesas_culto'], 2, ',', '.') }}</td></tr>
            <tr><td>&nbsp;&nbsp;2.2.3. Transporte - Viagens</td><td class="text-right">{{ number_format($dados['despesas']['transporte'], 2, ',', '.') }}</td></tr>
            <tr><td>&nbsp;&nbsp;2.2.4. Energia</td><td class="text-right">{{ number_format($dados['despesas']['energia'], 2, ',', '.') }}</td></tr>
            <tr><td>&nbsp;&nbsp;2.2.5. Água</td><td class="text-right">{{ number_format($dados['despesas']['agua'], 2, ',', '.') }}</td></tr>
            <tr><td>&nbsp;&nbsp;2.2.6. Internet - comunicações</td><td class="text-right">{{ number_format($dados['despesas']['internet'], 2, ',', '.') }}</td></tr>
            <tr><td>&nbsp;&nbsp;2.2.7. Donativos e esmolas</td><td class="text-right">{{ number_format($dados['despesas']['donativos_esmolas'], 2, ',', '.') }}</td></tr>
            <tr><td>&nbsp;&nbsp;2.2.8. Outras Despesas</td><td></td></tr>
            @forelse ($dados['despesas']['outras_despesas'] as $i => $linha)
                <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;2.2.8.{{ $i + 1 }}____{{ $linha['nome'] }}</td><td class="text-right">{{ number_format($linha['valor'], 2, ',', '.') }}</td></tr>
            @empty
                <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;2.2.8.1____________</td><td></td></tr>
            @endforelse
            <tr class="total-row"><td>SUB TOTAL</td><td class="text-right">{{ number_format($dados['despesas']['subtotal_correntes'], 2, ',', '.') }}</td></tr>

            <tr><td>2.3. Contributos à Arquidiocese</td><td></td></tr>
            <tr><td>&nbsp;&nbsp;2.3.1. Contributo para o Bispado ({{ number_format($dados['despesas']['percentual_bispado'], 0) }}%)</td><td class="text-right">{{ number_format($dados['despesas']['contributo_bispado'], 2, ',', '.') }}</td></tr>
            <tr><td>&nbsp;&nbsp;2.3.2. Contributo do Dízimo ({{ number_format($dados['despesas']['percentual_dizimo'], 0) }}%)</td><td class="text-right">{{ number_format($dados['despesas']['contributo_dizimo'], 2, ',', '.') }}</td></tr>
            <tr><td>&nbsp;&nbsp;2.3.3. Missas Binadas</td><td class="text-right">{{ number_format($dados['despesas']['missas_binadas'], 2, ',', '.') }}</td></tr>
            <tr><td>&nbsp;&nbsp;2.3.4. Colectas Obrigatórias</td><td></td></tr>
            @forelse ($dados['despesas']['colectas_obrigatorias'] as $i => $linha)
                <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;2.3.4.{{ $i + 1 }}. {{ $linha['nome'] }}</td><td class="text-right">{{ number_format($linha['valor'], 2, ',', '.') }}</td></tr>
            @empty
                <tr><td>&nbsp;&nbsp;&nbsp;&nbsp;2.3.4.1. ___________________</td><td></td></tr>
            @endforelse
            <tr class="total-row"><td>SUB TOTAL</td><td class="text-right">{{ number_format($dados['despesas']['subtotal_contributos'], 2, ',', '.') }}</td></tr>

            <tr class="total-row"><td><strong>TOTAL DE DESPESAS</strong></td><td class="text-right"><strong>{{ number_format($dados['despesas']['total'], 2, ',', '.') }}</strong></td></tr>
        </tbody>
    </table>

    <table style="margin-top: 16px;">
        <tbody>
            <tr class="total-row"><td><strong>SALDO DO MÊS EM CURSO</strong></td><td class="text-right"><strong>{{ number_format($dados['saldo_mes'], 2, ',', '.') }}</strong></td></tr>
        </tbody>
    </table>

    <div style="margin-top: 20px; font-size: 9px; color: #4b5563;">
        <p><strong>LEGENDA:</strong></p>
        <p><strong>RECEITAS</strong></p>
        <p>1.1. As receitas ordinárias são todas as entradas da Paróquia, dos quais advêm os {{ number_format($dados['despesas']['percentual_bispado'], 0) }}% para a Cúria que se dão mensalmente na Tesouraria da Arquidiocese.</p>
        <p>1.1.1. Subsídios: Entradas regulares por parte de um ente, instituição ou pessoas singulares.</p>
        <p>1.1.2. Donativos: Entradas irregulares em espécie ou monetária.</p>
        <p>1.1.3. Peditórios: Ofertórios semanais e dominicais e de ocasiões especiais.</p>
        <p>1.1.4. Entradas do Cartório: Emolumentos e entradas gerais do cartório.</p>
        <p>1.1.5. Receitas Diversas: Entradas de alugueres, parques, escolas, centros médicos, etc…</p>
        <p>1.2. Côngruas: Dízimo.</p>
        <p>1.3. Intenções de Missa: As intenções de Missa devem ser 1 para o Padre celebrante e o resto das Intenções enviadas para a Cúria (Fundo de Previdência Sacerdotal).</p>
        <p>SALDO MÊS ANTERIOR: Saldo caixa e dos Bancos do mês anterior.</p>

        <p><strong>DESPESAS</strong></p>
        <p>2.1. Subsídios ao Clero</p>
        <p>2.1.1. Subsídio aos Padres: Pároco e Vigários.</p>
        <p>2.1.2. Estipêndios: são as contribuições dadas aos Padres por Missa celebrada.</p>
        <p>2.1.3. Subsídio Casa Paroquial: o sustento logístico e manutenção da casa do clero.</p>
        <p>2.2. Despesas correntes da Paróquia</p>
        <p>2.2.1. Salário Funcionários + INSS: salário dos trabalhadores + INSS e IRT.</p>
        <p>2.2.2. Despesas de Culto: velas, hóstias, vinho...</p>
        <p>2.2.3. Transporte - Viagens: combustível, seguro, manutenção da viatura e viagens pastorais.</p>
        <p>2.2.4. Energia: contas de luz e gerador.</p>
        <p>2.2.5. Água: contas de água (Epal ou…).</p>
        <p>2.2.6. Internet - comunicações: internet - saldo telefónico - planos internet, etc...</p>
        <p>2.2.7. Donativos e esmolas: ajudas dadas aos indigentes e necessitados.</p>
        <p>2.2.8. Outras Despesas: especificar quais são essas outras despesas.</p>
        <p>2.3. Contributos à Arquidiocese</p>
        <p>2.3.1. Contributo para o Bispado ({{ number_format($dados['despesas']['percentual_bispado'], 0) }}%): para a gestão ordinária da Arquidiocese.</p>
        <p>2.3.2. Contributo do Dízimo ({{ number_format($dados['despesas']['percentual_dizimo'], 0) }}%): fundo para construções, automóveis, fundo para acudir calamidades.</p>
        <p>2.3.3. Missas Binadas: fundo de Previdência Social do Clero.</p>
        <p>2.3.4. Colectas Obrigatórias: especificar a Colecta Obrigatória em referência (Santa Infância, Óbolo de S. Pedro, etc...).</p>
    </div>
@endsection
