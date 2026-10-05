{{--
    عمود رصيد الإجازات في جدول الموظفين.
    $getRecord() يُعيد مثيل App\Models\Employee.
--}}
@php
    /** @var \App\Models\Employee $employee */
    $employee = $getRecord();
    $balances = $employee->leaveBalances()
        ->with('leaveType')
        ->currentYear()
        ->get();
@endphp

@if ($balances->isEmpty())
    <span class="text-xs text-gray-400">—</span>
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
                title="{{ $typeName }}: المتبقي {{ $remaining }} من {{ $entitled }}"
            >
                {{ $typeName }}: {{ $remaining }}/{{ $entitled }}
            </span>
        @endforeach
    </div>
@endif
