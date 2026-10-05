<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تقرير الإجازات</title>
</head>
<body>
<div style="font-family: DejaVu Sans, serif; direction: rtl; font-size: 12px; color: #111; margin: 0; padding: 10px 20px;">

    {{-- ===== HEADER ===== --}}
    <div style="text-align: center; border-bottom: 2px solid #1e3a5f; padding-bottom: 12px; margin-bottom: 16px;">
        <div style="font-size: 14px; color: #555; margin-bottom: 4px;">وزارة التربية والتعليم</div>
        <div style="font-size: 18px; font-weight: bold; color: #1e3a5f; margin-bottom: 4px;">تقرير الإجازات</div>
        <div style="font-size: 11px; color: #777;">تاريخ الإصدار: <strong>{{ now()->format('Y/m/d H:i') }}</strong></div>
    </div>

    {{-- ===== FILTER SUMMARY ===== --}}
    <div style="margin-bottom: 14px;">
        <div style="background: #1e3a5f; color: #fff; font-weight: bold; padding: 5px 8px; font-size: 12px; margin-bottom: 6px;">
            معايير التصفية المطبّقة
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
            <tr>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold; width: 25%;">الفترة الزمنية</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px; width: 75%;">
                    @if(!empty($filters['date_range']['from']) || !empty($filters['date_range']['to']))
                        {{ $filters['date_range']['from'] ?? '—' }}
                        @if(!empty($filters['date_range']['from']) && !empty($filters['date_range']['to']))
                             &larr; 
                        @endif
                        {{ $filters['date_range']['to'] ?? '' }}
                    @else
                        جميع التواريخ
                    @endif
                </td>
            </tr>
            <tr>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold;">الحالة</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px;">
                    @if(!empty($filters['status']))
                        {{ \App\Enums\LeaveStatus::from($filters['status'])->label() }}
                    @else
                        جميع الحالات
                    @endif
                </td>
            </tr>
            <tr>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold;">المؤسسة</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px;">
                    @if(!empty($filters['organization_id']))
                        {{ \App\Models\Organization::withoutGlobalScopes()->find($filters['organization_id'])?->name ?? $filters['organization_id'] }}
                    @else
                        جميع المؤسسات
                    @endif
                </td>
            </tr>
            <tr>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold;">نوع الإجازة</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px;">
                    @if(!empty($filters['leave_type_id']))
                        {{ \App\Models\LeaveType::find($filters['leave_type_id'])?->name ?? $filters['leave_type_id'] }}
                    @else
                        جميع الأنواع
                    @endif
                </td>
            </tr>
        </table>
    </div>

    {{-- ===== STATUS BREAKDOWN ===== --}}
    <div style="margin-bottom: 14px;">
        <div style="background: #1e3a5f; color: #fff; font-weight: bold; padding: 5px 8px; font-size: 12px; margin-bottom: 6px;">
            توزيع الطلبات حسب الحالة
        </div>
        @if(count($statusBreakdown) > 0)
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
            <thead>
                <tr>
                    <th style="border: 1px solid #ccc; padding: 5px 8px; background: #e8edf2; text-align: right; width: 50%;">الحالة</th>
                    <th style="border: 1px solid #ccc; padding: 5px 8px; background: #e8edf2; text-align: right; width: 25%;">العدد</th>
                    <th style="border: 1px solid #ccc; padding: 5px 8px; background: #e8edf2; text-align: right; width: 25%;">النسبة %</th>
                </tr>
            </thead>
            <tbody>
                @foreach($statusBreakdown as $index => $row)
                <tr style="{{ $index % 2 === 0 ? 'background: #ffffff;' : 'background: #f8f9fa;' }}">
                    <td style="border: 1px solid #ccc; padding: 5px 8px;">{{ $row['label'] }}</td>
                    <td style="border: 1px solid #ccc; padding: 5px 8px; text-align: center;">{{ $row['count'] }}</td>
                    <td style="border: 1px solid #ccc; padding: 5px 8px; text-align: center;">{{ $row['percentage'] }}%</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div style="padding: 10px; color: #666; font-size: 11px;">لا توجد بيانات.</div>
        @endif
    </div>

    {{-- ===== TOP 10 EMPLOYEES ===== --}}
    <div style="margin-bottom: 14px;">
        <div style="background: #1e3a5f; color: #fff; font-weight: bold; padding: 5px 8px; font-size: 12px; margin-bottom: 6px;">
            أكثر 10 موظفين استهلاكاً للإجازات
        </div>
        @if(count($topEmployees) > 0)
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
            <thead>
                <tr>
                    <th style="border: 1px solid #ccc; padding: 5px 8px; background: #e8edf2; text-align: right; width: 8%;">الترتيب</th>
                    <th style="border: 1px solid #ccc; padding: 5px 8px; background: #e8edf2; text-align: right; width: 40%;">اسم الموظف</th>
                    <th style="border: 1px solid #ccc; padding: 5px 8px; background: #e8edf2; text-align: right; width: 36%;">المدرسة</th>
                    <th style="border: 1px solid #ccc; padding: 5px 8px; background: #e8edf2; text-align: right; width: 16%;">إجمالي الأيام</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topEmployees as $index => $emp)
                <tr style="{{ $index % 2 === 0 ? 'background: #ffffff;' : 'background: #f8f9fa;' }}">
                    <td style="border: 1px solid #ccc; padding: 5px 8px; text-align: center;">{{ $index + 1 }}</td>
                    <td style="border: 1px solid #ccc; padding: 5px 8px;">{{ $emp['name'] }}</td>
                    <td style="border: 1px solid #ccc; padding: 5px 8px;">{{ $emp['school'] }}</td>
                    <td style="border: 1px solid #ccc; padding: 5px 8px; text-align: center;">{{ $emp['total_days'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @else
        <div style="padding: 10px; color: #666; font-size: 11px;">لا توجد بيانات.</div>
        @endif
    </div>

    {{-- ===== FOOTER ===== --}}
    <div style="border-top: 1px solid #ccc; padding-top: 10px; margin-top: 16px;">
        <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
            <tr>
                <td style="text-align: right; vertical-align: middle;">
                    <div style="color: #666;">صدر بواسطة النظام &mdash; تاريخ الطباعة: {{ now()->format('Y/m/d H:i') }}</div>
                </td>
                <td style="text-align: left; vertical-align: middle;">
                    <div style="color: #666;">صفحة 1</div>
                </td>
            </tr>
        </table>
    </div>

</div>
</body>
</html>
