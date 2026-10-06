<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HolidayResource\Pages;
use App\Models\Holiday;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

/**
 * مورد إدارة الإجازات الرسمية والعطلات.
 * محمي بصلاحية manage_organization بدلاً من Policy مستقلة.
 */
class HolidayResource extends Resource
{
    protected static ?string $model = Holiday::class;

    protected static ?string $navigationGroup = 'الإعدادات';
    protected static ?string $navigationLabel = 'الإجازات الرسمية';
    protected static ?string $navigationIcon  = 'heroicon-o-calendar';
    protected static ?string $modelLabel      = 'إجازة رسمية';
    protected static ?string $pluralModelLabel = 'الإجازات الرسمية';

    // -------------------------------------------------------------------------
    // Gate check
    // -------------------------------------------------------------------------

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

    // -------------------------------------------------------------------------
    // Form
    // -------------------------------------------------------------------------

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('date')
                ->label('التاريخ')
                ->required(),

            Forms\Components\TextInput::make('name')
                ->label('اسم الإجازة / العطلة')
                ->required()
                ->maxLength(255),
        ]);
    }

    // -------------------------------------------------------------------------
    // Table
    // -------------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('date')
                    ->label('التاريخ')
                    ->date('d F Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('اسم الإجازة')
                    ->searchable(),

                Tables\Columns\TextColumn::make('day_of_week')
                    ->label('اليوم')
                    ->getStateUsing(function (Holiday $record): string {
                        $days = [
                            0 => 'الأحد',
                            1 => 'الاثنين',
                            2 => 'الثلاثاء',
                            3 => 'الأربعاء',
                            4 => 'الخميس',
                            5 => 'الجمعة',
                            6 => 'السبت',
                        ];

                        return $days[$record->date->dayOfWeek] ?? '—';
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('date');
    }

    // -------------------------------------------------------------------------
    // Pages
    // -------------------------------------------------------------------------

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListHolidays::route('/'),
            'create' => Pages\CreateHoliday::route('/create'),
            'edit'   => Pages\EditHoliday::route('/{record}/edit'),
        ];
    }
}
