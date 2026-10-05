<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>تقرير الإجازات</title>
<style>
  body { font-family: DejaVu Sans, Arial, sans-serif; direction: rtl; text-align: right; font-size: 11px; color: #1a1a1a; margin: 20px; }
  h1 { font-size: 18px; text-align: center; color: #1e3a5f; border-bottom: 2px solid #1e3a5f; padding-bottom: 8px; }
  h2 { font-size: 13px; color: #1e3a5f; margin-top: 20px; border-bottom: 1px solid #ccc; padding-bottom: 4px; }
  .meta { background: #f5f7fa; padding: 10px; border-radius: 4px; margin-bottom: 16px; }
  .meta table { width: 100%; }
  .meta td { padding: 3px 8px; }
  .meta td:first-child { font-weight: bold; width: 30%; }
  table.data { width: 100%; border-collapse: collapse; margin-top: 10px; }
  table.data th { background: #1e3a5f; color: #fff; padding: 7px; text-align: right; font-size: 11px; }
  table.data td { padding: 6px 7px; border-bottom: 1px solid #e0e0e0; }
  table.data tr:nth-child(even) { background: #f8f9fb; }
  .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 10px; }
  .footer { margin-top: 30px; font-size: 9px; color: #888; text-align: center; border-top: 1px solid #eee; padding-top: 8px; }
</style>
</head>
<body>

<h1>تقرير نظام إجازات مديرية الأقصر التعليمية</h1>

{{-- Filter Summary --}}
<div class="meta">
  <table>
    <tr>
      <td>المؤسسة:</td>
      <td>{{ $orgName }}</td>
      <td>تاريخ التقرير:</td>
      <td>{{ $generatedAt }}</td>
    </tr>
    <tr>
      <td>نوع الإجازة:</td>
      <td>{{ $leaveTypeName ?? 'الكل' }}</td>
      <td>إجمالي الطلبات:</td>
      <td><strong>{{ $totalRequests }}</strong></td>
    </tr>
    @if(!empty($filters['from']) || !empty($filters['to']))
    <tr>
      <td>الفترة:</td>
      <td colspan="3">{{ $filters['from'] ?? '—' }} إلى {{ $filters['to'] ?? '—' }}</td>
    </tr>
    @endif
  </table>
</div>

{{-- Status Breakdown --}}
<h2>توزيع الطلبات حسب الحالة</h2>
<table class="data">
  <thead>
    <tr>
      <th>الحالة</th>
      <th>عدد الطلبات</th>
      <th>النسبة %</th>
    </tr>
  </thead>
  <tbody>
    @foreach($statusBreakdown as $row)
    <tr>
      <td>{{ $row['label'] }}</td>
      <td>{{ $row['count'] }}</td>
      <td>{{ $totalRequests > 0 ? number_format($row['count'] / $totalRequests * 100, 1) : '0.0' }}%</td>
    </tr>
    @endforeach
  </tbody>
</table>

{{-- Top 10 Leave Takers --}}
@if($topTakers->count() > 0)
<h2>أعلى 10 موظفين في استهلاك الإجازة المعتمدة</h2>
<table class="data">
  <thead>
    <tr>
      <th>#</th>
      <th>اسم الموظف</th>
      <th>المدرسة</th>
      <th>إجمالي الأيام</th>
      <th>عدد الطلبات</th>
    </tr>
  </thead>
  <tbody>
    @foreach($topTakers as $idx => $emp)
    <tr>
      <td>{{ $idx + 1 }}</td>
      <td>{{ $emp['name'] }}</td>
      <td>{{ $emp['school'] }}</td>
      <td><strong>{{ number_format($emp['total_days'], 1) }}</strong></td>
      <td>{{ $emp['count'] }}</td>
    </tr>
    @endforeach
  </tbody>
</table>
@endif

<div class="footer">
  صدر عن نظام إدارة الإجازات — مديرية الأقصر التعليمية &mdash; {{ $generatedAt }}
</div>
</body>
</html>
