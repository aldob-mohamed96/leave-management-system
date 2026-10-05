<?php

namespace App\Filament\Resources;

use App\Enums\LeaveStatus;
use App\Enums\StepStatus;
use App\Exceptions\LeaveRequestException;
use App\Filament\Resources\LeaveRequestResource\Pages;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Rules\Leave\WorkingDaysRule;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * مورد إدارة طلبات الإجازات مع مسار الاعتماد الكامل.
 */
class LeaveRequestResource extends Resource
{
    protected static ?string $model = LeaveRequest::class;

    protected static ?string $navigationGroup = 'طلبات الإجازات';
    protected static ?string $navigationLabel = 'طلبات الإجازة';
    protected static ?string $navigationIcon  = 'heroicon-o-clipboard-document-list';
    protected static ?string $modelLabel      = 'طلب إجازة';
    protected static ?string $pluralModelLabel = 'طلبات الإجازات';

    // -------------------------------------------------------------------------
    // Navigation Badge (pending requests count)
    // -------------------------------------------------------------------------

    public static function getNavigationBadge(): ?string
    {
        // OrganizationScope applies automatically — shows only the logged-in user's org
        return (string) LeaveRequest::pending()->count() ?: null;
    }

    // -------------------------------------------------------------------------
    // Form (shared between Create and Edit pages)
    // -------------------------------------------------------------------------

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('بيانات الطلب')
                ->schema([
                    Forms\Components\Select::make('employee_id')
                        ->label('الموظف')
                        ->options(
                            Employee::withoutGlobalScopes()
                                ->active()
                                ->orderBy('full_name')
                                ->pluck('full_name', 'id')
                                ->toArray()
                        )
                        ->searchable()
                        ->required()
                        ->reactive(),

                    Forms\Components\Select::make('leave_type_id')
                        ->label('نوع الإجازة')
                        ->options(
                            LeaveType::active()
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray()
                        )
                        ->required()
                        ->reactive(),

                    Forms\Components\DatePicker::make('start_date')
                        ->label('تاريخ البداية')
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                            self::recalculateDays($get, $set);
                        }),

