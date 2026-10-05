<?php

namespace App\Filament\Resources\LeaveRequestResource\Pages;

use App\Filament\Resources\LeaveRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

/**
 * صفحة قائمة طلبات الإجازات.
 */
class ListLeaveRequests extends ListRecords
{
    protected static string $resource = LeaveRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('exportAll')
                ->label('تصدير Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(route('leave.export'))
                ->openUrlInNewTab(),
        ];
    }
}
