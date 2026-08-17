@extends('pdfs.layout')

@section('conteudo')
    <table>
        <thead>
            <tr>
                <th>Nº</th>
                <th>Nome</th>
                <th>Centro</th>
                <th>Telefone</th>
                <th>Email</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($catequistas as $catequista)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $catequista->nome_completo }}</td>
                    <td>{{ $catequista->centro->nome ?? '—' }}</td>
                    <td>{{ $catequista->telefone ?? '—' }}</td>
                    <td>{{ $catequista->email ?? '—' }}</td>
                    <td>{{ $catequista->ativo ? 'Activo' : 'Inactivo' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
