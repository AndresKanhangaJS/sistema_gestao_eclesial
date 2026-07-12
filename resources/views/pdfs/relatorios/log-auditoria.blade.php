@extends('pdfs.layout')

@section('conteudo')
    <table>
        <thead>
            <tr>
                <th>Data/Hora</th>
                <th>Utilizador</th>
                <th>Evento</th>
                <th>Movimento #</th>
                <th>Alterações</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($atividades as $atividade)
                <tr>
                    <td>{{ $atividade->created_at->format('d/m/Y H:i') }}</td>
                    <td>{{ $atividade->causer->name ?? '—' }}</td>
                    <td>{{ $atividade->description }}</td>
                    <td>{{ $atividade->subject_id }}</td>
                    <td>{{ json_encode($atividade->changes()->get('attributes', [])) }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Sem registos de auditoria.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
