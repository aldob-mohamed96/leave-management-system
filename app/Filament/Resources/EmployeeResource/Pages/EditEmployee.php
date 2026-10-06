<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected bool $createSystemUser = false;

    protected ?string $systemEmail = null;

    protected ?string $systemPassword = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->createSystemUser = (bool) ($this->data['is_system_employee'] ?? $data['is_system_employee'] ?? false);
        $this->systemEmail = $this->data['system_email'] ?? $data['system_email'] ?? null;
        $this->systemPassword = $this->data['system_password'] ?? $data['system_password'] ?? null;

        unset($data['is_system_employee'], $data['system_email'], $data['system_password']);

        if (EmployeeResource::isSchoolActor()) {
            $data['organization_id'] = auth()->user()->organization_id;
            // لا تسمح المدرسة بتغيير ربط الحساب يدوياً
            unset($data['user_id']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if (! EmployeeResource::isSchoolActor()) {
            return;
        }

        if ($this->record->user_id || ! $this->createSystemUser) {
            return;
        }

        if (! $this->systemEmail || ! $this->systemPassword || ! $this->record->organization_id) {
            return;
        }

        $organizationId = $this->record->organization_id;

        $user = User::create([
            'name'                 => $this->record->full_name,
            'email'                => $this->systemEmail,
            'password'             => $this->systemPassword,
            'organization_id'      => $organizationId,
            'is_active'            => true,
            'must_change_password' => true,
        ]);

        setPermissionsTeamId($organizationId);

        $role = Role::query()
            ->where('name', 'موظف مدرسة')
            ->where('organization_id', $organizationId)
            ->first();

        if ($role) {
            $user->assignRole($role);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        setPermissionsTeamId(null);

        $this->record->update(['user_id' => $user->id]);
    }
}
