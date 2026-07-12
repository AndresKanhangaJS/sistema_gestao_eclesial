@extends('pdfs.layout')

@section('conteudo')
    <p><strong>Ano:</strong> {{ $ano }}</p>

    <table>
        <thead>
            <tr>
                <th>Mês</th>
                <th class="text-right">Receitas</th>
                <th class="text-right">Despesas</th>
                <th class="text-right">Saldo</th>
            </tr>
        </thead>
        <tbody>
            @foreach (['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'] as $i => $mesLabel)
                @php $linha = $dados['por_mes'][$i + 1]; @endphp
                <tr>
                    <td>{{ $mesLabel }}</td>
                    <td class="text-right">{{ number_format($linha['receitas'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($linha['despesas'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($linha['saldo'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>Total</td>
                <td class="text-right">{{ number_format($dados['total_receitas'], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($dados['total_despesas'], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($dados['saldo'], 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
@endsection
