@extends('pdfs.layout')

@section('conteudo')
    <table>
        <thead>
            <tr>
                <th>Banco</th>
                <th>Nº Conta</th>
                <th>Centro</th>
                <th>Data</th>
                <th>Tipo</th>
                <th>Referência</th>
                <th class="text-right">Valor</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($movimentos as $movimento)
                <tr>
                    <td>{{ $movimento->banco->sigla }}</td>
                    <td>{{ $movimento->banco->numero_conta }}</td>
                    <td>{{ $movimento->centro->nome }}</td>
                    <td>{{ $movimento->data_movimento->format('d/m/Y') }}</td>
                    <td>{{ $movimento->tipo->value }}</td>
                    <td>{{ $movimento->numero_referencia_bancaria }}</td>
                    <td class="text-right">{{ number_format($movimento->valor, 2, ',', '.') }}</td>
                    <td>{{ $movimento->status_conciliacao->value }}</td>
                </tr>
            @empty
                <tr><td colspan="8">Sem movimentos bancários registados.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
