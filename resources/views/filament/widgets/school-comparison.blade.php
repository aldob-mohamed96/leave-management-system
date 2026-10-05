<x-filament-widgets::widget>
    <x-filament::section heading="مقارنة المدارس">
        @php $data = $this->getData(); $rows = $data['rows']; @endphp
        @if($rows->isEmpty())
            <p class="text-sm text-gray-500">لا توجد بيانات.</p>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm" dir="rtl">
                <thead class="bg-gray-50 text-gray-700">
                    <tr>
                        <th class="text-right px-4 py-2">المدرسة</th>
                        <th class="text-center px-4 py-2">الإجمالي</th>
                        <th class="text-center px-4 py-2">معلق</th>
                        <th class="text-center px-4 py-2">معتمد</th>
                        <th class="text-center px-4 py-2">مرفوض</th>
                        <th class="text-center px-4 py-2">نسبة الرفض</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium">{{ $row['name'] }}</td>
                        <td class="text-center px-4 py-2">{{ $row['total_requests'] }}</td>
                        <td class="text-center px-4 py-2 text-amber-600">{{ $row['pending'] }}</td>
                        <td class="text-center px-4 py-2 text-green-600">{{ $row['approved'] }}</td>
                        <td class="text-center px-4 py-2 text-red-600">{{ $row['rejected'] }}</td>
                        <td class="text-center px-4 py-2">
                            <span class="inline-block px-2 py-0.5 rounded-full text-xs
                                {{ $row['rejection_rate'] > 30 ? 'bg-red-100 text-red-700' : 'bg-gray-100 text-gray-600' }}">
                                {{ $row['rejection_rate'] }}%
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
