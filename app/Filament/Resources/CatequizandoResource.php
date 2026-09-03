<?php

namespace App\Filament\Resources;

use App\Enums\EstadoInscricao;
use App\Enums\TipoInscricao;
use App\Filament\Concerns\TemExportacaoComEstado;
use App\Filament\Resources\CatequizandoResource\Pages;
use App\Filament\Resources\CatequizandoResource\RelationManagers\CentrosRelationManager;
use App\Filament\Resources\CatequizandoResource\RelationManagers\InscricoesRelationManager;
use App\Models\AnoCatequetico;
use App\Models\AnoLetivo;
use App\Models\Catequista;
use App\Models\Catequizando;
use App\Models\Fiel;
use App\Models\Inscricao;
use App\Models\Sacramento;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Navigation\NavigationItem;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CatequizandoResource extends Resource
{
    use TemExportacaoComEstado;

    protected static ?string $model = Catequizando::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    // O grupo "Catequizandos" tem os dois itens montados manualmente em
    // getNavigationItems() (Inscrever + Listar) — este valor so serve de
    // fallback para o Filament nao reclamar de propriedade em falta.
    protected static ?string $navigationGroup = 'Catequizandos';

    protected static ?string $navigationLabel = 'Listar';

    protected static ?string $modelLabel = 'Catequizando';

    protected static ?string $pluralModelLabel = 'Catequizandos';

    // Papeis que podem escolher livremente o centro; os restantes ficam
    // presos ao seu proprio centro (mesmo padrao do paroquia_id no
    // FielResource, adaptado a centro — docs/modulos/catequese.md seccao 2).
    private const GESTORES_CENTRO_LIVRE = ['admin_geral', 'coordenador_catequese_paroquia'];

    /**
     * Grupo "Catequizandos" com dois itens (Inscrever + Listar), em vez do
     * unico item automatico que o Filament geraria a partir do Resource —
     * pedido do utilizador para eliminar as duas voltas de registar o
     * Catequizando e so depois ir a Inscricoes fazer a ficha do ano lectivo.
     */
    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make('Inscrever')
                ->group('Catequizandos')
                ->icon('heroicon-o-user-plus')
                ->sort(1)
                ->url(fn (): string => static::getUrl('create'))
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.create'))
                ->visible(fn (): bool => static::canCreate()),
            NavigationItem::make('Listar')
                ->group('Catequizandos')
                ->icon(static::getNavigationIcon())
                ->sort(2)
                ->url(fn (): string => static::getUrl('index'))
                ->isActiveWhen(fn (): bool => request()->routeIs(static::getRouteBaseName().'.index')
                    || request()->routeIs(static::getRouteBaseName().'.edit'))
                ->visible(fn (): bool => static::canViewAny()),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Catequizando')
                    ->columnSpanFull()
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Dados Pessoais')
                            ->schema([
                                // So escolhivel na criacao — mudar de centro depois exige
                                // a accao dedicada "Transferir de Centro" (RelationManager),
                                // porque tem efeitos em catequizando_centros e inscricao_turma
                                // (docs/modulos/catequese.md seccao 7.1), nunca edicao livre.
                                Forms\Components\Select::make('centro_id')
                                    ->label('Centro')
                                    ->relationship('centro', 'nome')
                                    ->required()
                                    ->live()
                                    ->visible(fn (string $operation) => $operation === 'create'
                                        && (Auth::user()?->hasRole(self::GESTORES_CENTRO_LIVRE) ?? false))
                                    ->default(fn () => Auth::user()?->centro_id)
                                    ->afterStateUpdated(fn (Forms\Set $set) => $set('fiel_id', null)),
                                Forms\Components\TextInput::make('centro.nome')
                                    ->label('Centro actual')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->visible(fn (string $operation) => $operation === 'edit')
                                    ->helperText('Para mudar de centro, use "Transferir de Centro" no separador Centros.'),
                                Forms\Components\Select::make('fiel_id')
                                    ->label('Fiel vinculado')
                                    ->relationship(
                                        'fiel',
                                        'nome',
                                        modifyQueryUsing: fn (Builder $query, Get $get) => $query
                                            ->where('status', 'ativo')
                                            ->when(
                                                $get('centro_id'),
                                                fn (Builder $query, $centroId) => $query->whereHas(
                                                    'centros',
                                                    fn (Builder $query) => $query->where('centros.id', $centroId)->whereNull('fiel_centros.data_fim')
                                                )
                                            ),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->afterStateUpdated(function (?string $state, Forms\Set $set) {
                                        if (filled($state)) {
                                            $set('nome_completo', Fiel::find($state)?->nome);
                                        }
                                    })
                                    ->helperText('Opcional: só fiéis activos vinculados ao centro acima. Ao seleccionar, copia o nome automaticamente (pode editar a seguir).'),
                                Forms\Components\TextInput::make('nome_completo')
                                    ->label('Nome Completo')
                                    ->required()
                                    ->maxLength(200)
                                    ->columnSpanFull(),
                                Forms\Components\TextInput::make('nome_pai')
                                    ->label('Nome do Pai')
                                    ->maxLength(150),
                                Forms\Components\TextInput::make('nome_mae')
                                    ->label('Nome da Mãe')
                                    ->maxLength(150),
                                Forms\Components\TextInput::make('profissao')
                                    ->label('Profissão (dos pais/encarregado)')
                                    ->maxLength(100),
                                Forms\Components\DatePicker::make('data_nascimento')
                                    ->label('Data de Nascimento')
                                    ->required()
                                    ->maxDate(now()),
                                Forms\Components\Select::make('sexo')
                                    ->label('Sexo')
                                    ->options([
                                        'M' => 'Masculino',
                                        'F' => 'Feminino',
                                    ])
                                    ->required(),
                                Forms\Components\TextInput::make('numero_identificacao')
                                    ->label('Nº de Identificação (BI)')
                                    ->maxLength(30)
                                    ->unique(ignoreRecord: true),
                                Forms\Components\Select::make('status')
                                    ->label('Estado')
                                    ->options([
                                        'ativo' => 'Activo',
                                        'inativo' => 'Inactivo',
                                    ])
                                    ->required()
                                    ->default('ativo'),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('Naturalidade')
                            ->schema([
                                Forms\Components\TextInput::make('municipio_nascimento')
                                    ->label('Município de Nascimento')
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('provincia_nascimento')
                                    ->label('Província de Nascimento')
                                    ->maxLength(100),
                                Forms\Components\TextInput::make('pais_nascimento')
                                    ->label('País de Nascimento')
                                    ->default('Angola')
                                    ->maxLength(80),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('Contacto')
                            ->schema([
                                Forms\Components\TextInput::make('residencia')
                                    ->label('Residência')
                                    ->maxLength(150),
                                Forms\Components\TextInput::make('rua_numero')
                                    ->label('Rua/Número')
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('edificio')
                                    ->label('Edifício')
                                    ->maxLength(80),
                                Forms\Components\TextInput::make('casa_ap')
                                    ->label('Casa/Apartamento')
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('telefone')
                                    ->label('Telefone')
                                    ->tel()
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('telefone_casa')
                                    ->label('Telefone de Casa')
                                    ->tel()
                                    ->maxLength(20),
                                Forms\Components\TextInput::make('email')
                                    ->label('Email')
                                    ->email()
                                    ->maxLength(100),
                            ])
                            ->columns(2),
                        Forms\Components\Tabs\Tab::make('Dados Religiosos')
                            ->schema([
                                Forms\Components\Group::make()
                                    ->relationship('dadosReligiosos')
                                    ->schema([
                                        Forms\Components\Fieldset::make('Baptismo')
                                            ->schema([
                                                Forms\Components\TextInput::make('paroquia_baptismo')
                                                    ->label('Paróquia de Baptismo')
                                                    ->maxLength(150),
                                                Forms\Components\DatePicker::make('data_baptismo')
                                                    ->label('Data de Baptismo'),
                                                Forms\Components\TextInput::make('pais_baptismo')
                                                    ->label('País de Baptismo')
                                                    ->maxLength(80),
                                            ])
                                            ->columns(3),
                                        Forms\Components\Fieldset::make('Comunhão')
                                            ->schema([
                                                Forms\Components\TextInput::make('paroquia_comunhao')
                                                    ->label('Paróquia de Comunhão')
                                                    ->maxLength(150),
                                                Forms\Components\DatePicker::make('data_comunhao')
                                                    ->label('Data de Comunhão'),
                                                Forms\Components\TextInput::make('pais_comunhao')
                                                    ->label('País de Comunhão')
                                                    ->maxLength(80),
                                            ])
                                            ->columns(3),
                                        Forms\Components\Fieldset::make('Padrinhos')
                                            ->schema([
                                                Forms\Components\TextInput::make('padrinho_nome')
                                                    ->label('Nome do Padrinho')
                                                    ->maxLength(150),
                                                Forms\Components\TextInput::make('padrinho_telefone')
                                                    ->label('Telefone do Padrinho')
                                                    ->tel()
                                                    ->maxLength(20),
                                                Forms\Components\TextInput::make('madrinha_nome')
                                                    ->label('Nome da Madrinha')
                                                    ->maxLength(150),
                                                Forms\Components\TextInput::make('madrinha_telefone')
                                                    ->label('Telefone da Madrinha')
                                                    ->tel()
                                                    ->maxLength(20),
                                            ])
                                            ->columns(2),
                                        Forms\Components\Fieldset::make('Transferência')
                                            ->schema([
                                                Forms\Components\TextInput::make('paroquia_transferencia')
                                                    ->label('Paróquia de Transferência')
                                                    ->maxLength(150),
                                                Forms\Components\TextInput::make('ano_transferencia')
                                                    ->label('Ano de Transferência')
                                                    ->numeric()
                                                    ->minValue(1900)
                                                    ->maxValue(2100),
                                            ])
                                            ->columns(2),
                                        Forms\Components\Toggle::make('pertence_grupo')
                                            ->label('Pertence a Grupo')
                                            ->default(false)
                                            ->live(),
                                        Forms\Components\TextInput::make('nome_grupo')
                                            ->label('Nome do Grupo')
                                            ->maxLength(150)
                                            ->required()
                                            ->visible(fn (Forms\Get $get) => $get('pertence_grupo')),
                                    ]),
                            ]),
                        // So no registo (elimina a volta de registar o Catequizando e
                        // so depois ir a Inscricoes fazer a ficha do ano lectivo — pedido
                        // do utilizador). No 'edit' o formulario fica exactamente como
                        // antes; inscricoes seguintes usam o InscricaoResource ou a accao
                        // "Confirmar para o Próximo Ano Lectivo" na lista de Catequizandos.
                        Forms\Components\Tabs\Tab::make('Inscrição')
                            ->visible(fn (string $operation) => $operation === 'create')
                            ->schema([
                                // statePath proprio para os campos nascerem aninhados sob
                                // 'inscricao' e nunca colidirem com atributos do
                                // Catequizando — CreateCatequizando::mutateFormDataBeforeCreate()
                                // extrai este bloco antes de gravar o Catequizando.
                                Forms\Components\Group::make()
                                    ->statePath('inscricao')
                                    ->schema([
                                        Forms\Components\Select::make('ano_letivo_id')
                                            ->label('Ano Lectivo')
                                            ->options(fn () => AnoLetivo::orderByDesc('nome')->pluck('nome', 'id'))
                                            ->default(fn () => AnoLetivo::where('status', 'em_curso')->value('id'))
                                            ->required(),
                                        Forms\Components\Select::make('ano_catequetico_id')
                                            ->label('Ano de Catequese')
                                            ->options(fn () => AnoCatequetico::orderBy('ordem')->pluck('nome', 'id'))
                                            ->required()
                                            ->helperText('Usado para filtrar as turmas compatíveis ao colocar em turma.'),
                                        Forms\Components\Select::make('sacramentos')
                                            ->label('Sacramento(s)')
                                            ->options(fn () => Sacramento::orderBy('ordem')->pluck('nome', 'id'))
                                            ->multiple()
                                            ->preload()
                                            ->required()
                                            ->helperText('O conjunto exacto tem de bater certo com a turma escolhida, ex.: "Baptismo e Comunhão" não entra numa turma só de "Baptismo".'),
                                        Forms\Components\Select::make('catequista_id')
                                            ->label('Catequista Responsável')
                                            ->options(function (Forms\Get $get) {
                                                $user = Auth::user();
                                                $query = Catequista::query();

                                                if ($user && $user->hasRole(['coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese'])) {
                                                    $query->where('centro_id', $user->centro_id);
                                                } elseif (filled($get('../centro_id'))) {
                                                    $query->where('centro_id', $get('../centro_id'));
                                                }

                                                return $query->orderBy('nome_completo')->pluck('nome_completo', 'id');
                                            })
                                            ->searchable()
                                            ->preload()
                                            ->helperText('Quem atendeu/processou a ficha (não é necessariamente quem lecciona a turma).'),
                                        Forms\Components\DatePicker::make('data_atendimento')
                                            ->label('Data de Atendimento')
                                            ->required()
                                            ->default(now()),
                                        Forms\Components\Select::make('estado')
                                            ->label('Estado')
                                            ->options([
                                                EstadoInscricao::Inscrito->value => 'Inscrito',
                                                EstadoInscricao::Aprovado->value => 'Aprovado',
                                                EstadoInscricao::Reprovado->value => 'Reprovado',
                                                EstadoInscricao::Desistente->value => 'Desistente',
                                            ])
                                            ->required()
                                            ->default(EstadoInscricao::Inscrito->value),
                                        Forms\Components\Textarea::make('observacoes')
                                            ->label('Observações')
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('nome_completo')
            ->columns([
                Tables\Columns\TextColumn::make('nome_completo')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('centro.nome')
                    ->label('Centro')
                    ->sortable(),
                Tables\Columns\TextColumn::make('data_nascimento')
                    ->label('Data de Nascimento')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sexo')
                    ->label('Sexo')
                    ->formatStateUsing(fn (string $state) => $state === 'M' ? 'Masculino' : 'Feminino'),
                Tables\Columns\TextColumn::make('telefone')
                    ->label('Telefone')
                    ->placeholder('-'),
                // As duas colunas seguintes nao sao atributos reais — leem a
                // inscricao do catequizando no ano lectivo actualmente
                // filtrado (self::anoLetivoIdFiltrado()), mesma fonte de
                // verdade usada pelas accoes "Dados de Catequese" e
                // "Confirmar para o Próximo Ano Lectivo" abaixo.
                Tables\Columns\TextColumn::make('ano_catequetico_actual')
                    ->label('Ano de Catequese')
                    ->getStateUsing(fn (Catequizando $record, HasTable $livewire) => self::inscricaoNoFiltroActual($record, $livewire)?->anoCatequetico?->nome)
                    ->placeholder('Sem inscrição'),
                Tables\Columns\TextColumn::make('tipo_inscricao_actual')
                    ->label('Tipo de Inscrição')
                    ->badge()
                    ->getStateUsing(fn (Catequizando $record, HasTable $livewire) => self::inscricaoNoFiltroActual($record, $livewire)?->tipo)
                    ->formatStateUsing(fn (?TipoInscricao $state) => $state?->label())
                    ->color(fn (?TipoInscricao $state) => $state?->color() ?? 'gray')
                    ->placeholder('Sem inscrição'),
                Tables\Columns\TextColumn::make('estado_inscricao_actual')
                    ->label('Estado da Inscrição')
                    ->badge()
                    ->getStateUsing(fn (Catequizando $record, HasTable $livewire) => self::inscricaoNoFiltroActual($record, $livewire)?->estado)
                    ->formatStateUsing(fn (?EstadoInscricao $state) => self::labelEstadoInscricao($state))
                    ->colors([
                        'warning' => fn ($state) => $state === EstadoInscricao::Inscrito,
                        'success' => fn ($state) => $state === EstadoInscricao::Aprovado,
                        'danger' => fn ($state) => in_array($state, [EstadoInscricao::Reprovado, EstadoInscricao::Cancelado], true),
                        'gray' => fn ($state) => $state === EstadoInscricao::Desistente,
                    ])
                    ->placeholder('Sem inscrição'),
                Tables\Columns\IconColumn::make('status')
                    ->label('Activo')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->status === 'ativo'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            // Filtro de Ano Lectivo visivel acima da tabela (nao escondido no
            // menu de filtros) — pedido do utilizador: e o filtro principal
            // do modulo, deve estar sempre a vista, com o ano em curso por
            // omissao.
            ->filtersLayout(Tables\Enums\FiltersLayout::AboveContent)
            ->filters([
                Tables\Filters\SelectFilter::make('ano_letivo')
                    ->label('Ano Lectivo')
                    ->options(fn () => AnoLetivo::orderByDesc('nome')->pluck('nome', 'id'))
                    ->default(fn () => AnoLetivo::where('status', 'em_curso')->value('id'))
                    ->query(function (Builder $query, array $data): Builder {
                        if (blank($data['value'] ?? null)) {
                            return $query;
                        }

                        // So filtra QUEM aparece na lista (quem tem inscricao
                        // nesse ano) — os dados do proprio Catequizando
                        // continuam sempre os actuais, nunca versionados por
                        // ano (so a Inscricao e que e anual).
                        return $query->whereHas('inscricoes', fn (Builder $q) => $q->where('ano_letivo_id', $data['value']));
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'ativo' => 'Activo',
                        'inativo' => 'Inactivo',
                    ]),
                Tables\Filters\SelectFilter::make('sexo')
                    ->label('Sexo')
                    ->options([
                        'M' => 'Masculino',
                        'F' => 'Feminino',
                    ]),
                Tables\Filters\TrashedFilter::make(),
            ])
            ->headerActions([
                self::accaoExportarComEstado(
                    opcoesEstado: ['todos' => 'Todos', 'ativo' => 'Activo', 'inativo' => 'Inactivo'],
                    estadoPorOmissao: 'ativo',
                    rotaExcel: 'relatorios.catequizandos.excel',
                    rotaPdf: 'relatorios.catequizandos.pdf',
                    comAnoLectivo: true,
                    comCentro: true,
                ),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('verFicha')
                        ->label('Ver ficha')
                        ->icon('heroicon-o-eye')
                        ->url(fn (Catequizando $record): string => static::getUrl('view', ['record' => $record])),
                    Tables\Actions\EditAction::make(),
                    self::accaoDadosCatequese(),
                    self::accaoConfirmarProximoAno(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Ano lectivo actualmente seleccionado no filtro da lista — com
     * fallback para o ano "em_curso" quando o filtro ainda nao montou (ex.:
     * chamado a partir de outro contexto). Usado por todas as colunas e
     * accoes que dependem do ano seleccionado, para nunca desalinhar.
     */
    private static function anoLetivoIdFiltrado(HasTable $livewire): ?int
    {
        $valor = $livewire->getTableFilterState('ano_letivo')['value'] ?? null;

        if (filled($valor)) {
            return (int) $valor;
        }

        return AnoLetivo::where('status', 'em_curso')->value('id');
    }

    private static function inscricaoNoFiltroActual(Catequizando $record, HasTable $livewire): ?Inscricao
    {
        return $record->inscricaoNoAnoLetivo(self::anoLetivoIdFiltrado($livewire));
    }

    /**
     * Ano lectivo seguinte ao da inscricao actual que ja esteja "em_curso"
     * nesta paroquia — pedido do utilizador: a accao "Confirmar para o
     * Próximo Ano Lectivo" so pode abrir/avancar quando este existir,
     * para nunca deixar confirmar por engano para o mesmo ano lectivo em
     * que o catequizando ja esta (o que aconteceria se nao houvesse ainda
     * nenhum ano lectivo seguinte marcado como activo). paroquia_id
     * explicito (nao so a ParoquiaScope) pelo mesmo motivo de defesa em
     * profundidade do numero_ficha (docs/modulos/catequese.md secc. 28).
     */
    private static function proximoAnoLetivoActivo(Inscricao $inscricaoActual): ?AnoLetivo
    {
        return AnoLetivo::withoutGlobalScopes()
            ->where('paroquia_id', $inscricaoActual->paroquia_id)
            ->where('status', 'em_curso')
            ->where('data_inicio', '>', $inscricaoActual->anoLetivo->data_inicio)
            ->orderBy('data_inicio')
            ->first();
    }

    private static function labelEstadoInscricao(?EstadoInscricao $estado): ?string
    {
        return match ($estado) {
            EstadoInscricao::Inscrito => 'Inscrito',
            EstadoInscricao::Aprovado => 'Aprovado',
            EstadoInscricao::Reprovado => 'Reprovado',
            EstadoInscricao::Desistente => 'Desistente',
            EstadoInscricao::Cancelado => 'Cancelado',
            null => null,
        };
    }

    /**
     * Modal so-leitura com a ficha de catequese do catequizando no ano
     * lectivo filtrado — evita ter de abrir o InscricaoResource so para ver
     * o essencial; o botao no rodape do modal continua a abrir a ficha
     * completa para quem precisar de editar.
     */
    private static function accaoDadosCatequese(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('dadosCatequese')
            ->label('Dados de Catequese')
            ->icon('heroicon-o-book-open')
            ->modalHeading('Dados de Catequese')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Fechar')
            ->disabled(fn (Catequizando $record, HasTable $livewire): bool => ! self::inscricaoNoFiltroActual($record, $livewire))
            ->tooltip(fn (Catequizando $record, HasTable $livewire): ?string => self::inscricaoNoFiltroActual($record, $livewire)
                ? null
                : 'Sem inscrição no ano lectivo seleccionado.')
            ->extraModalFooterActions(fn (Catequizando $record, HasTable $livewire): array => array_filter([
                ($inscricao = self::inscricaoNoFiltroActual($record, $livewire))
                    ? Tables\Actions\Action::make('abrirFichaCompleta')
                        ->label('Abrir ficha completa')
                        ->url(InscricaoResource::getUrl('edit', ['record' => $inscricao]))
                        ->openUrlInNewTab()
                        ->button()
                    : null,
            ]))
            ->form(function (Catequizando $record, HasTable $livewire): array {
                $inscricao = self::inscricaoNoFiltroActual($record, $livewire);

                if (! $inscricao) {
                    return [];
                }

                $turma = $inscricao->turmaAtiva?->turma;

                return [
                    Forms\Components\Placeholder::make('numero_ficha')
                        ->label('Nº Ficha')
                        ->content($inscricao->numero_ficha),
                    Forms\Components\Placeholder::make('tipo')
                        ->label('Tipo')
                        ->content($inscricao->tipo->label()),
                    Forms\Components\Placeholder::make('ano_catequetico')
                        ->label('Ano de Catequese')
                        ->content($inscricao->anoCatequetico?->nome ?? '-'),
                    Forms\Components\Placeholder::make('sacramentos')
                        ->label('Sacramento(s)')
                        ->content($inscricao->sacramentos->pluck('nome')->implode(', ') ?: '-'),
                    Forms\Components\Placeholder::make('estado')
                        ->label('Estado')
                        ->content(self::labelEstadoInscricao($inscricao->estado)),
                    Forms\Components\Placeholder::make('catequista')
                        ->label('Catequista')
                        ->content($inscricao->catequista?->nome_completo ?? '-'),
                    Forms\Components\Placeholder::make('turma')
                        ->label('Turma actual')
                        ->content($turma?->descricaoCurta() ?? 'Sem turma'),
                ];
            });
    }

    /**
     * Progressao ano-a-ano (docs/modulos/catequese.md secc. 7): gera uma
     * nova Inscricao (tipo=confirmacao) ligada a actual via
     * inscricao_anterior_id. Decisao do utilizador: qualquer estado da
     * inscricao actual excepto "cancelado" habilita a confirmacao (desvio
     * consciente da nota original "so aprovado progride").
     *
     * Duas situacoes bloqueiam o progresso, tratadas de forma diferente
     * (pedido do utilizador, secc. 29/30): sem inscricao no ano filtrado a
     * accao fica mesmo desactivada (nao ha nada para confirmar a partir
     * desta linha); sem ano lectivo seguinte "em_curso" a accao continua
     * clicavel, mas o modal abre um alerta explicativo em vez do
     * formulario — mais visivel do que um botao cinzento com um tooltip
     * que passa despercebido.
     */
    private static function accaoConfirmarProximoAno(): Tables\Actions\Action
    {
        $semAnoSeguinteActivo = function (Catequizando $record, HasTable $livewire): bool {
            $inscricaoActual = self::inscricaoNoFiltroActual($record, $livewire);

            return $inscricaoActual !== null && ! self::proximoAnoLetivoActivo($inscricaoActual);
        };

        return Tables\Actions\Action::make('confirmarProximoAno')
            ->label('Confirmar para o Próximo Ano Lectivo')
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (): bool => Auth::user()?->can('create', Inscricao::class) ?? false)
            ->disabled(fn (Catequizando $record, HasTable $livewire): bool => ! self::inscricaoNoFiltroActual($record, $livewire))
            ->tooltip(fn (Catequizando $record, HasTable $livewire): ?string => self::inscricaoNoFiltroActual($record, $livewire)
                ? null
                : 'Sem inscrição no ano lectivo seleccionado.')
            ->modalHeading(fn (Catequizando $record, HasTable $livewire): string => $semAnoSeguinteActivo($record, $livewire)
                ? 'Ano Lectivo Seguinte Ainda Não Disponível'
                : 'Confirmar para o Próximo Ano Lectivo')
            ->modalDescription(fn (Catequizando $record, HasTable $livewire): ?string => $semAnoSeguinteActivo($record, $livewire)
                ? 'O próximo ano lectivo ainda não entrou em vigor nesta paróquia. Contacte o administrador ou o coordenador de catequese para criar e activar o novo ano lectivo antes de confirmar catequizandos.'
                : null)
            ->modalIcon(fn (Catequizando $record, HasTable $livewire): ?string => $semAnoSeguinteActivo($record, $livewire) ? 'heroicon-o-exclamation-triangle' : null)
            ->modalIconColor(fn (Catequizando $record, HasTable $livewire): ?string => $semAnoSeguinteActivo($record, $livewire) ? 'warning' : null)
            ->modalCancelAction(fn (Catequizando $record, HasTable $livewire): ?bool => $semAnoSeguinteActivo($record, $livewire) ? false : null)
            ->modalSubmitAction(fn (Catequizando $record, HasTable $livewire): ?bool => $semAnoSeguinteActivo($record, $livewire) ? false : null)
            ->modalWidth(fn (Catequizando $record, HasTable $livewire): ?string => $semAnoSeguinteActivo($record, $livewire) ? 'sm' : null)
            ->form(function (Catequizando $record, HasTable $livewire) use ($semAnoSeguinteActivo): array {
                $inscricaoActual = self::inscricaoNoFiltroActual($record, $livewire);

                if (! $inscricaoActual || $semAnoSeguinteActivo($record, $livewire)) {
                    return [];
                }

                $proximoAnoLetivoId = self::proximoAnoLetivoActivo($inscricaoActual)?->id;

                $proximoAnoCatequeticoId = $inscricaoActual->anoCatequetico
                    ? AnoCatequetico::where('ordem', '>', $inscricaoActual->anoCatequetico->ordem)
                        ->orderBy('ordem')
                        ->value('id')
                    : null;

                return [
                    Forms\Components\Select::make('ano_letivo_id')
                        ->label('Ano Lectivo (destino)')
                        ->options(fn () => AnoLetivo::orderByDesc('nome')->pluck('nome', 'id'))
                        ->default($proximoAnoLetivoId)
                        ->required()
                        ->rule(function () use ($record): Closure {
                            return function (string $attribute, $value, Closure $fail) use ($record): void {
                                $existe = Inscricao::where('catequizando_id', $record->id)
                                    ->where('ano_letivo_id', $value)
                                    ->where('estado', '!=', EstadoInscricao::Cancelado->value)
                                    ->exists();

                                if ($existe) {
                                    $fail('Já existe uma inscrição activa para este catequizando nesse ano lectivo.');
                                }
                            };
                        }),
                    Forms\Components\Select::make('ano_catequetico_id')
                        ->label('Ano de Catequese')
                        ->options(fn () => AnoCatequetico::orderBy('ordem')->pluck('nome', 'id'))
                        ->default($proximoAnoCatequeticoId ?? $inscricaoActual->ano_catequetico_id)
                        ->required(),
                    Forms\Components\Select::make('sacramentos')
                        ->label('Sacramento(s)')
                        ->options(fn () => Sacramento::orderBy('ordem')->pluck('nome', 'id'))
                        ->multiple()
                        ->preload()
                        ->default($inscricaoActual->sacramentos->pluck('id')->all())
                        ->required(),
                    Forms\Components\Select::make('catequista_id')
                        ->label('Catequista Responsável')
                        ->options(fn () => Catequista::query()
                            ->when($record->centro_id, fn (Builder $q) => $q->where('centro_id', $record->centro_id))
                            ->orderBy('nome_completo')
                            ->pluck('nome_completo', 'id'))
                        ->searchable()
                        ->preload()
                        ->helperText('Quem atendeu/processou a ficha (não é necessariamente quem lecciona a turma).'),
                    Forms\Components\DatePicker::make('data_atendimento')
                        ->label('Data de Atendimento')
                        ->default(now())
                        ->required(),
                    Forms\Components\Textarea::make('observacoes')
                        ->label('Observações')
                        ->columnSpanFull(),
                ];
            })
            ->action(function (array $data, Catequizando $record, HasTable $livewire) use ($semAnoSeguinteActivo): void {
                $inscricaoActual = self::inscricaoNoFiltroActual($record, $livewire);

                // Defensivo: o botao de submeter fica escondido
                // (modalSubmitAction) sempre que nao ha ano lectivo seguinte
                // activo, mas nunca confiar so na UI para uma gravacao.
                if (! $inscricaoActual || $semAnoSeguinteActivo($record, $livewire)) {
                    return;
                }

                $sacramentos = $data['sacramentos'] ?? [];
                unset($data['sacramentos']);

                $novaInscricao = DB::transaction(function () use ($data, $record, $inscricaoActual, $sacramentos): Inscricao {
                    $inscricao = Inscricao::create([
                        ...$data,
                        'paroquia_id' => $record->paroquia_id,
                        'centro_id' => $record->centro_id,
                        'catequizando_id' => $record->id,
                        'tipo' => TipoInscricao::Confirmacao->value,
                        'inscricao_anterior_id' => $inscricaoActual->id,
                        'estado' => EstadoInscricao::Inscrito->value,
                    ]);

                    $inscricao->sacramentos()->sync($sacramentos);

                    return $inscricao;
                });

                Notification::make()
                    ->title('Catequizando confirmado para o novo ano lectivo')
                    ->success()
                    ->actions([
                        NotificationAction::make('abrir')
                            ->label('Abrir ficha')
                            ->url(InscricaoResource::getUrl('edit', ['record' => $novaInscricao]))
                            ->openUrlInNewTab()
                            ->button(),
                    ])
                    ->send();
            });
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['inscricoes.anoCatequetico', 'inscricoes.sacramentos', 'inscricoes.anoLetivo', 'inscricoes.catequista', 'inscricoes.turmaAtiva.turma']);

        $user = Auth::user();

        // A ParoquiaScope ja limita a paroquia; reforca para o centro proprio
        // nos papeis que so gerem/leem o seu centro (docs/modulos/catequese.md
        // seccao 2).
        if ($user && $user->hasRole(['coordenador_catequese_centro', 'secretario_catequese', 'tesoureiro_catequese'])) {
            $query->where('centro_id', $user->centro_id);
        }

        return $query;
    }

    public static function getRelations(): array
    {
        return [
            CentrosRelationManager::class,
            InscricoesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCatequizandos::route('/'),
            'create' => Pages\CreateCatequizando::route('/create'),
            'view' => Pages\ViewCatequizando::route('/{record}'),
            'edit' => Pages\EditCatequizando::route('/{record}/edit'),
        ];
    }
}
