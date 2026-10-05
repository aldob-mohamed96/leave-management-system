<?php

namespace App\Filament\Components;

use App\Models\Employee;
use Illuminate\Support\HtmlString;

/**
 * مكوّن شارة رصيد الإجازات — يعرض رصيد كل نوع إجازة للموظف في السنة الحالية.
 * يُستخدم كـ ViewComponent بسيط داخل Filament ViewColumn / Infolist.
 */
class BalanceBadge
{
    /**
     * يرسم شارات رصيد الإجازات للموظف المُمرَّر.
     *
     * @param Employee $employee الموظف المراد عرض رصيده
     * @return HtmlString HTML مُعاد كـ HtmlString آمن
     */
    public static function render(Employee $employee): HtmlString
    {
        $balances = $employee->leaveBalances()
            ->with('leaveType')
            ->currentYear()
            ->get();

        if ($balances->isEmpty()) {
            return new HtmlString('<span class="text-xs text-gray-400">لا يوجد رصيد</span>');
        }

        $html = '<div class="flex flex-wrap gap-1">';

        foreach ($balances as $balance) {
            $typeName  = $balance->leaveType?->name ?? '—';
            $remaining = number_format($balance->remaining, 0);
            $entitled  = number_format((float)$balance->entitled + (float)$balance->carried_over, 0);

            $html .= sprintf(
                '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800" title="%s">'
                . '%s: %s من %s</span>',
                e($typeName),
                e($typeName),
                e($remaining),
                e($entitled)
            );
        }

        $html .= '</div>';

        return new HtmlString($html);
    }
}
