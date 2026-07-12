<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        header { margin-bottom: 16px; border-bottom: 2px solid #1f2937; padding-bottom: 8px; }
        h1 { font-size: 16px; margin: 0; }
        .meta { color: #6b7280; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 4px 6px; text-align: left; }
        th { background: #f3f4f6; }
        .text-right { text-align: right; }
        .total-row td { font-weight: bold; background: #f9fafb; }
    </style>
</head>
<body>
    <header>
        <h1>{{ $titulo }}</h1>
        <div class="meta">
            {{ optional($paroquia ?? null)->nome ?? 'SGE — Sistema de Gestão Eclesial' }}
            &middot; Emitido em {{ now()->format('d/m/Y H:i') }}
        </div>
    </header>

    @yield('conteudo')
</body>
</html>
