<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>بيانات تسجيل الدخول — إدارة أرمنت</title>
<style>
  @page { margin: 28px 24px; }
  body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 10px;
    color: #1a1a1a;
    margin: 0;
  }
  h1 {
    font-size: 16px;
    text-align: center;
    color: #1e3a5f;
    margin: 0 0 4px;
  }
  .subtitle {
    text-align: center;
    font-size: 11px;
    color: #444;
    margin-bottom: 10px;
  }
  .banner {
    background: #eef4fb;
    border: 1px solid #c5d6ea;
    padding: 8px 10px;
    margin-bottom: 12px;
    text-align: center;
    font-size: 11px;
  }
  .banner strong {
    font-family: DejaVu Sans Mono, DejaVu Sans, monospace;
    letter-spacing: 1px;
    color: #0b3d6e;
  }
  table {
    width: 100%;
    border-collapse: collapse;
  }
  th {
    background: #1e3a5f;
    color: #fff;
    padding: 6px 5px;
    font-size: 10px;
    text-align: center;
  }
  td {
    padding: 5px 4px;
    border-bottom: 1px solid #dde3ea;
    vertical-align: middle;
  }
  tr:nth-child(even) td { background: #f7f9fc; }
  .org { text-align: right; width: 34%; }
  .role { text-align: center; width: 14%; }
  .email {
    font-family: DejaVu Sans Mono, DejaVu Sans, monospace;
    font-size: 8.5px;
    direction: ltr;
    text-align: left;
    white-space: nowrap;
    width: 38%;
  }
  .pass {
    font-family: DejaVu Sans Mono, DejaVu Sans, monospace;
    text-align: center;
    width: 14%;
    font-size: 10px;
  }
  .note {
    margin-top: 10px;
    font-size: 9px;
    color: #666;
    text-align: center;
  }
</style>
</head>
<body>

<h1>إدارة أرمنت التعليمية — بيانات تسجيل الدخول</h1>
<div class="subtitle">نظام إدارة الإجازات — مديرية التربية والتعليم بالأقصر</div>

<div class="banner">
  كلمة المرور الموحّدة لجميع الحسابات:
  <strong>12345678</strong>
</div>

<table>
  <thead>
    <tr>
      <th>الجهة</th>
      <th>الدور</th>
      <th>البريد الإلكتروني (نص للنسخ)</th>
      <th>كلمة المرور</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($rows as $row)
      <tr>
        <td class="org">{{ $row['org'] }}</td>
        <td class="role">{{ $row['role'] }}</td>
        <td class="email">{{ $row['email_display'] }}</td>
        <td class="pass">12345678</td>
      </tr>
    @endforeach
  </tbody>
</table>

<div class="note">
  رابط الدخول: {{ $loginUrl }} — كلمة المرور لجميع الحسابات: 12345678
</div>

</body>
</html>
