<!DOCTYPE html>
<html lang="pt-PT">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'SGE') }}</title>

        <style>
            :root {
                --sge-bg: #f7f5f0;
                --sge-bg-alt: #ffffff;
                --sge-text: #211c14;
                --sge-text-muted: #6b6355;
                --sge-border: #e4ddce;
                --sge-primary: #b45309;
                --sge-primary-dark: #92400e;
                --sge-primary-contrast: #fffaf0;
            }

            @media (prefers-color-scheme: dark) {
                :root {
                    --sge-bg: #16130d;
                    --sge-bg-alt: #201b12;
                    --sge-text: #f3ede1;
                    --sge-text-muted: #a89a80;
                    --sge-border: #3a3222;
                    --sge-primary: #d97706;
                    --sge-primary-dark: #f59e0b;
                    --sge-primary-contrast: #1a1305;
                }
            }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                min-height: 100vh;
                background: var(--sge-bg);
                color: var(--sge-text);
                font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, sans-serif;
                display: flex;
                flex-direction: column;
            }

            header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                padding: 1.25rem 1.5rem;
                max-width: 72rem;
                width: 100%;
                margin: 0 auto;
            }

            .brand {
                display: flex;
                align-items: center;
                gap: 0.65rem;
                font-weight: 600;
                letter-spacing: 0.01em;
            }

            .brand-mark {
                width: 2.25rem;
                height: 2.25rem;
                border-radius: 0.6rem;
                background: linear-gradient(135deg, var(--sge-primary), var(--sge-primary-dark));
                color: #fff;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 0.85rem;
                font-weight: 700;
                letter-spacing: 0.03em;
            }

            .btn {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                padding: 0.55rem 1.1rem;
                border-radius: 0.5rem;
                font-size: 0.9rem;
                font-weight: 600;
                text-decoration: none;
                border: 1px solid transparent;
                transition: opacity 0.15s ease, transform 0.15s ease;
            }

            .btn:hover { opacity: 0.9; }

            .btn-primary {
                background: var(--sge-primary);
                color: var(--sge-primary-contrast);
            }

            .btn-ghost {
                background: transparent;
                border-color: var(--sge-border);
                color: var(--sge-text);
            }

            main {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem 1.5rem 3rem;
            }

            .hero {
                max-width: 56rem;
                width: 100%;
                text-align: center;
            }

            .hero-badge {
                display: inline-block;
                font-size: 0.75rem;
                font-weight: 600;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: var(--sge-primary-dark);
                background: color-mix(in srgb, var(--sge-primary) 12%, transparent);
                border-radius: 999px;
                padding: 0.3rem 0.85rem;
                margin-bottom: 1.25rem;
            }

            .hero h1 {
                font-size: clamp(1.9rem, 4vw, 2.75rem);
                line-height: 1.15;
                margin: 0 0 0.75rem;
                font-weight: 700;
            }

            .hero p.lead {
                font-size: 1.05rem;
                color: var(--sge-text-muted);
                max-width: 38rem;
                margin: 0 auto 2rem;
                line-height: 1.6;
            }

            .hero-actions {
                display: flex;
                gap: 0.75rem;
                justify-content: center;
                flex-wrap: wrap;
                margin-bottom: 3rem;
            }

            .hero-actions .btn {
                padding: 0.75rem 1.6rem;
                font-size: 0.95rem;
            }

            .modules {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr));
                gap: 0.9rem;
                text-align: left;
            }

            .module-card {
                background: var(--sge-bg-alt);
                border: 1px solid var(--sge-border);
                border-radius: 0.75rem;
                padding: 1rem 1.1rem;
            }

            .module-card .icon {
                font-size: 1.35rem;
                margin-bottom: 0.5rem;
                display: block;
            }

            .module-card h3 {
                font-size: 0.95rem;
                margin: 0 0 0.25rem;
                font-weight: 600;
            }

            .module-card p {
                font-size: 0.8rem;
                color: var(--sge-text-muted);
                margin: 0;
                line-height: 1.4;
            }

            footer {
                text-align: center;
                padding: 1.5rem;
                font-size: 0.8rem;
                color: var(--sge-text-muted);
            }
        </style>
    </head>
    <body>
        <header>
            <div class="brand">
                <span class="brand-mark">SGE</span>
                <span>{{ config('app.name', 'SGE') }}</span>
            </div>
            <nav>
                @auth
                    @if (Route::has('filament.admin.pages.dashboard'))
                        <a href="{{ route('filament.admin.pages.dashboard') }}" class="btn btn-ghost">Aceder ao Painel</a>
                    @endif
                @else
                    @if (Route::has('filament.admin.auth.login'))
                        <a href="{{ route('filament.admin.auth.login') }}" class="btn btn-ghost">Entrar</a>
                    @endif
                @endauth
            </nav>
        </header>

        <main>
            <div class="hero">
                <span class="hero-badge">Sistema de Gestão Eclesial</span>
                <h1>Gestão completa da vida paroquial, num só lugar</h1>
                <p class="lead">
                    Finanças, fiéis, dízimos e catequese organizados e acessíveis - com o rigor
                    que a gestão da paróquia exige.
                </p>

                <div class="hero-actions">
                    @auth
                        @if (Route::has('filament.admin.pages.dashboard'))
                            <a href="{{ route('filament.admin.pages.dashboard') }}" class="btn btn-primary">Aceder ao Painel</a>
                        @endif
                    @else
                        @if (Route::has('filament.admin.auth.login'))
                            <a href="{{ route('filament.admin.auth.login') }}" class="btn btn-primary">Entrar</a>
                        @endif
                    @endauth
                </div>

                <div class="modules">
                    <div class="module-card">
                        <span class="icon">💰</span>
                        <h3>Financeiro</h3>
                        <p>Movimentos, conciliação bancária e despesas por centro.</p>
                    </div>
                    <div class="module-card">
                        <span class="icon">🙏</span>
                        <h3>Fiéis &amp; Dízimos</h3>
                        <p>Cadastro de fiéis e matriz de assiduidade do dízimo.</p>
                    </div>
                    <div class="module-card">
                        <span class="icon">📖</span>
                        <h3>Catequese</h3>
                        <p>Catequizandos, turmas, catequistas e inscrições por ano lectivo.</p>
                    </div>
                    <div class="module-card">
                        <span class="icon">📊</span>
                        <h3>Relatórios</h3>
                        <p>Exportação em PDF e Excel para prestação de contas.</p>
                    </div>
                </div>
            </div>
        </main>

        <footer>
            {{ config('app.name', 'SGE') }} &middot; {{ now()->year }} &middot; made by &lt;AVK/&gt;
        </footer>
    </body>
</html>