                    Forms\Components\DatePicker::make('end_date')
                        ->label('تاريخ النهاية')
                        ->required()
                        ->reactive()
                        ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set) {
                            self::recalculateDays($get, $set);
                        }),

                    Forms\Components\TextInput::make('days')
                        ->label('عدد الأيام (أيام العمل)')
                        ->numeric()
                        ->disabled()
                        ->dehydrated()
                        ->helperText('يُحسب تلقائياً عند اختيار التواريخ، مع استثناء أيام الإجازة الرسمية والعطل الأسبوعية.'),

                    Forms\Components\DatePicker::make('written_at')
                        ->label('تاريخ كتابة الطلب')
                        ->nullable(),

                    Forms\Components\Select::make('substitute_employee_id')
                        ->label('الموظف البديل')
                        ->options(function (Forms\Get $get): array {
                            $selectedId = $get('employee_id');

                            return Employee::withoutGlobalScopes()
                                ->active()
                                ->when($selectedId, fn($q) => $q->where('id', '!=', $selectedId))
                                ->orderBy('full_name')
                                ->pluck('full_name', 'id')
                                ->toArray();
                        })
                        ->searchable()
                        ->nullable(),

                    Forms\Components\Textarea::make('reason')
                        ->label('السبب')
                        ->nullable()
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Forms\Components\Section::make('معلومات الرصيد')
                ->schema([
                    Forms\Components\TextInput::make('balance_entitled')
                        ->label('الرصيد المستحق')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('balance_used')
                        ->label('المستخدم')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(false),

                    Forms\Components\TextInput::make('balance_remaining')
                        ->label('المتبقي')
                        ->numeric()
                        ->disabled()
                        ->dehydrated(false),
                ])
                ->columns(3)
                ->disabled()
                ->hiddenOn('create'),
        ]);
    }

    /**
     * إعادة حساب أيام العمل بين التاريخين.
     */
    private static function recalculateDays(Forms\Get $get, Forms\Set $set): void
    {
        $start = $get('start_date');
        $end   = $get('end_date');

        if (! $start || ! $end) {
            return;
        }

        $rule = new WorkingDaysRule();
        $rule->setData(['start_date' => $start, 'end_date' => $end]);
        $rule->validate('days', 0, fn($msg) => null);

        if ($rule->calculatedDays > 0) {
            $set('days', $rule->calculatedDays);
        }
    }

    // -------------------------------------------------------------------------
    // Infolist (View page)
    // -------------------------------------------------------------------------

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('بيانات الطلب')
                ->schema([
                    Infolists\Components\TextEntry::make('number')
                        ->label('رقم الطلب')
                        ->copyable(),

                    Infolists\Components\TextEntry::make('employee.full_name')
                        ->label('الموظف'),

                    Infolists\Components\TextEntry::make('leaveType.name')
                        ->label('نوع الإجازة'),

                    Infolists\Components\TextEntry::make('organization.name')
                        ->label('الجهة'),

                    Infolists\Components\TextEntry::make('start_date')
                        ->label('من')
                        ->date('Y-m-d'),

                    Infolists\Components\TextEntry::make('end_date')
                        ->label('إلى')
                        ->date('Y-m-d'),

                    Infolists\Components\TextEntry::make('days')
                        ->label('عدد الأيام'),

                    Infolists\Components\TextEntry::make('written_at')
                        ->label('تاريخ الكتابة')
                        ->date('Y-m-d'),

                    Infolists\Components\TextEntry::make('status')
                        ->label('الحالة')
                        ->badge()
                        ->formatStateUsing(fn(LeaveStatus $state): string => $state->label())
                        ->color(fn(LeaveStatus $state): string => $state->color()),

                    Infolists\Components\TextEntry::make('current_stage')
                        ->label('المرحلة الحالية')
                        ->placeholder('—'),

                    Infolists\Components\TextEntry::make('reason')
                        ->label('السبب')
                        ->columnSpanFull()
                        ->placeholder('—'),

                    Infolists\Components\TextEntry::make('rejection_reason')
                        ->label('سبب الرفض')
                        ->columnSpanFull()
                        ->placeholder('—')
                        ->visible(fn(LeaveRequest $record) => $record->rejection_reason !== null),
                ])
                ->columns(2),

            Infolists\Components\Section::make('معلومات الرصيد')
                ->schema([
                    Infolists\Components\TextEntry::make('balance_entitled')
                        ->label('المستحق'),

                    Infolists\Components\TextEntry::make('balance_used')
                        ->label('المستخدم'),

                    Infolists\Components\TextEntry::make('balance_remaining')
                        ->label('المتبقي'),
                ])
                ->columns(3)
                ->visible(fn(LeaveRequest $record) => $record->balance_entitled !== null),

            Infolists\Components\Section::make('مسار الاعتماد')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('steps')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('stage')
                                ->label('المرحلة'),

                            Infolists\Components\TextEntry::make('status')
                                ->label('الحالة')
                                ->badge()
                                ->formatStateUsing(fn(StepStatus $state): string => $state->label())
                                ->color(fn(StepStatus $state): string => $state->color()),

                            Infolists\Components\TextEntry::make('actedBy.name')
                                ->label('بواسطة')
                                ->placeholder('—'),

                            Infolists\Components\TextEntry::make('acted_at')
                                ->label('التاريخ')
                                ->dateTime('Y-m-d H:i')
                                ->placeholder('—'),

                            Infolists\Components\TextEntry::make('note')
                                ->label('الملاحظة')
                                ->placeholder('—')
                                ->columnSpanFull(),
                        ])
                        ->columns(2),
                ]),
        ]);
    }

    // -------------------------------------------------------------------------
    // Table
    // -------------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('رقم الطلب')
                    ->copyable()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('الموظف')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('leaveType.name')
                    ->label('نوع الإجازة')
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_date')
                    ->label('من')
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('end_date')
                    ->label('إلى')
                    ->date('Y-m-d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('days')
                    ->label('الأيام')
                    ->numeric(1)
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn(LeaveStatus $state): string => $state->label())
                    ->color(fn(LeaveStatus $state): string => $state->color())
                    ->sortable(),

                Tables\Columns\TextColumn::make('current_stage')
                    ->label('المرحلة الحالية')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('الجهة')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(
                        collect(LeaveStatus::cases())
                            ->mapWithKeys(fn(LeaveStatus $s) => [$s->value => $s->label()])
                            ->toArray()
                    ),

                Tables\Filters\SelectFilter::make('leave_type_id')
                    ->label('نوع الإجازة')
                    ->options(
                        LeaveType::orderBy('name')->pluck('name', 'id')->toArray()
                    ),

                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('الجهة')
                    ->options(
                        Organization::withoutGlobalScopes()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray()
                    ),

                Tables\Filters\Filter::make('start_date')
                    ->label('نطاق التاريخ')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('من'),
                        Forms\Components\DatePicker::make('to')->label('إلى'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn($q, $date) => $q->whereDate('start_date', '>=', $date))
                            ->when($data['to'],   fn($q, $date) => $q->whereDate('start_date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn(LeaveRequest $record): bool => $record->status->canBeEdited()
                        && Auth::user()?->can('update', $record)),

                Tables\Actions\Action::make('submit')
                    ->label('تقديم الطلب')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn(LeaveRequest $record): bool =>
                        in_array($record->status, [LeaveStatus::DRAFT, LeaveStatus::RETURNED])
                        && Auth::user()?->can('submit', $record)
                    )
                    ->action(function (LeaveRequest $record): void {
                        try {
                            app(\App\Services\LeaveRequestService::class)->submit($record, Auth::user());
                            Notification::make()
                                ->success()
                                ->title('تم تقديم الطلب بنجاح')
                                ->send();
                        } catch (LeaveRequestException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),

                Tables\Actions\Action::make('approve')
                    ->label('اعتماد')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn(LeaveRequest $record): bool =>
                        in_array($record->status, [LeaveStatus::SUBMITTED, LeaveStatus::IN_REVIEW])
                        && Auth::user()?->can('approve', $record)
                    )
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('ملاحظة (اختيارية)')
                            ->nullable(),
                    ])
                    ->action(function (LeaveRequest $record, array $data): void {
                        try {
                            $step = $record->steps()
                                ->where('status', StepStatus::PENDING->value)
                                ->where('stage', $record->current_stage)
                                ->first();

                            if (! $step) {
                                Notification::make()->danger()->title('لا توجد مرحلة نشطة للاعتماد.')->send();
                                return;
                            }

                            app(\App\Services\LeaveRequestService::class)->approve(
                                $record, $step, Auth::user(), $data['note'] ?? null
                            );

                            Notification::make()->success()->title('تمت الموافقة على الطلب')->send();
                        } catch (LeaveRequestException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn(LeaveRequest $record): bool =>
                        in_array($record->status, [LeaveStatus::SUBMITTED, LeaveStatus::IN_REVIEW])
                        && Auth::user()?->can('reject', $record)
                    )
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('سبب الرفض')
                            ->required(),
                    ])
                    ->action(function (LeaveRequest $record, array $data): void {
                        try {
                            $step = $record->steps()
                                ->where('status', StepStatus::PENDING->value)
                                ->where('stage', $record->current_stage)
                                ->first();

                            if (! $step) {
                                Notification::make()->danger()->title('لا توجد مرحلة نشطة للرفض.')->send();
                                return;
                            }

                            app(\App\Services\LeaveRequestService::class)->reject(
                                $record, $step, Auth::user(), $data['reason']
                            );

                            Notification::make()->success()->title('تم رفض الطلب')->send();
                        } catch (LeaveRequestException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),

                Tables\Actions\Action::make('return')
                    ->label('إعادة للتعديل')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(fn(LeaveRequest $record): bool =>
                        in_array($record->status, [LeaveStatus::SUBMITTED, LeaveStatus::IN_REVIEW])
                        && Auth::user()?->can('return', $record)
                    )
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('الملاحظة')
                            ->required(),
                    ])
                    ->action(function (LeaveRequest $record, array $data): void {
                        try {
                            $step = $record->steps()
                                ->where('status', StepStatus::PENDING->value)
                                ->where('stage', $record->current_stage)
                                ->first();

                            if (! $step) {
                                Notification::make()->danger()->title('لا توجد مرحلة نشطة للإعادة.')->send();
                                return;
                            }

                            app(\App\Services\LeaveRequestService::class)->returnRequest(
                                $record, $step, Auth::user(), $data['note']
                            );

                            Notification::make()->success()->title('تمت إعادة الطلب للتعديل')->send();
                        } catch (LeaveRequestException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('إلغاء')
                    ->icon('heroicon-o-no-symbol')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->visible(fn(LeaveRequest $record): bool =>
                        $record->status->canBeCancelled()
                        && Auth::user()?->can('cancel', $record)
                    )
                    ->action(function (LeaveRequest $record): void {
                        try {
                            app(\App\Services\LeaveRequestService::class)->cancel($record, Auth::user());
                            Notification::make()->success()->title('تم إلغاء الطلب')->send();
                        } catch (LeaveRequestException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        } catch (ValidationException $e) {
                            Notification::make()->danger()->title($e->getMessage())->send();
                        }
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('export')
                    ->label('تصدير')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn() => null), // placeholder
            ])
            ->defaultSort('created_at', 'desc');
    }

    // -------------------------------------------------------------------------
    // Modify query for permission-aware scoping
    // -------------------------------------------------------------------------

    public static function getEloquentQuery(): Builder
    {
        $user = Auth::user();
        $base = LeaveRequest::withoutGlobalScopes();

        if (! $user) {
            return $base->whereRaw('1 = 0');
        }

        $user->setOrganizationTeam();

        if ($user->hasPermissionTo('view_all_leave_requests')) {
            $org = $user->organization;

            if ($org) {
                return $base->whereIn('organization_id', $org->subtreeIds());
            }

            return $base;
        }

        // Scoped to own organization only
        return $base->where('organization_id', $user->organization_id);
    }

    // -------------------------------------------------------------------------
    // Pages
    // -------------------------------------------------------------------------

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListLeaveRequests::route('/'),
            'create' => Pages\CreateLeaveRequest::route('/create'),
            'edit'   => Pages\EditLeaveRequest::route('/{record}/edit'),
            'view'   => Pages\ViewLeaveRequest::route('/{record}'),
        ];
    }
}
