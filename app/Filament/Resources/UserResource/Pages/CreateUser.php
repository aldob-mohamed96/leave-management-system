<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * ربط الأدوار بعد حفظ المستخدم.
     */
    protected function afterCreate(): void
    {
        $roles = $this->data['roles'] ?? [];

        if (! empty($roles) && $this->record->organization_id) {
            setPermissionsTeamId($this->record->organization_id);
            $this->record->syncRoles($roles);
        }
    }
}
