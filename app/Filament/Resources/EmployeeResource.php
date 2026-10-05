<?php

namespace App\Filament\Resources;

use App\Enums\EntitlementGrade;
use App\Filament\Components\BalanceBadge;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Models\Organization;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * مورد إدارة بيانات الموظفين مع عرض رصيد الإجازات.
 */
class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationGroup = 'الموظفون';
    protected static ?string $navigationLabel = 'الموظفون';
    protected static ?string $navigationIcon  = 'heroicon-o-identification';
    protected static ?string $modelLabel      = 'موظف';
    protected static ?string $pluralModelLabel = 'الموظفون';

    // -------------------------------------------------------------------------
    // Form
    // -------------------------------------------------------------------------

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('employee_code')
                ->label('كود الموظف')
                ->maxLength(50),

            Forms\Components\TextInput::make('full_name')
                ->label('الاسم الكامل')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('job_title')
                ->label('المسمى الوظيفي')
                ->maxLength(255),

            Forms\Components\Select::make('organization_id')
                ->label('المؤسسة')
                ->options(
                    Organization::withoutGlobalScopes()
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                )
                ->searchable()
                ->required(),

            Forms\Components\Select::make('entitlement_grade')
                ->label('الدرجة الوظيفية')
                ->options(
                    collect(EntitlementGrade::cases())
                        ->mapWithKeys(fn(EntitlementGrade $g) => [
                            $g->value => "{$g->label()} ({$g->yearlyDays()} يوم)",
                        ])
                        ->toArray()
                )
                ->searchable(),

            Forms\Components\Select::make('user_id')
                ->label('حساب المستخدم')
                ->options(
                    User::orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                )
                ->searchable()
                ->nullable()
                ->placeholder('— لا يوجد حساب مرتبط —'),

            Forms\Components\DatePicker::make('birth_date')
                ->label('تاريخ الميلاد')
                ->nullable(),

            Forms\Components\DatePicker::make('hire_date')
                ->label('تاريخ التعيين')
                ->nullable(),

            Forms\Components\DatePicker::make('work_start_date')
                ->label('تاريخ بداية العمل')
                ->nullable(),

            Forms\Components\TextInput::make('phone')
                ->label('رقم الهاتف')
                ->tel()
                ->maxLength(20),

            Forms\Components\Toggle::make('is_active')
                ->label('نشط')
                ->default(true),
        ]);
    }

    // -------------------------------------------------------------------------
    // Table
    // -------------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee_code')
                    ->label('الكود')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('الاسم الكامل')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('job_title')
                    ->label('المسمى الوظيفي')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('entitlement_grade')
                    ->label('الدرجة')
                    ->formatStateUsing(fn(?EntitlementGrade $state): string => $state?->label() ?? '—'),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('المؤسسة')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),

                Tables\Columns\ViewColumn::make('balance_badge')
                    ->label('رصيد الإجازات')
                    ->view('filament.columns.balance-badge'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('المؤسسة')
                    ->options(
                        Organization::withoutGlobalScopes()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray()
                    ),

                Tables\Filters\SelectFilter::make('entitlement_grade')
                    ->label('الدرجة الوظيفية')
                    ->options(
                        collect(EntitlementGrade::cases())
                            ->mapWithKeys(fn(EntitlementGrade $g) => [$g->value => $g->label()])
                            ->toArray()
                    ),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('الحالة'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('full_name');
    }

    // -------------------------------------------------------------------------
    // Pages
    // -------------------------------------------------------------------------

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEmployees::route('/'),
            'create' => Pages\CreateEmployee::route('/create'),
            'edit'   => Pages\EditEmployee::route('/{record}/edit'),
        ];
    }
}
