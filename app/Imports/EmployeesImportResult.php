<?php

namespace App\Imports;

/**
 * Value object returned after an employee import operation.
 * Holds the success count and any validation/runtime errors encountered.
 */
class EmployeesImportResult
{
    public function __construct(
        public int   $successCount = 0,
        public array $errors       = [],
    ) {}

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }
}
