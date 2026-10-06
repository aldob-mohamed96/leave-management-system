<?php

namespace App\Filament\Resources\EntitlementGradeResource\Pages;

use App\Filament\Resources\EntitlementGradeResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListEntitlementGrades extends ListRecords
{
    protected static string $resource = EntitlementGradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('إضافة درجة'),
        ];
    }
}
