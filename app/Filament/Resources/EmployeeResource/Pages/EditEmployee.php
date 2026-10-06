<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Models\User;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    protected bool $createSystemUser = false;

    protected ?string $systemEmail = null;

    protected ?string $systemPassword = null;

    protected ?string $systemPhone = null;

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
        $this->systemPhone = $this->data['system_phone'] ?? $data['system_phone'] ?? null;

        unset(
            $data['is_system_employee'],
            $data['system_email'],
            $data['system_password'],
            $data['system_phone'],
        );

        if (EmployeeResource::isSchoolActor()) {
            $data['organization_id'] = auth()->user()->organization_id;
            unset($data['user_id']);
        }

        if ($this->createSystemUser && blank($this->record->user_id)) {
            unset($data['user_id']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        // Sync phone to linked user account
        if ($this->record->user_id) {
            $phone = User::normalizePhone($this->record->phone);
            User::whereKey($this->record->user_id)->update(['phone' => $phone]);
        }

        if ($this->record->user_id || ! $this->createSystemUser) {
            return;
        }

        if (! $this->systemEmail || ! $this->systemPassword || ! $this->record->organization_id) {
            return;
        }

        EmployeeResource::provisionSystemUser(
            employee: $this->record,
            email: $this->systemEmail,
            password: $this->systemPassword,
            phone: $this->systemPhone ?: $this->record->phone,
        );
    }
}
