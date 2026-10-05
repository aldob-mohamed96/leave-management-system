<x-filament-widgets::widget>
    <x-filament::section heading="أعلى موظفين استهلاكاً للإجازات">
        @php $takers = $this->getData()['takers']; @endphp
        @if($takers->isEmpty())
            <p class="text-sm text-gray-500">لا توجد بيانات.</p>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm" dir="rtl">
                <thead class="bg-gray-50 text-gray-700">
                    <tr>
                        <th class="text-center px-3 py-2">#</th>
                        <th class="text-right px-3 py-2">اسم الموظف</th>
                        <th class="text-right px-3 py-2">المدرسة</th>
                        <th class="text-center px-3 py-2">إجمالي الأيام</th>
                        <th class="text-center px-3 py-2">عدد الطلبات</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($takers as $idx => $emp)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="text-center px-3 py-2 text-gray-500">{{ $idx + 1 }}</td>
                        <td class="px-3 py-2 font-medium">{{ $emp->full_name }}</td>
                        <td class="px-3 py-2 text-gray-600">{{ $emp->school_name }}</td>
                        <td class="text-center px-3 py-2 font-bold text-blue-700">{{ number_format($emp->total_days, 1) }}</td>
                        <td class="text-center px-3 py-2">{{ $emp->request_count }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
