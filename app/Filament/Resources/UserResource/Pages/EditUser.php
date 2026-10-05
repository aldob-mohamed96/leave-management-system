<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * تحميل الأدوار الحالية للمستخدم في حقل النموذج.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if ($this->record->organization_id) {
            setPermissionsTeamId($this->record->organization_id);
        }

        $data['roles'] = $this->record->getRoleNames()->toArray();

        return $data;
    }

    /**
     * مزامنة الأدوار بعد حفظ التعديلات.
     */
    protected function afterSave(): void
    {
        $roles = $this->data['roles'] ?? [];

        if ($this->record->organization_id) {
            setPermissionsTeamId($this->record->organization_id);
        }

        $this->record->syncRoles($roles);
    }
}
