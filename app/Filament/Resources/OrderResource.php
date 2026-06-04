<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Area;
use App\Models\Company;
use App\Models\User;
use App\Models\Format;
use App\Models\Master;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\PaymentSchedule;
use App\Models\Status;
use App\Models\Type;
use App\Models\UserType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationGroup = 'Órdenes';

    protected static ?string $modelLabel = 'Orden';

    protected static ?string $pluralModelLabel = 'Mis Órdenes';

    protected static ?int $navigationSort = 1;

    // ──────────────────────────────────────────────────────────────────────────
    // QUERY — filtrar por rol
    // ──────────────────────────────────────────────────────────────────────────

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        $query = parent::getEloquentQuery()->with(['company', 'type', 'detail', 'history', 'responsible']);

        // AA solo ve sus propias órdenes
        if ($user->user_type === 'AA') {
            $query->where('user_responsible', $user->id);
        }

        // Obtener estados permitidos para este rol desde politicas
        $allowedStatuses = DB::table('orders_politicas')
            ->where('user_type', $user->user_type)
            ->pluck('status_id')
            ->toArray();

        // Si no hay políticas definidas, ver todas (fallback)
        if (empty($allowedStatuses)) {
            $allowedStatuses = [0]; // Esto no matchea nada, lista vacía
        }

        // Estados sin lógica especial
        $normalStatuses = array_diff($allowedStatuses, [5]);

        $query->where(function ($q) use ($allowedStatuses, $normalStatuses, $user) {
            // Estados normales (sin whereHas)
            if (!empty($normalStatuses)) {
                $q->whereIn('status', $normalStatuses);
            }

            // Estado 5 (OBSERVADO) con lógica de whereHas
            if (in_array(5, $allowedStatuses)) {
                $q->orWhere(function ($q2) use ($user) {
                    $q2->where('status', 5)
                       ->whereHas('history', fn ($q3) => $q3->where('to_status', 5)->where('to_user', $user->user_type)->latest('id'));
                });
            }
        });

        return $query->latest();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // FORM (not used — EditOrder/CreateOrder own their forms)
    // ──────────────────────────────────────────────────────────────────────────

    public static function form(Form $form): Form
    {
        return $form->schema([]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // TABLE
    // ──────────────────────────────────────────────────────────────────────────

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')->sortable()->weight('bold')->copyable(),

                Tables\Columns\TextColumn::make('company.name')
                    ->label('Empresa')->sortable()->limit(25),

                Tables\Columns\TextColumn::make('responsible.name')
                    ->label('Responsable')->default('—')->limit(22)
                    ->tooltip(fn ($record) => $record->responsible?->name),

                Tables\Columns\TextColumn::make('title')
                    ->label('Título')->limit(40)
                    ->tooltip(fn ($record) => $record->title),

                Tables\Columns\TextColumn::make('format_id')
                    ->label('Formato')->badge()->color('gray'),

                Tables\Columns\TextColumn::make('paymentSchedule.name')
                    ->label('Programación')->default('—')->limit(25)
                    ->tooltip(fn ($record) => $record->paymentSchedule?->name),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')->badge()
                    ->formatStateUsing(fn ($state) => static::statusLabel((int) $state))
                    ->color(fn ($state) => static::statusColor((int) $state)),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Fecha de creación')->date('d/m/Y')->sortable(),

                Tables\Columns\TextColumn::make('fecha_pago')
                    ->label('Fecha de vcto.')
                    ->getStateUsing(fn ($record) =>
                        $record->detail?->expiration_date
                            ? \Carbon\Carbon::parse($record->detail->expiration_date)->format('d/m/Y')
                            : '—'
                    ),

                Tables\Columns\TextColumn::make('monto_neto')
                    ->label('Monto a pagar')
                    ->getStateUsing(function ($record) {
                        $detail  = $record->detail;
                        $amount  = floatval($detail?->amount_neto ?? 0) > 0
                            ? floatval($detail->amount_neto)
                            : floatval($detail?->amount_ref ?? 0);
                        if ($amount == 0) return '—';
                        $cur = ($detail?->currency === 'USD') ? '$ ' : 'S/ ';
                        return $cur . number_format($amount, 2);
                    }),
            ])
            ->filters([

                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options(static::statusOptionsForCurrentUser())
                    ->placeholder('Todos los estados')
                    ->searchable()
                    ->hidden(fn () => empty(static::statusOptionsForCurrentUser())),

                Tables\Filters\SelectFilter::make('format_id')
                    ->label('Tipo de Orden')
                    ->options(Format::orderBy('description')->pluck('description', 'abrev'))
                    ->placeholder('Todos los tipos')
                    ->searchable(),

                Tables\Filters\SelectFilter::make('area_id')
                    ->label('Área')
                    ->options(Area::orderBy('description')->pluck('description', 'id'))
                    ->placeholder('Todas las áreas')
                    ->searchable()
                    ->modifyQueryUsing(fn (Builder $query, array $data): Builder =>
                        $data['value']
                            ? $query->whereHas('detail', fn ($q) => $q->where('area_id', $data['value']))
                            : $query
                    ),

                Tables\Filters\SelectFilter::make('payment_schedule_id')
                    ->label('Programación')
                    ->options(PaymentSchedule::orderBy('name')->pluck('name', 'id'))
                    ->placeholder('Todas las programaciones')
                    ->searchable(),

                Tables\Filters\Filter::make('created_range')
                    ->form([
                        Forms\Components\TextInput::make('rango')
                            ->label('Fecha de creación')
                            ->placeholder('Seleccionar rango de fechas...')
                            ->suffixIcon('heroicon-m-calendar-days')
                            ->extraInputAttributes([
                                'readonly'     => true,
                                'style'        => 'cursor:pointer',
                                'autocomplete' => 'off',
                                'x-init'       => "
                                    flatpickr(\$el, {
                                        mode: 'range',
                                        showMonths: 2,
                                        dateFormat: 'Y-m-d',
                                        locale: 'es',
                                        appendTo: document.body,
                                        onClose: function(dates, dateStr, inst) {
                                            \$el.dispatchEvent(new Event('input', { bubbles: true }));
                                        }
                                    });
                                ",
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['rango'])) return $query;
                        $parts = array_map('trim', explode(' to ', $data['rango']));
                        if (count($parts) !== 2) return $query;
                        return $query
                            ->whereDate('created_at', '>=', $parts[0])
                            ->whereDate('created_at', '<=', $parts[1]);
                    })
                    ->indicateUsing(function (array $data): array {
                        if (empty($data['rango'])) return [];
                        $parts = array_map('trim', explode(' to ', $data['rango']));
                        if (count($parts) !== 2) return [];
                        return ['Solicitud: ' . \Carbon\Carbon::parse($parts[0])->format('d/m/Y') . ' — ' . \Carbon\Carbon::parse($parts[1])->format('d/m/Y')];
                    }),

Tables\Filters\Filter::make('buscar')
                    ->form([
                        Forms\Components\TextInput::make('keyword')
                            ->label('Buscar')
                            ->placeholder('Código, título, empresa...')
                            ->prefixIcon('heroicon-m-magnifying-glass')
                            ->live(debounce: 500),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['keyword'])) return $query;
                        $k = '%' . $data['keyword'] . '%';
                        return $query->where(fn ($q) => $q
                            ->where('code', 'like', $k)
                            ->orWhere('title', 'like', $k)
                            ->orWhereHas('company', fn ($q2) => $q2->where('name', 'like', $k))
                        );
                    })
                    ->indicateUsing(fn (array $data): array =>
                        filled($data['keyword']) ? ['Búsqueda: "' . $data['keyword'] . '"'] : []
                    ),
            ])
            ->actions(static::buildTableActions())
            ->bulkActions([])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->filtersFormColumns(3)
            ->defaultSort('created_at', 'desc')
            ->striped();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // TABLE ACTIONS — por rol
    // ──────────────────────────────────────────────────────────────────────────

    private static function buildTableActions(): array
    {
        return [
            // ── Lápiz (editar) ─────────────────────────────────────────────
            Tables\Actions\Action::make('edit')
                ->label('')->icon('heroicon-o-pencil-square')->color('primary')
                ->tooltip('Editar')
                ->url(fn ($record) => static::getUrl('edit', ['record' => $record]))
                ->visible(fn ($record) => static::canEditRecord($record)),

            // ── Ojo (ver) ──────────────────────────────────────────────────
            Tables\Actions\Action::make('view')
                ->label('')->icon('heroicon-o-eye')->color('gray')
                ->tooltip('Ver')
                ->url(fn ($record) => static::getUrl('view', ['record' => $record]))
                ->visible(fn ($record) => !static::canEditRecord($record)),

            // ── GA: Aprobar ────────────────────────────────────────────────
            Tables\Actions\Action::make('ga_approve')
                ->label('')->tooltip('Aprobar')->icon('heroicon-o-check-circle')->color('success')
                ->requiresConfirmation()
                ->modalHeading('Aprobar orden')
                ->modalDescription('La orden pasará a Gerencia Financiera.')
                ->visible(fn ($record) => auth()->user()->user_type === 'GA' && $record->status === 2)
                ->action(function ($record) {
                    $user = auth()->user();
                    DB::transaction(function () use ($record, $user) {
                        OrderHistory::create([
                            'from_user' => 'GA', 'to_user' => 'GF',
                            'from_status' => 2, 'to_status' => 3,
                            'coment' => '', 'order_id' => $record->id,
                            'created_by' => $user->id, 'updated_by' => $user->id,
                        ]);
                        $record->update(['status' => 3, 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Orden aprobada')->success()->send();
                }),

            // ── GA: Observar ───────────────────────────────────────────────
            Tables\Actions\Action::make('ga_observe')
                ->label('')->tooltip('Observar')->icon('heroicon-o-exclamation-triangle')->color('warning')
                ->modalHeading('Registrar observación')->modalSubmitActionLabel('Enviar observación')
                ->visible(fn ($record) => auth()->user()->user_type === 'GA' && $record->status === 2)
                ->form(static::observeForm())
                ->action(function (array $data, $record) {
                    $user    = auth()->user();
                    $desc    = Master::where('main', 20)->where('value', $data['obs_type'])->value('description');
                    $comment = '[' . $desc . '] ' . $data['obs_comment'];
                    DB::transaction(function () use ($record, $user, $comment) {
                        OrderHistory::create([
                            'from_user' => 'GA', 'to_user' => 'AA',
                            'from_status' => 2, 'to_status' => 5,
                            'coment' => $comment, 'order_id' => $record->id,
                            'created_by' => $user->id, 'updated_by' => $user->id,
                        ]);
                        $record->update(['status' => 5, 'motive_observation' => $comment, 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Observación registrada')->warning()->send();
                }),

            // ── GA: Rechazar ───────────────────────────────────────────────
            Tables\Actions\Action::make('ga_reject')
                ->label('')->tooltip('Rechazar')->icon('heroicon-o-x-circle')->color('danger')
                ->modalHeading('Rechazar orden')->modalSubmitActionLabel('Confirmar rechazo')
                ->visible(fn ($record) => auth()->user()->user_type === 'GA' && $record->status === 2)
                ->form([
                    Forms\Components\Textarea::make('reject_reason')
                        ->label('Motivo del rechazo')->required()->rows(3),
                ])
                ->action(function (array $data, $record) {
                    $user = auth()->user();
                    DB::transaction(function () use ($record, $user, $data) {
                        OrderHistory::create([
                            'from_user' => 'GA', 'to_user' => 'AA',
                            'from_status' => 2, 'to_status' => 4,
                            'coment' => $data['reject_reason'], 'order_id' => $record->id,
                            'created_by' => $user->id, 'updated_by' => $user->id,
                        ]);
                        $record->update(['status' => 4, 'motive_cancelation' => $data['reject_reason'], 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Orden rechazada')->danger()->send();
                }),

            // ── GF: Aprobar ────────────────────────────────────────────────
            Tables\Actions\Action::make('gf_approve')
                ->label('')->tooltip('Aprobar')->icon('heroicon-o-check-circle')->color('success')
                ->modalHeading('Aprobar — Cuenta de origen')->modalSubmitActionLabel('Confirmar y aprobar')
                ->visible(fn ($record) => auth()->user()->user_type === 'GF' && $record->status === 3)
                ->fillForm(fn ($record) => [
                    'source_account_ref' => static::companyAccountString($record->company_id),
                ])
                ->form(function ($record) {
                    $info = static::companyAccountString($record->company_id);
                    return [
                        Forms\Components\Placeholder::make('account_info')
                            ->label('Cuenta de salida')
                            ->content($info
                                ? new HtmlString('<span style="font-weight:600;color:#1e293b">' . e($info) . '</span>')
                                : new HtmlString('<span style="color:#dc2626;font-weight:600">⛔ Esta empresa no tiene cuenta de origen configurada. Configure la cuenta en el módulo de Empresas antes de aprobar.</span>')),
                        Forms\Components\TextInput::make('source_account_ref')
                            ->label('Referencia / N° de operación')
                            ->placeholder('Ej. 0012345678')
                            ->required((bool) $info)
                            ->maxLength(200)
                            ->hidden(!$info),
                    ];
                })
                ->action(function (array $data, $record, Tables\Actions\Action $action) {
                    $info = static::companyAccountString($record->company_id);
                    if (!$info) {
                        Notification::make()
                            ->title('Sin cuenta de origen')
                            ->body('Configure la cuenta bancaria de la empresa antes de aprobar.')
                            ->danger()->send();
                        $action->halt();
                        return;
                    }
                    $user      = auth()->user();
                    $afLabel   = UserType::where('prefijo', 'AF')->value('description') ?? 'AF';
                    DB::transaction(function () use ($record, $user, $data) {
                        $record->detail()->updateOrCreate(
                            ['order_id' => $record->id],
                            ['source_account' => $data['source_account_ref'] ?? null, 'updated_by' => $user->id]
                        );
                        OrderHistory::create([
                            'from_user' => 'GF', 'to_user' => 'AF',
                            'from_status' => 3, 'to_status' => 6,
                            'coment' => 'Cuenta origen: ' . ($data['source_account_ref'] ?? '—'),
                            'order_id' => $record->id,
                            'created_by' => $user->id, 'updated_by' => $user->id,
                        ]);
                        $record->update(['status' => 6, 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Orden aprobada — enviada a ' . $afLabel)->success()->send();
                }),

            // ── GF: Observar ───────────────────────────────────────────────
            Tables\Actions\Action::make('gf_observe')
                ->label('')->tooltip('Observar')->icon('heroicon-o-exclamation-triangle')->color('warning')
                ->modalHeading('Registrar observación')->modalSubmitActionLabel('Enviar observación')
                ->visible(fn ($record) => auth()->user()->user_type === 'GF' && $record->status === 3)
                ->form(static::observeForm())
                ->action(function (array $data, $record) {
                    $user    = auth()->user();
                    $desc    = Master::where('main', 20)->where('value', $data['obs_type'])->value('description');
                    $comment = '[' . $desc . '] ' . $data['obs_comment'];
                    DB::transaction(function () use ($record, $user, $comment) {
                        OrderHistory::create([
                            'from_user' => 'GF', 'to_user' => 'GA',
                            'from_status' => 3, 'to_status' => 5,
                            'coment' => $comment, 'order_id' => $record->id,
                            'created_by' => $user->id, 'updated_by' => $user->id,
                        ]);
                        $record->update(['status' => 5, 'motive_observation' => $comment, 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Observación registrada')->warning()->send();
                }),

            // ── AA: Observar (solo en status=7) ───────────────────────────
            Tables\Actions\Action::make('aa_observe')
                ->label('')->tooltip('Observar')->icon('heroicon-o-exclamation-triangle')->color('warning')
                ->modalHeading('Registrar observación')->modalSubmitActionLabel('Enviar observación')
                ->visible(fn ($record) => auth()->user()->user_type === 'AA' && $record->status === 7)
                ->form(static::observeForm())
                ->action(function (array $data, $record) {
                    $user    = auth()->user();
                    $desc    = Master::where('main', 20)->where('value', $data['obs_type'])->value('description');
                    $comment = '[' . $desc . '] ' . $data['obs_comment'];
                    DB::transaction(function () use ($record, $user, $comment) {
                        OrderHistory::create([
                            'from_user' => 'AA', 'to_user' => 'AF',
                            'from_status' => 7, 'to_status' => 5,
                            'coment' => $comment, 'order_id' => $record->id,
                            'created_by' => $user->id, 'updated_by' => $user->id,
                        ]);
                        $record->update(['status' => 5, 'motive_observation' => $comment, 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Observación registrada')->warning()->send();
                }),

            // ── AF: Conforme (status=8) ────────────────────────────────────
            Tables\Actions\Action::make('af_conforme')
                ->label('')->tooltip('Conforme')->icon('heroicon-o-check-badge')->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirmar sustento')
                ->modalDescription('La orden pasará a Contabilidad (UC nivel 1).')
                ->visible(fn ($record) => auth()->user()->user_type === 'AF' && $record->status === 8)
                ->action(function ($record) {
                    $user = auth()->user();
                    DB::transaction(function () use ($record, $user) {
                        OrderHistory::create([
                            'from_user' => 'AF', 'to_user' => 'UC',
                            'from_status' => 8, 'to_status' => 9,
                            'coment' => '', 'order_id' => $record->id,
                            'created_by' => $user->id, 'updated_by' => $user->id,
                        ]);
                        $record->update(['status' => 9, 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Sustento confirmado — enviado a Contabilidad')->success()->send();
                }),

            // ── AF: Observar (status=8) ────────────────────────────────────
            Tables\Actions\Action::make('af_observe')
                ->label('')->tooltip('Observar')->icon('heroicon-o-exclamation-triangle')->color('warning')
                ->modalHeading('Registrar observación')->modalSubmitActionLabel('Enviar observación')
                ->visible(fn ($record) => auth()->user()->user_type === 'AF' && $record->status === 8)
                ->form(static::observeForm())
                ->action(function (array $data, $record) {
                    $user    = auth()->user();
                    $desc    = Master::where('main', 20)->where('value', $data['obs_type'])->value('description');
                    $comment = '[' . $desc . '] ' . $data['obs_comment'];
                    DB::transaction(function () use ($record, $user, $comment) {
                        OrderHistory::create([
                            'from_user' => 'AF', 'to_user' => 'AA',
                            'from_status' => 8, 'to_status' => 5,
                            'coment' => $comment, 'order_id' => $record->id,
                            'created_by' => $user->id, 'updated_by' => $user->id,
                        ]);
                        $record->update(['status' => 5, 'motive_observation' => $comment, 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Observación registrada')->warning()->send();
                }),

            // ── UC nivel 1: Ingresar código de registro (status=9) ─────────
            Tables\Actions\Action::make('uc1_code')
                ->label('')->tooltip('Ingresar código')->icon('heroicon-o-hashtag')->color('primary')
                ->modalHeading('Código de Registro Contable')->modalSubmitActionLabel('Guardar y continuar')
                ->visible(fn ($record) => auth()->user()->user_type === 'UC'
                    && (auth()->user()->uc_level ?? 0) === 1
                    && $record->status === 9)
                ->fillForm(fn ($record) => ['codigo_registro' => $record->detail?->codigo_registro])
                ->form([
                    Forms\Components\TextInput::make('codigo_registro')
                        ->label('Código de Registro Contable')
                        ->required()->maxLength(100)->placeholder('Ej. REG-2026-00123'),
                ])
                ->action(function (array $data, $record) {
                    $user = auth()->user();
                    DB::transaction(function () use ($record, $user, $data) {
                        $record->detail()->updateOrCreate(
                            ['order_id' => $record->id],
                            ['codigo_registro' => $data['codigo_registro'], 'updated_by' => $user->id]
                        );
                        OrderHistory::create([
                            'from_user' => 'UC', 'to_user' => 'UC',
                            'from_status' => 9, 'to_status' => 91,
                            'coment' => 'Cód. Registro: ' . $data['codigo_registro'],
                            'order_id' => $record->id,
                            'created_by' => $user->id, 'updated_by' => $user->id,
                        ]);
                        $record->update(['status' => 91, 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Código de registro ingresado')->success()->send();
                }),

            // ── UC nivel 1: Observar (status=9) ───────────────────────────
            Tables\Actions\Action::make('uc1_observe')
                ->label('')->tooltip('Observar')->icon('heroicon-o-exclamation-triangle')->color('warning')
                ->modalHeading('Registrar observación')->modalSubmitActionLabel('Enviar observación')
                ->visible(fn ($record) => auth()->user()->user_type === 'UC'
                    && (auth()->user()->uc_level ?? 0) === 1
                    && $record->status === 9)
                ->form(static::observeForm())
                ->action(fn (array $data, $record) => static::doObserve($data, $record, 9, 'UC', 'AA')),

            // ── UC nivel 2: Ingresar código de banco (status=91) ──────────
            Tables\Actions\Action::make('uc2_code')
                ->label('')->tooltip('Ingresar código')->icon('heroicon-o-hashtag')->color('primary')
                ->modalHeading('Código de Banco')->modalSubmitActionLabel('Guardar y continuar')
                ->visible(fn ($record) => auth()->user()->user_type === 'UC'
                    && (auth()->user()->uc_level ?? 0) === 2
                    && $record->status === 91)
                ->fillForm(fn ($record) => ['codigo_banco' => $record->detail?->codigo_banco])
                ->form([
                    Forms\Components\TextInput::make('codigo_banco')
                        ->label('Código de Banco')
                        ->required()->maxLength(100)->placeholder('Ej. BAN-2026-00456'),
                ])
                ->action(function (array $data, $record) {
                    $user = auth()->user();
                    DB::transaction(function () use ($record, $user, $data) {
                        $record->detail()->updateOrCreate(
                            ['order_id' => $record->id],
                            ['codigo_banco' => $data['codigo_banco'], 'updated_by' => $user->id]
                        );
                        OrderHistory::create([
                            'from_user' => 'UC', 'to_user' => 'UC',
                            'from_status' => 91, 'to_status' => 92,
                            'coment' => 'Cód. Banco: ' . $data['codigo_banco'],
                            'order_id' => $record->id,
                            'created_by' => $user->id, 'updated_by' => $user->id,
                        ]);
                        $record->update(['status' => 92, 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Código de banco ingresado')->success()->send();
                }),

            // ── UC nivel 2: Observar (status=91) ──────────────────────────
            Tables\Actions\Action::make('uc2_observe')
                ->label('')->tooltip('Observar')->icon('heroicon-o-exclamation-triangle')->color('warning')
                ->modalHeading('Registrar observación')->modalSubmitActionLabel('Enviar observación')
                ->visible(fn ($record) => auth()->user()->user_type === 'UC'
                    && (auth()->user()->uc_level ?? 0) === 2
                    && $record->status === 91)
                ->form(static::observeForm())
                ->action(fn (array $data, $record) => static::doObserve($data, $record, 91, 'UC', 'AA')),

            // ── UC nivel 3: Terminar (status=92) ──────────────────────────
            Tables\Actions\Action::make('uc3_finish')
                ->label('')->tooltip('Terminar')->icon('heroicon-o-check-badge')->color('success')
                ->requiresConfirmation()
                ->modalHeading('Cerrar orden')
                ->modalDescription('Se marcará como CERRADA. Esta acción no se puede deshacer.')
                ->visible(fn ($record) => auth()->user()->user_type === 'UC'
                    && (auth()->user()->uc_level ?? 0) === 3
                    && $record->status === 92)
                ->action(function ($record) {
                    $user = auth()->user();
                    DB::transaction(function () use ($record, $user) {
                        OrderHistory::create([
                            'from_user' => 'UC', 'to_user' => '',
                            'from_status' => 92, 'to_status' => 10,
                            'coment' => 'Proceso contable completado.',
                            'order_id' => $record->id,
                            'created_by' => $user->id, 'updated_by' => $user->id,
                        ]);
                        $record->update(['status' => 10, 'updated_by' => $user->id]);
                    });
                    Notification::make()->title('Orden cerrada exitosamente')->success()->send();
                }),

            // ── UC nivel 3: Observar (status=92) ──────────────────────────
            Tables\Actions\Action::make('uc3_observe')
                ->label('')->tooltip('Observar')->icon('heroicon-o-exclamation-triangle')->color('warning')
                ->modalHeading('Registrar observación')->modalSubmitActionLabel('Enviar observación')
                ->visible(fn ($record) => auth()->user()->user_type === 'UC'
                    && (auth()->user()->uc_level ?? 0) === 3
                    && $record->status === 92)
                ->form(static::observeForm())
                ->action(fn (array $data, $record) => static::doObserve($data, $record, 92, 'UC', 'AA')),

            // ── Historial (línea de tiempo) ────────────────────────────────
            Tables\Actions\Action::make('timeline')
                ->label('')->icon('heroicon-o-clock')->color('gray')
                ->tooltip('Historial de movimientos')
                ->modalHeading('Historial de movimientos')
                ->modalContent(fn ($record) => new HtmlString(static::buildHistoryHtmlStatic($record)))
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Cerrar')
                ->modalWidth('4xl'),
        ];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────────────────

    private static function canEditRecord($record): bool
    {
        $user   = auth()->user();
        $role   = $user->user_type;
        $status = (int) $record->status;
        $level  = $user->uc_level ?? 0;

        if ($status === 5) {
            $toUser = OrderHistory::where('order_id', $record->id)
                ->where('to_status', 5)->latest('id')->value('to_user');
            return match ($role) {
                'JA' => $toUser === 'JA',
                'AA' => $toUser === 'AA',
                'GA' => $toUser === 'GA',
                'GF' => $toUser === 'GF',
                'AF' => $toUser === 'AF',
                default => false,
            };
        }

        return match ($role) {
            'JA' => false, // JA solo edita si status=5 to_user=JA (manejado arriba)
            'AA' => in_array($status, [1, 7]),
            'GA' => $status === 2,
            'GF' => $status === 3,
            'AF' => in_array($status, [6, 8]),
            'UC' => match ($level) {
                1 => $status === 9,
                2 => $status === 91,
                3 => $status === 92,
                default => false,
            },
            default => false,
        };
    }

    private static function observeForm(): array
    {
        return [
            Forms\Components\Select::make('obs_type')
                ->label('Tipo de observación')
                ->options(Master::where('main', 20)->orderBy('value')->pluck('description', 'value'))
                ->required()->searchable(),
            Forms\Components\Textarea::make('obs_comment')
                ->label('Comentario')->required()->rows(3)
                ->placeholder('Detalla la observación...'),
        ];
    }

    private static function doObserve(array $data, $record, int $fromStatus, string $fromUser, string $toUser): void
    {
        $user    = auth()->user();
        $desc    = Master::where('main', 20)->where('value', $data['obs_type'])->value('description');
        $comment = '[' . $desc . '] ' . $data['obs_comment'];
        DB::transaction(function () use ($record, $user, $fromStatus, $fromUser, $toUser, $comment) {
            OrderHistory::create([
                'from_user'   => $fromUser, 'to_user' => $toUser,
                'from_status' => $fromStatus, 'to_status' => 5,
                'coment'      => $comment, 'order_id' => $record->id,
                'created_by'  => $user->id, 'updated_by' => $user->id,
            ]);
            $record->update(['status' => 5, 'motive_observation' => $comment, 'updated_by' => $user->id]);
        });
        Notification::make()->title('Observación registrada')->warning()->send();
    }

    private static function companyAccountString(?int $companyId): ?string
    {
        if (!$companyId) return null;
        $company = Company::find($companyId);
        if (!$company || !$company->source_bank) return null;
        return $company->source_bank
            . ' · N° ' . $company->source_account_number
            . ($company->source_cci ? ' · CCI ' . $company->source_cci : '');
    }

    private static function statusOptionsForCurrentUser(): array
    {
        $user = auth()->user();

        $ids = DB::table('orders_politicas')
            ->where('user_type', $user->user_type)
            ->pluck('status_id')
            ->toArray();

        $query = Status::orderBy('id');
        if (!empty($ids)) {
            $query->whereIn('id', $ids);
        }
        return $query->pluck('description', 'id')->toArray();
    }

    private static function statusLabel(int $status): string
    {
        return Status::label($status);
    }

    private static function statusColor(int $status): string
    {
        return match ($status) {
            1, 6  => 'info',
            2, 5  => 'warning',
            3, 7, 9 => 'success',
            4     => 'danger',
            8, 91, 92 => 'primary',
            default => 'gray',
        };
    }

    private static function buildHistoryHtmlStatic(Order $record): string
    {
        $history = OrderHistory::where('order_id', $record->id)->orderByDesc('id')->get();

        if ($history->isEmpty()) {
            return '<p style="color:#94a3b8;text-align:center;padding:32px 0">Sin movimientos registrados.</p>';
        }

        $statusNames = Status::pluck('description', 'id')->toArray();
        $roleNames   = UserType::pluck('description', 'prefijo')->toArray();

        $html  = '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:.82rem">';
        $html .= '<thead><tr style="border-bottom:2px solid #e2e8f0">';
        foreach (['Fecha','Usuario','Movimiento','Comentario'] as $th) {
            $html .= '<th style="padding:8px 12px;font-weight:700;color:#64748b;text-align:left;white-space:nowrap">' . $th . '</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($history as $i => $h) {
            $bg       = $i % 2 === 0 ? '#fff' : '#f8fafc';
            $isObs    = $h->to_status == 5;
            $fromName = $statusNames[$h->from_status] ?? $h->from_status;
            $toName   = $statusNames[$h->to_status]   ?? $h->to_status;
            $fromRole = $roleNames[$h->from_user]     ?? $h->from_user;
            $userName = \App\Models\User::find($h->created_by)?->name ?? '—';
            $date     = $h->created_at ? \Carbon\Carbon::parse($h->created_at)->format('d/m/Y H:i') : '—';
            $comment  = ($h->coment && $h->coment !== '0') ? e($h->coment) : '';

            $fromBg = $isObs ? '#fef3c7' : '#dbeafe';
            $fromFg = $isObs ? '#92400e' : '#1d4ed8';
            $toBg   = $isObs ? '#fee2e2' : '#d1fae5';
            $toFg   = $isObs ? '#991b1b' : '#065f46';
            $cStyle = $isObs ? 'color:#92400e;font-style:italic;background:#fffbeb;padding:2px 6px;border-radius:3px' : 'color:#64748b';

            $html .= "<tr style='background:{$bg};border-bottom:1px solid #f1f5f9'>";
            $html .= "<td style='padding:8px 12px;white-space:nowrap;color:#64748b'>{$date}</td>";
            $html .= "<td style='padding:8px 12px'><span style='font-weight:600;color:#1e293b'>" . e($userName) . "</span><br><span style='font-size:.74rem;color:#94a3b8'>{$fromRole}</span></td>";
            $html .= "<td style='padding:8px 12px;white-space:nowrap'>"
                   . "<span style='background:{$fromBg};color:{$fromFg};font-size:11px;padding:2px 7px;border-radius:4px;font-weight:700'>{$fromName}</span>"
                   . "<span style='color:#94a3b8;margin:0 5px'>→</span>"
                   . "<span style='background:{$toBg};color:{$toFg};font-size:11px;padding:2px 7px;border-radius:4px;font-weight:700'>{$toName}</span></td>";
            $html .= "<td style='padding:8px 12px'>"
                   . ($comment ? "<span style='{$cStyle}'>{$comment}</span>" : '<span style="color:#cbd5e1">—</span>')
                   . "</td>";
            $html .= "</tr>";
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // PAGES
    // ──────────────────────────────────────────────────────────────────────────

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit'   => Pages\EditOrder::route('/{record}/edit'),
            'view'   => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
