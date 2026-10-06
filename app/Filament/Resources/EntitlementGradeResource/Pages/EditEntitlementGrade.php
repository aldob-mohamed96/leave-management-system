<?php

namespace App\Filament\Resources\EntitlementGradeResource\Pages;

use App\Filament\Resources\EntitlementGradeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEntitlementGrade extends EditRecord
{
    protected static string $resource = EntitlementGradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('تأكيد حذف الدرجة')
                ->modalDescription('لن يُحذف الموظفون المرتبطون، لكن كود الدرجة سيصبح غير معرّف لديهم.'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
