@extends('pdfs.layout')

@section('conteudo')
    <p><strong>Ano:</strong> {{ $ano }}</p>

    <table>
        <thead>
            <tr>
                <th>Mês</th>
                <th class="text-right">Dízimo</th>
                <th class="text-right">Ofertório</th>
                <th class="text-right">Outras Contribuições</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach (['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'] as $i => $mesLabel)
                @php $linha = $dados['por_mes_tipo'][$i + 1]; @endphp
                <tr>
                    <td>{{ $mesLabel }}</td>
                    <td class="text-right">{{ number_format($linha['dizimo'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($linha['ofertorio'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($linha['campanha'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format(array_sum($linha), 2, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>Total</td>
                <td class="text-right">{{ number_format($dados['por_tipo']['dizimo'], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($dados['por_tipo']['ofertorio'], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($dados['por_tipo']['campanha'], 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format($dados['total'], 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>
@endsection
