@extends('pdfs.layout')

@section('conteudo')
    <p><strong>Ano:</strong> {{ $ano }}</p>

    <table>
        <thead>
            <tr>
                <th>Mês</th>
                @foreach ($dados['categorias'] as $categoria)
                    <th class="text-right">{{ $categoria['nome'] }}</th>
                @endforeach
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach (['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'] as $i => $mesLabel)
                @php $linha = $dados['por_mes_categoria'][$i + 1]; @endphp
                <tr>
                    <td>{{ $mesLabel }}</td>
                    @foreach ($dados['categorias'] as $categoria)
                        <td class="text-right">{{ number_format($linha[$categoria['chave']], 2, ',', '.') }}</td>
                    @endforeach
                    <td class="text-right">{{ number_format(array_sum($linha), 2, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>Total</td>
                @foreach ($dados['categorias'] as $categoria)
                    <td class="text-right">{{ number_format($dados['por_categoria'][$categoria['chave']], 2, ',', '.') }}</td>
                @endforeach
                <td class="text-right">{{ number_format($dados['total'], 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
@endsection
