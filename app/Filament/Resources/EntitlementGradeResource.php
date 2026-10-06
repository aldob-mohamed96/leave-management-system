<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EntitlementGradeResource\Pages;
use App\Models\EntitlementGrade;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * إدارة الدرجات الوظيفية واستحقاق الإجازة الاعتيادية.
 */
class EntitlementGradeResource extends Resource
{
    protected static ?string $model = EntitlementGrade::class;

    protected static ?string $navigationGroup = 'الإعدادات';
    protected static ?string $navigationLabel = 'الدرجات الوظيفية';
    protected static ?string $navigationIcon  = 'heroicon-o-academic-cap';
    protected static ?string $modelLabel      = 'درجة وظيفية';
    protected static ?string $pluralModelLabel = 'الدرجات الوظيفية';
    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return Gate::allows('manage_organization');
    }

    public static function canCreate(): bool
    {
        return Gate::allows('manage_organization');
    }

    public static function canEdit($record): bool
    {
        return Gate::allows('manage_organization');
    }

    public static function canDelete($record): bool
    {
        return Gate::allows('manage_organization');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('الاسم')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state): void {
                    if (filled($get('code')) || blank($state)) {
                        return;
                    }

                    $set('code', Str::slug($state, '_'));
                }),

            Forms\Components\TextInput::make('code')
                ->label('الكود')
                ->required()
                ->unique(EntitlementGrade::class, 'code', ignoreRecord: true)
                ->maxLength(50)
                ->helperText('يُستخدم لربط الموظفين بالدرجة — يُفضّل إنجليزي بدون مسافات.'),

            Forms\Components\Select::make('category')
                ->label('التصنيف')
                ->options(EntitlementGrade::CATEGORIES)
                ->required()
                ->native(false),

            Forms\Components\TextInput::make('yearly_days')
                ->label('أيام الإجازة الاعتيادية سنوياً')
                ->numeric()
                ->required()
                ->minValue(0)
                ->maxValue(365)
                ->suffix('يوم'),

            Forms\Components\TextInput::make('sort_order')
                ->label('ترتيب العرض')
                ->numeric()
                ->default(0)
                ->minValue(0),

            Forms\Components\Toggle::make('is_active')
                ->label('نشط (يظهر عند اختيار درجة الموظف)')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('الكود')
                    ->badge()
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('التصنيف')
                    ->formatStateUsing(fn (string $state): string => EntitlementGrade::CATEGORIES[$state] ?? $state)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'teacher'  => 'info',
                        'guidance' => 'warning',
                        'admin'    => 'gray',
                        'special'  => 'success',
                        default    => 'gray',
                    }),

                Tables\Columns\TextColumn::make('yearly_days')
                    ->label('الأيام / سنة')
                    ->sortable()
                    ->suffix(' يوم'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),

                Tables\Columns\TextColumn::make('employees_count')
                    ->label('الموظفون')
                    ->counts('employees')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('التصنيف')
                    ->options(EntitlementGrade::CATEGORIES),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('نشط'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد حذف الدرجة')
                    ->modalDescription('لن يُحذف الموظفون المرتبطون، لكن كود الدرجة سيصبح غير معرّف لديهم.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEntitlementGrades::route('/'),
            'create' => Pages\CreateEntitlementGrade::route('/create'),
            'edit'   => Pages\EditEntitlementGrade::route('/{record}/edit'),
        ];
    }
}
