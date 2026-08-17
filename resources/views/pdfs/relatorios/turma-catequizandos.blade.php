@extends('pdfs.layout')

@section('conteudo')
    <p><strong>Turma:</strong> {{ $turma->descricaoCurta() }}</p>
    <p><strong>Centro:</strong> {{ $turma->centro->nome }}</p>
    <p><strong>Sacramento(s):</strong> {{ $sacramentos->pluck('nome')->implode(', ') ?: '—' }}</p>
    <p><strong>Catequista(s):</strong> {{ $catequistas->map(fn ($catequista) => $catequista->nome_completo.' ('.($catequista->pivot->papel === 'titular' ? 'Titular' : 'Auxiliar').')')->implode(', ') ?: '—' }}</p>

    <table>
        <thead>
            <tr>
                <th>Nº</th>
                <th>Catequizando</th>
                <th>Nº Ficha</th>
                <th>Início</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($inscricoes as $inscricao)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $inscricao->catequizando->nome_completo }}</td>
                    <td>{{ $inscricao->numero_ficha }}</td>
                    <td>{{ $inscricao->pivot->data_inicio->format('d/m/Y') }}</td>
                    <td>{{ $inscricao->pivot->status->label() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
