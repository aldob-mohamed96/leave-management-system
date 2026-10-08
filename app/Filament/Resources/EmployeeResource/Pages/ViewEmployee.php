<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('تعديل'),

            Actions\Action::make('leave_history')
                ->label('التاريخ الكامل')
                ->icon('heroicon-o-calendar-days')
                ->color('info')
                ->url(fn (): string => EmployeeResource::getUrl('leave-history', ['record' => $this->record->id])),
        ];
    }
}
