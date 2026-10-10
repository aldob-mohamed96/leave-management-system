<!DOCTYPE html>
<html lang="ar" dir="ltr">
<head>
    <meta charset="UTF-8">
    <title>طلب إجازة - {{ $leaveRequest->employee?->full_name ?? '' }} - {{ $leaveRequest->number }}</title>
    <style>
        /*
         * DomPDF + Ar-PHP: glyphs are pre-shaped into visual LTR order.
         * Document must stay LTR so DomPDF does not reverse them again.
         * text-align:right keeps Arabic alignment; table cell order is
         * written left→right so labels end up on the right visually.
         */
        html, body {
            font-family: DejaVu Sans, sans-serif;
            direction: ltr;
            text-align: right;
            font-size: 11px;
            color: #111;
            margin: 0;
            padding: 0;
        }
        body { padding: 12px 18px; }
        .header-table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .header-table td { vertical-align: top; }
        .org-line { font-size: 12px; font-weight: bold; color: #1e3a5f; line-height: 1.5; text-align: right; }
        .code-box { text-align: left; font-size: 10px; }
        .barcode { margin-top: 4px; text-align: left; }
        .title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            color: #1e3a5f;
            margin: 6px 0 10px;
            border-bottom: 2px solid #1e3a5f;
            padding-bottom: 6px;
        }
        .section-title {
            background: #1e3a5f;
            color: #fff;
            font-weight: bold;
            padding: 4px 8px;
            font-size: 11px;
            margin: 10px 0 5px;
            text-align: right;
        }
        table.data { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        table.data td, table.data th {
            border: 1px solid #999;
            padding: 5px 7px;
            vertical-align: middle;
            text-align: right;
        }
        .label { background: #f0f3f7; font-weight: bold; width: 20%; }
        .value { width: 30%; }
        .statement {
            border: 1px solid #999;
            padding: 8px;
            margin: 8px 0;
            line-height: 1.8;
            font-size: 11px;
            text-align: right;
        }
        .note {
            font-size: 9.5px;
            color: #444;
            margin: 6px 0;
            border: 1px dashed #aaa;
            padding: 5px 7px;
            text-align: right;
        }
        .approval-box {
            border: 1px solid #1e3a5f;
            padding: 8px;
            text-align: center;
            margin-top: 8px;
            min-height: 45px;
        }
        .signature-box {
            min-height: 34px;
            text-align: center;
            vertical-align: middle;
        }
        .signature-name {
            font-size: 12px;
            font-weight: bold;
            color: #1e3a5f;
            line-height: 1.3;
            border-bottom: 1px solid #1e3a5f;
            display: inline-block;
            padding: 0 6px 2px;
        }
        .signature-stamp {
            font-size: 8px;
            color: #555;
            margin-top: 2px;
        }
        .signature-line {
            border-bottom: 1px solid #777;
            height: 22px;
            margin: 6px 10px 2px;
        }
        .footer {
            border-top: 1px solid #ccc;
            margin-top: 12px;
            padding-top: 6px;
            font-size: 9px;
            color: #555;
        }
        .footer table { width: 100%; border-collapse: collapse; }
        .muted { color: #666; }
    </style>
</head>
<body>

    {{-- Header: left = barcode/meta, right = org names (LTR cell order) --}}
    <table class="header-table">
        <tr>
            <td style="width: 38%;" class="code-box">
                <div><strong>رقم الطلب:</strong> {{ $leaveRequest->number }}</div>
                <div class="muted">كود المدرسة: {{ $school?->code ?? $leaveRequest->organization?->code ?? '—' }}</div>
                @if(!empty($barcode))
                    <div class="barcode">
                        <img src="{{ $barcode }}" alt="barcode" style="height: 42px;">
                    </div>
                @endif
            </td>
            <td style="width: 62%;">
                <div class="org-line">{{ $directorate?->name ?? 'مديرية التربية والتعليم بالأقصر' }}</div>
                <div class="org-line">{{ $administration?->name ?? '—' }}</div>
                <div class="org-line">{{ $school?->name ?? $leaveRequest->organization?->name ?? '—' }}</div>
            </td>
        </tr>
    </table>

    <div class="title">طلب إجازة ({{ $leaveTypeShort ?? 'اعتيادية' }})</div>

    <div class="section-title">أولاً: بيانات الموظف</div>
    <table class="data">
        {{-- LTR cell order so rightmost pair is اسم الموظف --}}
        <tr>
            <td class="value">{{ $leaveRequest->employee?->employee_code ?? '—' }}</td>
            <td class="label">كود الموظف</td>
            <td class="value">{{ $leaveRequest->employee?->full_name ?? '—' }}</td>
            <td class="label">اسم الموظف</td>
        </tr>
        <tr>
            <td class="value">{{ $employeeGrade ?? '—' }}</td>
            <td class="label">الدرجة الوظيفية</td>
            <td class="value">{{ $leaveRequest->employee?->job_title ?? '—' }}</td>
            <td class="label">المسمى الوظيفي</td>
        </tr>
        <tr>
            <td class="value">{{ $leaveRequest->employee?->hire_date?->format('Y/m/d') ?? '—' }}</td>
            <td class="label">تاريخ التعيين</td>
            <td class="value">{{ $school?->name ?? $leaveRequest->organization?->name ?? '—' }}</td>
            <td class="label">جهة العمل</td>
        </tr>
        <tr>
            <td class="value">{{ $leaveRequest->employee?->work_start_date?->format('Y/m/d') ?? '—' }}</td>
            <td class="label">تاريخ استلام العمل</td>
            <td class="value">{{ $leaveRequest->employee?->birth_date?->format('Y/m/d') ?? '—' }}</td>
            <td class="label">تاريخ الميلاد</td>
        </tr>
    </table>

    <div class="section-title">ثانياً: بيانات الإجازة</div>
    <div class="statement">
        أرجو الموافقة على منحي
        <strong>({{ $leaveTypeShort ?? 'إجازة' }})</strong>
        مدتها
        (<strong>{{ (int) $leaveRequest->days }}</strong>)
        يوم
        (<strong>{{ $daysInWords }}</strong>)
        اعتباراً من
        <strong>{{ $leaveRequest->start_date?->format('Y/m/d') ?? '—/—/—' }}</strong>
        إلى
        <strong>{{ $leaveRequest->end_date?->format('Y/m/d') ?? '—/—/—' }}</strong>.
        <br>
        تاريخ تحرير الطلب:
        <strong>{{ $leaveRequest->written_at?->format('Y/m/d') ?? $leaveRequest->created_at?->format('Y/m/d') ?? '—' }}</strong>
        &nbsp;&nbsp;|&nbsp;&nbsp;
        القائم بالعمل أثناء الإجازة:
        <strong>{{ $substituteName ?? '—' }}</strong>
        <br>
        السبب: {{ $leaveRequest->reason ?: '—' }}
    </div>

    @if(!empty($showCasualNote))
        <div class="note">
            ملحوظة: لا تُمنح الإجازة الاعتيادية لمدة يوم أو يومين إلا بعد استنفاد رصيد الإجازة العارضة.
        </div>
    @endif

    <div class="section-title">خاص بقسم الإجازات — الرصيد</div>
    <table class="data">
        <tr>
            <td class="value">{{ $liveUsed !== null ? (int) $liveUsed : '—' }} يوم</td>
            <td class="label">السابق منحها</td>
            <td class="value">{{ $liveEntitled !== null ? (int) $liveEntitled : '—' }} يوم</td>
            <td class="label">الإجازة المستحقة</td>
        </tr>
        <tr>
            <td class="value">{{ $leaveRequest->displayStatusLabel() }}</td>
            <td class="label">حالة الطلب</td>
            <td class="value">{{ $liveRemaining !== null ? (int) $liveRemaining : '—' }} يوم</td>
            <td class="label">الرصيد المتبقي</td>
        </tr>
    </table>

    <div class="section-title">ثالثاً: الإجراءات والآراء</div>
    <table class="data">
        <thead>
            <tr>
                <th style="background:#e8edf2; width:26%;">التوقيع / الملاحظة</th>
                <th style="background:#e8edf2; width:18%;">التاريخ</th>
                <th style="background:#e8edf2; width:28%;">الاسم</th>
                <th style="background:#e8edf2; width:28%;">الجهة</th>
            </tr>
        </thead>
        <tbody>
            @foreach($approvalRows as $row)
                <tr>
                    <td class="signature-box">
                        @if(!empty($row['signed']))
                            <div class="signature-name">{{ $row['signature'] }}</div>
                            <div class="signature-stamp">توقيع إلكتروني معتمد</div>
                            @if(!empty($row['note']))
                                <div style="font-size:8px; color:#444; margin-top:2px;">{{ $row['note'] }}</div>
                            @endif
                        @else
                            <div class="signature-line"></div>
                            <div class="signature-stamp">خانة التوقيع</div>
                        @endif
                    </td>
                    <td>{{ $row['date'] ?: '' }}</td>
                    <td>{{ $row['name'] ?: '' }}</td>
                    <td style="font-weight:bold; height:36px;">{{ $row['label'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="approval-box">
        <div style="font-weight:bold; margin-bottom:6px;">رأى مدير الإدارة</div>
        <div style="font-size:13px;">
            @if($leaveRequest->status === \App\Enums\LeaveStatus::APPROVED)
                يعتمد
            @elseif($leaveRequest->status === \App\Enums\LeaveStatus::REJECTED)
                يُرفض
            @else
                ........................
            @endif
        </div>
    </div>

    @if($leaveRequest->status === \App\Enums\LeaveStatus::REJECTED && $leaveRequest->rejection_reason)
        <div style="margin-top:8px; border:1px solid #c0392b; padding:6px; background:#fef2f2; text-align:right;">
            <strong>سبب الرفض:</strong> {{ $leaveRequest->rejection_reason }}
        </div>
    @endif

    <div class="footer">
        <table>
            <tr>
                <td style="width:30%; text-align:left;">
                    @if(!empty($qrCode))
                        {!! $qrCode !!}
                    @endif
                </td>
                <td style="width:70%; text-align:right;">
                    تاريخ الطباعة: {{ $printedAt->format('Y/m/d H:i') }}
                    &nbsp;|&nbsp;
                    {{ $administration?->name ?? '' }}
                    @if($school) — {{ $school->name }} @endif
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
