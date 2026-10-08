<x-filament-panels::page>
<div class="space-y-4" dir="rtl">

    {{-- ============================
         Tab navigation
         ============================ --}}
    <div class="flex gap-1 border-b border-gray-200 dark:border-gray-700 overflow-x-auto">
        @php
            $tabs = [
                'info'      => 'بيانات المدرسة',
                'employees' => 'الموظفون',
                'requests'  => 'طلبات الإجازة',
                'stats'     => 'الإحصائيات',
            ];
        @endphp
        @foreach ($tabs as $key => $label)
            <button
                wire:click="switchTab('{{ $key }}')"
                class="px-4 py-2 text-sm font-medium whitespace-nowrap transition-colors focus:outline-none
                    {{ $activeTab === $key
                        ? 'border-b-2 border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                        : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- ============================
         Tab: بيانات المدرسة
         ============================ --}}
    @if($activeTab === 'info')
        <x-filament::section heading="بيانات المدرسة">
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 text-sm">
                <div>
                    <dt class="font-semibold text-gray-600 dark:text-gray-400">الاسم</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white">{{ $school->name }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-gray-600 dark:text-gray-400">الكود</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white font-mono" dir="ltr">{{ $school->code ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-gray-600 dark:text-gray-400">النوع</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white">{{ $school->type?->label() ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-gray-600 dark:text-gray-400">الحالة</dt>
                    <dd class="mt-1">
                        @if($school->is_active)
                            <x-filament::badge color="success">نشطة</x-filament::badge>
                        @else
                            <x-filament::badge color="danger">غير نشطة</x-filament::badge>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="font-semibold text-gray-600 dark:text-gray-400">الإدارة التعليمية</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white">{{ $parentOrg?->name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-gray-600 dark:text-gray-400">عدد الموظفين</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white">{{ $employeeCount }}</dd>
                </div>
                <div>
                    <dt class="font-semibold text-gray-600 dark:text-gray-400">إجمالي طلبات الإجازة</dt>
                    <dd class="mt-1 text-gray-900 dark:text-white">{{ $totalRequests }}</dd>
                </div>
            </dl>
        </x-filament::section>
    @endif

    {{-- ============================
         Tab: الموظفون
         ============================ --}}
    @if($activeTab === 'employees')
        <x-filament::section heading="الموظفون">
            @if($employees->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">لا يوجد موظفون مسجلون لهذه المدرسة.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">الكود</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">الاسم</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">المسمى الوظيفي</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">الدرجة</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">نشط</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">إجراء</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($employees as $emp)
                                <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                    <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400" dir="ltr">{{ $emp->employee_code ?? '—' }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">{{ $emp->full_name }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $emp->job_title ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $emp->entitlementGrade?->name ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        @if($emp->is_active)
                                            <x-filament::badge color="success">نشط</x-filament::badge>
                                        @else
                                            <x-filament::badge color="danger">غير نشط</x-filament::badge>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ \App\Filament\Resources\EmployeeResource::getUrl('edit', ['record' => $emp->id]) }}"
                                           class="text-primary-600 dark:text-primary-400 underline hover:no-underline text-xs">
                                            تعديل
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif

    {{-- ============================
         Tab: طلبات الإجازة
         ============================ --}}
    @if($activeTab === 'requests')
        <x-filament::section heading="طلبات الإجازة">
            {{-- Status filter --}}
            <div class="mb-4 flex items-center gap-3">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">تصفية بالحالة:</label>
                <select
                    wire:model.live="statusFilter"
                    class="rounded-lg border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm text-sm px-3 py-2 focus:ring-2 focus:ring-primary-500 focus:border-primary-500"
                >
                    <option value="">— كل الحالات —</option>
                    @foreach(\App\Enums\LeaveStatus::cases() as $s)
                        <option value="{{ $s->value }}">{{ $s->label() }}</option>
                    @endforeach
                </select>
            </div>

            @if($leaveRequests->isEmpty())
                <p class="text-sm text-gray-500 dark:text-gray-400">لا توجد طلبات إجازة لهذه المدرسة.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">الرقم</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">الموظف</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">نوع الإجازة</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">من</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">إلى</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">الأيام</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">الحالة</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">المرحلة</th>
                                <th class="px-4 py-3 text-right font-semibold text-gray-600 dark:text-gray-300">إجراء</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($leaveRequests as $lr)
                                <tr class="bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                    <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $lr->number }}</td>
                                    <td class="px-4 py-3 text-gray-900 dark:text-white">{{ $lr->employee?->full_name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $lr->leaveType?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $lr->start_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $lr->end_date?->format('Y-m-d') ?? '—' }}</td>
                                    <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $lr->days }}</td>
                                    <td class="px-4 py-3">
                                        <x-filament::badge :color="$lr->status->color()">
                                            {{ $lr->status->label() }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-600 dark:text-gray-400">
                                        {{ \App\Models\LeaveRequest::pendingApprovalLabel($lr->current_stage) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <a href="{{ \App\Filament\Resources\LeaveRequestResource::getUrl('view', ['record' => $lr->id]) }}"
                                           class="text-primary-600 dark:text-primary-400 underline hover:no-underline text-xs">
                                            عرض
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    @endif

    {{-- ============================
         Tab: الإحصائيات
         ============================ --}}
    @if($activeTab === 'stats')
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div class="bg-blue-50 dark:bg-blue-900/30 rounded-xl p-5 text-center">
                <div class="text-3xl font-bold text-blue-700 dark:text-blue-300">{{ $employeeCount }}</div>
                <div class="text-sm text-blue-600 dark:text-blue-400 mt-2">إجمالي الموظفين</div>
            </div>
            <div class="bg-green-50 dark:bg-green-900/30 rounded-xl p-5 text-center">
                <div class="text-3xl font-bold text-green-700 dark:text-green-300">{{ $activeEmployeeCount }}</div>
                <div class="text-sm text-green-600 dark:text-green-400 mt-2">الموظفون النشطون</div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/30 rounded-xl p-5 text-center">
                <div class="text-3xl font-bold text-gray-700 dark:text-gray-300">{{ $totalRequests }}</div>
                <div class="text-sm text-gray-600 dark:text-gray-400 mt-2">إجمالي الطلبات</div>
            </div>
            <div class="bg-emerald-50 dark:bg-emerald-900/30 rounded-xl p-5 text-center">
                <div class="text-3xl font-bold text-emerald-700 dark:text-emerald-300">{{ $approvedCount }}</div>
                <div class="text-sm text-emerald-600 dark:text-emerald-400 mt-2">معتمدة</div>
            </div>
            <div class="bg-amber-50 dark:bg-amber-900/30 rounded-xl p-5 text-center">
                <div class="text-3xl font-bold text-amber-700 dark:text-amber-300">{{ $pendingCount }}</div>
                <div class="text-sm text-amber-600 dark:text-amber-400 mt-2">قيد المراجعة / بانتظار</div>
            </div>
            <div class="bg-red-50 dark:bg-red-900/30 rounded-xl p-5 text-center">
                <div class="text-3xl font-bold text-red-700 dark:text-red-300">{{ $rejectedCount }}</div>
                <div class="text-sm text-red-600 dark:text-red-400 mt-2">مرفوضة</div>
            </div>
        </div>
    @endif

</div>
</x-filament-panels::page>
