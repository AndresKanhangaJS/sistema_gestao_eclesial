@extends('pdfs.layout')

@section('conteudo')
    <table>
        <thead>
            <tr>
                <th>Nº</th>
                <th>Catequizando</th>
                <th>Centro</th>
                <th>Ano Lectivo</th>
                <th>Ano Catequese</th>
                <th>Sacramento(s)</th>
                <th>Nº Ficha</th>
                <th>Data</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($inscricoes as $inscricao)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $inscricao->catequizando->nome_completo }}</td>
                    <td>{{ $inscricao->centro->nome }}</td>
                    <td>{{ $inscricao->anoLetivo->nome }}</td>
                    <td>{{ $inscricao->anoCatequetico->nome ?? '—' }}</td>
                    <td>{{ $inscricao->sacramentos->pluck('nome')->implode(', ') ?: '—' }}</td>
                    <td>{{ $inscricao->numero_ficha }}</td>
                    <td>{{ $inscricao->data_atendimento->format('d/m/Y') }}</td>
                    <td>{{ ucfirst($inscricao->estado->value) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
