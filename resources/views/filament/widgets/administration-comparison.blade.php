<x-filament-widgets::widget>
    <x-filament::section heading="مقارنة الإدارات التعليمية">
        @php $rows = $this->getData()['rows']; @endphp
        <div class="overflow-x-auto">
            <table class="w-full text-sm" dir="rtl">
                <thead class="bg-gray-50 text-gray-700">
                    <tr>
                        <th class="text-right px-4 py-2">الإدارة</th>
                        <th class="text-center px-4 py-2">إجمالي الطلبات</th>
                        <th class="text-center px-4 py-2">معتمد</th>
                        <th class="text-center px-4 py-2">معلق</th>
                        <th class="text-center px-4 py-2">متوسط وقت الاستجابة</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                    <tr class="border-t hover:bg-gray-50">
                        <td class="px-4 py-2 font-medium">{{ $row['name'] }}</td>
                        <td class="text-center px-4 py-2">{{ $row['total_requests'] }}</td>
                        <td class="text-center px-4 py-2 text-green-600">{{ $row['approved_count'] }}</td>
                        <td class="text-center px-4 py-2 text-amber-600">{{ $row['pending_count'] }}</td>
                        <td class="text-center px-4 py-2">{{ $row['avg_response_hours'] }} ساعة</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
