<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/**
 * Pagina de destino forcada pelo middleware ForcarAlteracaoSenha sempre que
 * User::deve_alterar_senha e verdadeiro — nunca aparece na navegacao, so e
 * alcancada pelo redireccionamento automatico. A senha inicial de uma conta
 * nova (ou redefinida por um administrador_paroquial/coordenador_centro)
 * nunca e escolhida pelo proprio utilizador; esta pagina e o unico sitio
 * onde a flag e desligada.
 *
 * $layout aponta para o mesmo layout "simple" usado pelo Login (cartao
 * centrado, com fundo e logotipo) em vez do layout normal do painel com
 * sidebar — nao faz sentido navegar para mais lado nenhum aqui. Continua a
 * estender Page (nao SimplePage): SimplePage estende BasePage directamente,
 * sem a trait HasRoutes, e o discoverPages() do painel so regista classes
 * que sejam is_subclass_of(Filament\Pages\Page::class) — SimplePage falhava
 * esse teste e a rota nem chegava a ser criada (getUrl() inexistente,
 * middleware sem rota para reencaminhar).
 */
class AlterarSenhaObrigatoria extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $layout = 'filament-panels::components.layout.simple';

    protected static ?string $slug = 'alterar-senha';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.alterar-senha-obrigatoria';

    protected static ?string $title = 'Alterar Senha';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    /**
     * Antes de orientar a troca de senha, deixa claro que o login teve
     * sucesso — pedido explícito do cliente: sem isto, um utilizador a
     * entrar pela primeira vez cai directamente no formulário de troca sem
     * perceber que já entrou no sistema. A confirmação em toast complementa
     * o alerta fixo na página (ver view), que não desaparece sozinho.
     */
    public function mount(): void
    {
        $this->form->fill();

        Notification::make()
            ->title('Login efetuado com sucesso')
            ->body('Bem-vindo(a), '.Auth::user()->name.'.')
            ->success()
            ->send();
    }

    public function hasLogo(): bool
    {
        return true;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('nova_senha')
                    ->label('Nova senha')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8)
                    ->confirmed()
                    ->autofocus()
                    ->extraInputAttributes(['tabindex' => 1]),
                TextInput::make('nova_senha_confirmation')
                    ->label('Confirmar nova senha')
                    ->password()
                    ->revealable()
                    ->required()
                    ->dehydrated(false)
                    ->extraInputAttributes(['tabindex' => 2]),
            ])
            ->statePath('data');
    }

    public function guardar(): void
    {
        $data = $this->form->getState();

        $user = Auth::user();

        $user->forceFill([
            'password' => $data['nova_senha'],
            'deve_alterar_senha' => false,
        ])->save();

        // Este pedido Livewire (AJAX) nao passa pelo middleware do painel
        // (AuthenticateSession), que compara em cada pagina normal o hash da
        // senha guardado na sessao com o hash actual do utilizador e desliga
        // a sessao sozinho se nao baterem certo — sem isto, o proximo pedido
        // de pagina via-lo-ia como uma sessao "roubada" (a senha mudou, mas a
        // sessao ainda tem o hash antigo) e mandava o utilizador de volta
        // para o login mesmo tendo acabado de trocar a senha com sucesso,
        // reentrando sempre nesta pagina (bug do "loop" reportado).
        session()->put('password_hash_'.Auth::getDefaultDriver(), $user->getAuthPassword());

        Notification::make()
            ->title('Senha alterada com sucesso')
            ->success()
            ->send();

        $this->redirect(Dashboard::getUrl());
    }
}
