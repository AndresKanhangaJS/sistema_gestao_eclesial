@extends('pdfs.layout')

@section('conteudo')
    <p><strong>Ano:</strong> {{ $ano }}</p>

    <table>
        <thead>
            <tr>
                <th>Fiel</th>
                <th class="text-right">Dízimos pagos</th>
                <th>Situação</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $linha)
                <tr>
                    <td>{{ $linha['fiel']->nome }}</td>
                    <td class="text-right">{{ $linha['total_pagos'] }}/12</td>
                    <td>{{ $linha['segmento'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="3">Sem fiéis para este ano/centro.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
