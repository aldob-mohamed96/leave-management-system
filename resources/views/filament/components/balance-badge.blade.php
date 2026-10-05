{{--
    شارة رصيد الإجازات للموظف في السنة الحالية.
    المتغيرات المتوقعة:
      $employee  — مثيل App\Models\Employee (مع تحميل leaveBalances.leaveType)
--}}
@php
    /** @var \App\Models\Employee $employee */
    $balances = $employee->leaveBalances()
        ->with('leaveType')
        ->currentYear()
        ->get();
@endphp

@if ($balances->isEmpty())
    <span class="text-xs text-gray-400">لا يوجد رصيد</span>
@else
    <div class="flex flex-wrap gap-1">
        @foreach ($balances as $balance)
            @php
                $typeName  = $balance->leaveType?->name ?? '—';
                $remaining = number_format($balance->remaining, 0);
                $entitled  = number_format((float) $balance->entitled + (float) $balance->carried_over, 0);
            @endphp
            <span
                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800"
                title="{{ $typeName }}"
            >
                {{ $typeName }}: المتبقي {{ $remaining }} من {{ $entitled }}
            </span>
        @endforeach
    </div>
@endif
