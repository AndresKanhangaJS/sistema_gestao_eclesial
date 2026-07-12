@extends('pdfs.layout')

@section('conteudo')
    <table>
        <thead>
            <tr>
                <th>Fiel</th>
                <th>Centro origem</th>
                <th>Centro destino</th>
                <th>Data da transferência</th>
                <th>Motivo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($vinculos as $vinculo)
                <tr>
                    <td>{{ $vinculo->fiel->nome }}</td>
                    <td>{{ \App\Filament\Pages\Relatorios\AuditoriaRepassesInterCentro::centroOrigem($vinculo) }}</td>
                    <td>{{ $vinculo->centro->nome }}</td>
                    <td>{{ \Illuminate\Support\Carbon::parse($vinculo->data_inicio)->format('d/m/Y') }}</td>
                    <td>{{ $vinculo->motivo_transferencia }}</td>
                </tr>
            @empty
                <tr><td colspan="5">Sem transferências registadas.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
