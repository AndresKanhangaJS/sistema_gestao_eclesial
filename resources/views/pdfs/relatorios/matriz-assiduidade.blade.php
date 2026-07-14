@extends('pdfs.layout')

@section('conteudo')
    <p><strong>Centro:</strong> {{ $centro?->nome ?? 'Todos os centros' }} &middot; <strong>Ano:</strong> {{ $ano }}</p>

    <table>
        <thead>
            <tr>
                <th>Fiel</th>
                @foreach (['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'] as $mesLabel)
                    <th class="text-right">{{ $mesLabel }}</th>
                @endforeach
                <th>Segmento</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($linhas as $linha)
                <tr>
                    <td>{{ $linha['fiel']->nome }}</td>
                    @foreach ($linha['meses'] as $estado)
                        <td class="text-right">
                            @if ($estado === 'pago') P
                            @elseif ($estado === 'em_aberto') A
                            @else — @endif
                        </td>
                    @endforeach
                    <td>{{ $linha['segmento'] ?? $linha['total_pagos'] . '/12' }}</td>
                </tr>
            @empty
                <tr><td colspan="14">Sem dados para este centro/ano.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="meta">P = Pago &middot; A = Em aberto &middot; — = Não vinculado</p>
@endsection
