<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\Organization;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Validators\Failure;
use Throwable;

class EmployeesImport implements ToModel, WithHeadingRow, WithValidation, WithBatchInserts, SkipsOnError, SkipsOnFailure
{
    public array $errors = [];
    private int $successCount = 0;

    /**
     * Map each spreadsheet row to an Employee model instance.
     */
    public function model(array $row): ?Employee
    {
        $org = Organization::withoutGlobalScopes()
            ->where('code', $row['organization_code'])
            ->first();

        if (! $org) {
            return null;
        }

        $entitlementGrade = isset($row['entitlement_grade']) && $row['entitlement_grade'] !== ''
            ? $row['entitlement_grade']
            : null;

        $this->successCount++;

        return new Employee([
            'organization_id'   => $org->id,
            'employee_code'     => $row['employee_code'],
            'full_name'         => $row['full_name'],
            'job_title'         => $row['job_title'] ?? null,
            'grade'             => $row['grade'] ?? null,
            'entitlement_grade' => $entitlementGrade,
            'birth_date'        => $this->nullableDate($row['birth_date'] ?? null),
            'hire_date'         => $this->nullableDate($row['hire_date'] ?? null),
            'work_start_date'   => $this->nullableDate($row['work_start_date'] ?? null),
            'phone'             => $row['phone'] ?? null,
            'is_active'         => true,
        ]);
    }

    /**
     * Validation rules applied to each row before model() is called.
     */
    public function rules(): array
    {
        return [
            'employee_code'     => 'required|unique:employees,employee_code',
            'full_name'         => 'required|string',
            'organization_code' => 'required|exists:organizations,code',
        ];
    }

    public function batchSize(): int
    {
        return 100;
    }

    /**
     * Required by SkipsOnError — called when any runtime error is thrown during import.
     */
    public function onError(Throwable $e): void
    {
        $this->errors[] = $e->getMessage();
    }

    /**
     * Required by SkipsOnFailure — called when a row fails validation.
     * Collects errors instead of throwing an exception.
     */
    public function onFailure(Failure ...$failures): void
    {
        foreach ($failures as $failure) {
            foreach ($failure->errors() as $error) {
                $this->errors[] = "صف {$failure->row()}: {$error}";
            }
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }

    /**
     * Return null for empty/whitespace date strings.
     */
    private function nullableDate(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return $value;
    }
}
