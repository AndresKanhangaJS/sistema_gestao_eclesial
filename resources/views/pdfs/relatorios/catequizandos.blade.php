@extends('pdfs.layout')

@section('conteudo')
    <table>
        <thead>
            <tr>
                <th>Nº</th>
                <th>Nome</th>
                <th>Centro</th>
                <th>Data de Nascimento</th>
                <th>Sexo</th>
                <th>Telefone</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($catequizandos as $catequizando)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $catequizando->nome_completo }}</td>
                    <td>{{ $catequizando->centro->nome }}</td>
                    <td>{{ $catequizando->data_nascimento->format('d/m/Y') }}</td>
                    <td>{{ $catequizando->sexo === 'M' ? 'Masculino' : 'Feminino' }}</td>
                    <td>{{ $catequizando->telefone ?? '—' }}</td>
                    <td>{{ $catequizando->status === 'ativo' ? 'Activo' : 'Inactivo' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endsection
