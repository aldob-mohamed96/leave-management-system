<?php

namespace App\Filament\Resources\EntitlementGradeResource\Pages;

use App\Filament\Resources\EntitlementGradeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEntitlementGrade extends CreateRecord
{
    protected static string $resource = EntitlementGradeResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
