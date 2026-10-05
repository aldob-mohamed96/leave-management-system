<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code'                  => 'regular',
                'name'                  => 'إجازة اعتيادية',
                'deducts_balance'       => true,
                'yearly_entitlement'    => 45,
                'max_days_per_request'  => null,
                'is_active'             => true,
            ],
            [
                'code'                  => 'casual',
                'name'                  => 'إجازة عارضة',
                'deducts_balance'       => true,
                'yearly_entitlement'    => 7,
                'max_days_per_request'  => 7,
                'is_active'             => true,
            ],
            [
                'code'                  => 'sick',
                'name'                  => 'إجازة مرضية',
                'deducts_balance'       => false,
                'yearly_entitlement'    => 180,
                'max_days_per_request'  => null,
                'is_active'             => true,
            ],
            [
                'code'                  => 'emergency',
                'name'                  => 'إجازة طارئة',
                'deducts_balance'       => true,
                'yearly_entitlement'    => 5,
                'max_days_per_request'  => 3,
                'is_active'             => true,
            ],
        ];

        foreach ($types as $type) {
            LeaveType::updateOrCreate(['code' => $type['code']], $type);
        }
    }
}
