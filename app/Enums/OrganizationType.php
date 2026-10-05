<?php

namespace App\Enums;

enum OrganizationType: string
{
    case DIRECTORATE   = 'directorate';
    case ADMINISTRATION = 'administration';
    case SCHOOL        = 'school';

    public function label(): string
    {
        return match($this) {
            self::DIRECTORATE    => 'مديرية',
            self::ADMINISTRATION => 'إدارة تعليمية',
            self::SCHOOL         => 'مدرسة',
        };
    }

    public function depth(): int
    {
        return match($this) {
            self::DIRECTORATE    => 0,
            self::ADMINISTRATION => 1,
            self::SCHOOL         => 2,
        };
    }

    /** Returns allowed child types for this level */
    public function childType(): ?self
    {
        return match($this) {
            self::DIRECTORATE    => self::ADMINISTRATION,
            self::ADMINISTRATION => self::SCHOOL,
            self::SCHOOL         => null,
        };
    }
}
