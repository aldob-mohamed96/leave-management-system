<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>طلب إجازة - {{ $leaveRequest->number }}</title>
</head>
<body>
<div style="font-family: DejaVu Sans, serif; direction: rtl; font-size: 12px; color: #111; margin: 0; padding: 10px 20px;">

    {{-- ===== HEADER ===== --}}
    <div style="text-align: center; border-bottom: 2px solid #1e3a5f; padding-bottom: 12px; margin-bottom: 16px;">
        <div style="font-size: 14px; color: #555; margin-bottom: 4px;">وزارة التربية والتعليم</div>
        <div style="font-size: 18px; font-weight: bold; color: #1e3a5f; margin-bottom: 4px;">طلب إجازة اعتيادية</div>
        <div style="font-size: 11px; color: #777;">رقم الطلب: <strong>{{ $leaveRequest->number }}</strong></div>
    </div>

    {{-- ===== SECTION 1: Employee Data ===== --}}
    <div style="margin-bottom: 14px;">
        <div style="background: #1e3a5f; color: #fff; font-weight: bold; padding: 5px 8px; font-size: 12px; margin-bottom: 6px;">
            أولاً: بيانات الموظف
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
            <tr>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold; width: 22%;">اسم الموظف</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px; width: 28%;">{{ $leaveRequest->employee?->full_name ?? '—' }}</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold; width: 22%;">كود الموظف</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px; width: 28%;">{{ $leaveRequest->employee?->employee_code ?? '—' }}</td>
            </tr>
            <tr>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold;">المسمى الوظيفي</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px;">{{ $leaveRequest->employee?->job_title ?? '—' }}</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold;">الدرجة الوظيفية</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px;">{{ $leaveRequest->employee?->grade ?? '—' }}</td>
            </tr>
            <tr>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold;">جهة العمل</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px;">{{ $leaveRequest->organization?->name ?? '—' }}</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold;">تاريخ التعيين</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px;">{{ $leaveRequest->employee?->hire_date?->toDateString() ?? '—' }}</td>
            </tr>
            <tr>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold;">تاريخ الميلاد</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px;">{{ $leaveRequest->employee?->birth_date?->toDateString() ?? '—' }}</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold;">تاريخ بداية العمل</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px;">{{ $leaveRequest->employee?->work_start_date?->toDateString() ?? '—' }}</td>
            </tr>
        </table>
    </div>

    {{-- ===== SECTION 2: Leave Data ===== --}}
    <div style="margin-bottom: 14px;">
        <div style="background: #1e3a5f; color: #fff; font-weight: bold; padding: 5px 8px; font-size: 12px; margin-bottom: 6px;">
            ثانياً: بيانات الإجازة
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
            <tr>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold; width: 22%;">نوع الإجازة</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px; width: 28%;">{{ $leaveRequest->leaveType?->name ?? '—' }}</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold; width: 22%;">تاريخ كتابة الطلب</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px; width: 28%;">{{ $leaveRequest->written_at?->toDateString() ?? '—' }}</td>
            </tr>
            <tr>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold;">من تاريخ</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px;">{{ $leaveRequest->start_date?->toDateString() ?? '—' }}</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold;">إلى تاريخ</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px;">{{ $leaveRequest->end_date?->toDateString() ?? '—' }}</td>
            </tr>
            <tr>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold;">عدد الأيام (رقماً)</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px;">{{ $leaveRequest->days }}</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold;">عدد الأيام (كتابةً)</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px;">{{ $daysInWords ?? '—' }}</td>
            </tr>
            <tr>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold;">الموظف البديل</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px;" colspan="3">{{ $leaveRequest->substituteEmployee?->full_name ?? '—' }}</td>
            </tr>
            <tr>
                <td style="border: 1px solid #ccc; padding: 5px 8px; background: #f5f5f5; font-weight: bold;">سبب الإجازة</td>
                <td style="border: 1px solid #ccc; padding: 5px 8px;" colspan="3">{{ $leaveRequest->reason ?? '—' }}</td>
            </tr>
        </table>
    </div>

    {{-- ===== SECTION 3: Opinions ===== --}}
    <div style="margin-bottom: 14px;">
        <div style="background: #1e3a5f; color: #fff; font-weight: bold; padding: 5px 8px; font-size: 12px; margin-bottom: 6px;">
            ثالثاً: آراء الجهات
        </div>
        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
            <thead>
                <tr>
                    <th style="border: 1px solid #ccc; padding: 5px 8px; background: #e8edf2; text-align: right; width: 25%;">الجهة</th>
                    <th style="border: 1px solid #ccc; padding: 5px 8px; background: #e8edf2; text-align: right; width: 25%;">الاسم</th>
                    <th style="border: 1px solid #ccc; padding: 5px 8px; background: #e8edf2; text-align: right; width: 25%;">التاريخ</th>
                    <th style="border: 1px solid #ccc; padding: 5px 8px; background: #e8edf2; text-align: right; width: 25%;">التوقيع</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="border: 1px solid #ccc; padding: 5px 8px; font-weight: bold;">المدير المباشر</td>
                    <td style="border: 1px solid #ccc; padding: 5px 8px; height: 30px;">&nbsp;</td>
                    <td style="border: 1px solid #ccc; padding: 5px 8px;">&nbsp;</td>
                    <td style="border: 1px solid #ccc; padding: 5px 8px;">&nbsp;</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ccc; padding: 5px 8px; font-weight: bold;">مسؤول الإجازات</td>
                    <td style="border: 1px solid #ccc; padding: 5px 8px; height: 30px;">&nbsp;</td>
                    <td style="border: 1px solid #ccc; padding: 5px 8px;">&nbsp;</td>
                    <td style="border: 1px solid #ccc; padding: 5px 8px;">&nbsp;</td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ccc; padding: 5px 8px; font-weight: bold; vertical-align: top;">رصيد الإجازة</td>
                    <td style="border: 1px solid #ccc; padding: 5px 8px;" colspan="3">
                        <span style="margin-left: 8px;">المستحق: {{ $leaveRequest->balance_entitled ?? '—' }}</span>
                        <span style="margin-left: 8px;">المستخدم: {{ $leaveRequest->balance_used ?? '—' }}</span>
                        <span style="margin-left: 8px;">المتبقي: {{ $leaveRequest->balance_remaining ?? '—' }}</span>
                    </td>
                </tr>
                <tr>
                    <td style="border: 1px solid #ccc; padding: 5px 8px; font-weight: bold;">مدير الإدارة</td>
                    <td style="border: 1px solid #ccc; padding: 5px 8px; height: 30px;">&nbsp;</td>
                    <td style="border: 1px solid #ccc; padding: 5px 8px;">&nbsp;</td>
                    <td style="border: 1px solid #ccc; padding: 5px 8px;">&nbsp;</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- ===== SECTION 4: Notes / Rejection ===== --}}
    @if($leaveRequest->status === \App\Enums\LeaveStatus::REJECTED && $leaveRequest->rejection_reason)
    <div style="margin-bottom: 14px;">
        <div style="background: #c0392b; color: #fff; font-weight: bold; padding: 5px 8px; font-size: 12px; margin-bottom: 6px;">
            ملاحظات
        </div>
        <div style="border: 1px solid #e74c3c; padding: 8px; background: #fef2f2; font-size: 11px;">
            <strong>سبب الرفض:</strong> {{ $leaveRequest->rejection_reason }}
        </div>
    </div>
    @endif

    {{-- ===== FOOTER ===== --}}
    <div style="border-top: 1px solid #ccc; padding-top: 10px; margin-top: 16px;">
        <table style="width: 100%; border-collapse: collapse; font-size: 10px;">
            <tr>
                <td style="text-align: right; vertical-align: middle; width: 70%;">
                    <div>صدر بتاريخ: {{ $leaveRequest->decided_at ? $leaveRequest->decided_at->format('Y/m/d') : now()->format('Y/m/d') }}</div>
                    <div style="color: #666; margin-top: 3px;">صفحة 1</div>
                </td>
                <td style="text-align: left; vertical-align: middle; width: 30%;">
                    @if(!empty($qrCode))
                        {!! $qrCode !!}
                    @endif
                </td>
            </tr>
        </table>
    </div>

</div>
</body>
</html>
