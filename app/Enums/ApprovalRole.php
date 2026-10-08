<?php

namespace App\Enums;

enum ApprovalRole: string
{
    case SCHOOL_PRINCIPAL = 'school_principal';
    case LEAVES_OFFICER   = 'leaves_officer';
    case HR_AFFAIRS       = 'hr_affairs';
    case ADMIN_MANAGER    = 'admin_manager';

    public function label(): string
    {
        return match($this) {
            self::SCHOOL_PRINCIPAL => 'مدير المدرسة',
            self::LEAVES_OFFICER   => 'مسؤول الإجازات',
            self::HR_AFFAIRS       => 'شؤون عاملين',
            self::ADMIN_MANAGER    => 'مدير الإدارة',
        };
    }
}
