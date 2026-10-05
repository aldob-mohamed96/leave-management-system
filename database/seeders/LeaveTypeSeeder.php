<?php

namespace Database\Seeders;

use App\Models\LeaveType;
use Illuminate\Database\Seeder;

class LeaveTypeSeeder extends Seeder
{
    /**
     * كل الإجازات التي تسير في مسار الاعتماد العادي.
     *
     * الحقول:
     *  - deducts_balance      : هل يُخصم من رصيد الموظف؟
     *  - yearly_entitlement   : الأيام المستحقة في السنة (0 = لا حد سنوي)
     *  - max_days_per_request : أقصى أيام لطلب واحد (null = لا حد)
     *  - max_times_in_service : أقصى عدد مرات طول الخدمة (null = غير محدود)
     *  - requires_document    : هل تستلزم مستنداً داعماً؟
     *  - gender_restriction   : male | female | null (كلاهما)
     */
    public function run(): void
    {
        $types = [
            // ----------------------------------------------------------------
            // 1. إجازة اعتيادية — الرصيد يتحدد حسب entitlement_grade
            // ----------------------------------------------------------------
            [
                'code'                  => 'regular',
                'name'                  => 'إجازة اعتيادية',
                'deducts_balance'       => true,
                'yearly_entitlement'    => 45,   // الحد الأعلى — يُعاد حساب الفعلي من entitlement_grade
                'max_days_per_request'  => null,
                'is_active'             => true,
            ],
            // ----------------------------------------------------------------
            // 2. إجازة عارضة — لازم تخلص قبل الاعتيادية في طلبات 1-2 يوم
            // ----------------------------------------------------------------
            [
                'code'                  => 'casual',
                'name'                  => 'إجازة عارضة',
                'deducts_balance'       => true,
                'yearly_entitlement'    => 7,
                'max_days_per_request'  => 7,
                'is_active'             => true,
            ],
            // ----------------------------------------------------------------
            // 3. إجازة مرضية — بتقرير طبي، لا تخصم من الرصيد
            // ----------------------------------------------------------------
            [
                'code'                  => 'sick',
                'name'                  => 'إجازة مرضية',
                'deducts_balance'       => false,
                'yearly_entitlement'    => 180,
                'max_days_per_request'  => null,
                'is_active'             => true,
            ],
            // ----------------------------------------------------------------
            // 4. إجازة مرضية نصف مرتب — بعد استنفاد المرضية الكاملة
            // ----------------------------------------------------------------
            [
                'code'                  => 'sick_half_pay',
                'name'                  => 'إجازة مرضية (نصف مرتب)',
                'deducts_balance'       => false,
                'yearly_entitlement'    => 180,
                'max_days_per_request'  => null,
                'is_active'             => true,
            ],
            // ----------------------------------------------------------------
            // 5. إجازة طارئة — ظروف مفاجئة
            // ----------------------------------------------------------------
            [
                'code'                  => 'emergency',
                'name'                  => 'إجازة طارئة',
                'deducts_balance'       => true,
                'yearly_entitlement'    => 5,
                'max_days_per_request'  => 3,
                'is_active'             => true,
            ],
            // ----------------------------------------------------------------
            // 6. إجازة أمومة — للمرأة فقط، 3 مرات طول الخدمة
            // ----------------------------------------------------------------
            [
                'code'                  => 'maternity',
                'name'                  => 'إجازة أمومة',
                'deducts_balance'       => false,
                'yearly_entitlement'    => 0,   // لا رصيد سنوي — مرات محددة بالخدمة
                'max_days_per_request'  => 90,
                'is_active'             => true,
            ],
            // ----------------------------------------------------------------
            // 7. إجازة وضع (أبوة) — للرجل عند ولادة طفل
            // ----------------------------------------------------------------
            [
                'code'                  => 'paternity',
                'name'                  => 'إجازة وضع (أبوة)',
                'deducts_balance'       => false,
                'yearly_entitlement'    => 0,
                'max_days_per_request'  => 3,
                'is_active'             => true,
            ],
            // ----------------------------------------------------------------
            // 8. إجازة حج — مرة واحدة طول الخدمة
            // ----------------------------------------------------------------
            [
                'code'                  => 'hajj',
                'name'                  => 'إجازة حج',
                'deducts_balance'       => false,
                'yearly_entitlement'    => 0,
                'max_days_per_request'  => 30,
                'is_active'             => true,
            ],
            // ----------------------------------------------------------------
            // 9. إجازة زواج — مرة واحدة
            // ----------------------------------------------------------------
            [
                'code'                  => 'marriage',
                'name'                  => 'إجازة زواج',
                'deducts_balance'       => false,
                'yearly_entitlement'    => 0,
                'max_days_per_request'  => 7,
                'is_active'             => true,
            ],
            // ----------------------------------------------------------------
            // 10. إجازة وفاة — وفاة قريب من الدرجة الأولى
            // ----------------------------------------------------------------
            [
                'code'                  => 'bereavement',
                'name'                  => 'إجازة وفاة',
                'deducts_balance'       => false,
                'yearly_entitlement'    => 0,
                'max_days_per_request'  => 7,
                'is_active'             => true,
            ],
            // ----------------------------------------------------------------
            // 11. إجازة دراسية — للمعلمين باذن الإدارة
            // ----------------------------------------------------------------
            [
                'code'                  => 'study',
                'name'                  => 'إجازة دراسية',
                'deducts_balance'       => false,
                'yearly_entitlement'    => 0,
                'max_days_per_request'  => null,
                'is_active'             => true,
            ],
            // ----------------------------------------------------------------
            // 12. إجازة بدون مرتب — موافقة مدير الإدارة مطلوبة
            // ----------------------------------------------------------------
            [
                'code'                  => 'without_pay',
                'name'                  => 'إجازة بدون مرتب',
                'deducts_balance'       => false,
                'yearly_entitlement'    => 0,
                'max_days_per_request'  => null,
                'is_active'             => true,
            ],
        ];

        foreach ($types as $type) {
            LeaveType::updateOrCreate(['code' => $type['code']], $type);
        }

        $this->command?->info('✓ ' . count($types) . ' leave types seeded.');
    }
}
