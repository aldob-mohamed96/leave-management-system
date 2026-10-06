<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>بيانات تسجيل الدخول — إدارة أرمنت</title>
<style>
  @page { margin: 22px 18px; }
  body {
    font-family: DejaVu Sans, Arial, sans-serif;
    font-size: 9px;
    color: #1a1a1a;
    margin: 0;
  }
  h1 {
    font-size: 15px;
    text-align: center;
    color: #1e3a5f;
    margin: 0 0 4px;
  }
  .subtitle {
    text-align: center;
    font-size: 10px;
    color: #444;
    margin-bottom: 8px;
  }
  .banner {
    background: #eef4fb;
    border: 1px solid #c5d6ea;
    padding: 7px 10px;
    margin-bottom: 10px;
    text-align: center;
    font-size: 10px;
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
    padding: 5px 4px;
    font-size: 9px;
    text-align: center;
  }
  td {
    padding: 4px 3px;
    border-bottom: 1px solid #dde3ea;
    vertical-align: middle;
  }
  tr:nth-child(even) td { background: #f7f9fc; }
  .org { text-align: right; width: 28%; }
  .role { text-align: center; width: 12%; }
  .email {
    font-family: DejaVu Sans Mono, DejaVu Sans, monospace;
    font-size: 8px;
    direction: ltr;
    text-align: left;
    white-space: nowrap;
    width: 30%;
  }
  .phone {
    font-family: DejaVu Sans Mono, DejaVu Sans, monospace;
    direction: ltr;
    text-align: center;
    width: 16%;
    font-size: 9px;
  }
  .pass {
    font-family: DejaVu Sans Mono, DejaVu Sans, monospace;
    text-align: center;
    width: 14%;
    font-size: 9px;
  }
  .note {
    margin-top: 8px;
    font-size: 8px;
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
  <strong>{{ $password }}</strong>
  — يمكن الدخول بالبريد أو رقم التليفون
</div>

<table>
  <thead>
    <tr>
      <th>الجهة</th>
      <th>الدور</th>
      <th>البريد الإلكتروني</th>
      <th>رقم التليفون</th>
      <th>كلمة المرور</th>
    </tr>
  </thead>
  <tbody>
    @foreach ($rows as $row)
      <tr>
        <td class="org">{{ $row['org'] }}</td>
        <td class="role">{{ $row['role'] }}</td>
        <td class="email">{{ $row['email_display'] }}</td>
        <td class="phone">{{ $row['phone'] }}</td>
        <td class="pass">{{ $password }}</td>
      </tr>
    @endforeach
  </tbody>
</table>

<div class="note">
  رابط الدخول: {{ $loginUrl }} — كلمة المرور: {{ $password }}
</div>

</body>
</html>
