<?php

namespace Database\Seeders;

use App\Models\EntitlementGrade;
use Illuminate\Database\Seeder;

class EntitlementGradeSeeder extends Seeder
{
    public function run(): void
    {
        $grades = [
            // category, code, name, yearly_days, is_active
            ['teacher',  'teacher_assistant', 'معلم مساعد',           28, false],
            ['teacher',  'teacher',           'معلم',                 28, true],
            ['teacher',  'teacher_first',     'معلم أول',             30, true],
            ['teacher',  'teacher_first_a',   'معلم أول (أ)',         35, true],
            ['teacher',  'teacher_expert',    'معلم خبير',            40, true],
            ['teacher',  'teacher_senior',    'كبير معلمين',          45, true],

            ['guidance', 'guidance',          'موجه',                 35, false],
            ['guidance', 'guidance_first',    'موجه أول',             40, false],
            ['guidance', 'guidance_general',  'موجه عام',             45, false],

            ['admin',    'admin_4',           'إداري الدرجة الرابعة', 28, true],
            ['admin',    'admin_3',           'إداري الدرجة الثالثة', 30, true],
            ['admin',    'admin_2',           'إداري الدرجة الثانية', 35, false],
            ['admin',    'admin_1',           'إداري الدرجة الأولى',  40, false],
            ['admin',    'admin_senior',      'إداري - وظائف عليا',   45, false],

            ['special',  'admin_over50',      'موظف (فوق 50 سنة)',    50, true],
        ];

        foreach ($grades as $index => [$category, $code, $name, $days, $active]) {
            EntitlementGrade::updateOrCreate(
                ['code' => $code],
                [
                    'category'    => $category,
                    'name'        => $name,
                    'yearly_days' => $days,
                    'is_active'   => $active,
                    'sort_order'  => ($index + 1) * 10,
                ]
            );
        }

        $this->command?->info('✓ ' . count($grades) . ' entitlement grades seeded.');
    }
}
