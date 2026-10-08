# إضافة حقل دور الاعتماد للموظفين

تُضيف هذه التغييرات حقل `approval_role` إلى نموذج الموظف، مما يسمح بتعيين دور اعتماد لكل موظف يعكس موضعه في مسار الموافقة على الإجازات. الحقل يُعرض في النموذج كقائمة منسدلة تعكس نوع المؤسسة (مدرسة أو إدارة)، ويظهر في جدول الموظفين كشارة ملونة. يتكامل الحقل مع منطق `provisionSystemUser` لتعيين دور Spatie تلقائيًا عند إنشاء حساب دخول للموظف.

**Watch for:** الاستعلام المزدوج عن `Organization` في نفس تقديم النموذج (confirmed)، وإمكانية أن تُسجَّل قيمة `approval_role` بدون تعيين دور Spatie لها إذا لم يكن الدور موجودًا في قاعدة البيانات (confirmed).

**Verdict**: APPROVED

---

## High-level view

تعمل القائمة المنسدلة بشكل تفاعلي مع `organization_id`، وتُطلب قاعدة البيانات مرتين في كل تحديث: مرة من دالة `options` ومرة من دالة `hidden`. هذا ليس N+1 تقليديًا لكنه استعلام مزدوج غير ضروري يمكن حله بـ `once()` أو بتجاوره في `Get`.

دالة `provisionSystemUser` تُعيّن دور Spatie فقط إذا وُجد في قاعدة البيانات (`if ($role)`). مما يعني أن موظفًا يحمل `approval_role = HR_AFFAIRS` قد يُنشأ له حساب دون أن يُسند إليه الدور `شؤون عاملين` إذا لم يكن موجودًا للمؤسسة المعنية — وهذا صامت تمامًا، لا خطأ ولا تسجيل.

---

<details>
<summary>Issues (2)</summary>

1. **استعلام مزدوج عن Organization في النموذج** — دالتا `options` و`hidden` تستعلمان كلتاهما عن `Organization::withoutGlobalScopes()->find($orgId)` بشكل مستقل في كل تحديث تفاعلي. حلّه بنقل الاستعلام إلى متغير مشترك أو الاعتماد على `once()`.

2. **إسناد دور Spatie صامت عند الفشل** — إذا لم يُوجد دور Spatie المطابق لـ `approval_role` في المؤسسة، يمر الكود بهدوء دون إسناد دور أو تسجيل تحذير. يجب إضافة `Log::warning(...)` على الأقل أو رفع استثناء في بيئة التطوير.

</details>

---

<details>
<summary>Details</summary>

## استعلام مزدوج في النموذج التفاعلي

عند كل تغيير في `organization_id`، يُشغَّل كلٌّ من closure الـ`options` وclosure الـ`hidden` بشكل مستقل، وكلاهما يُنفِّذ:

```php
$org = \App\Models\Organization::withoutGlobalScopes()->find($orgId);
```

هذا يعني استعلامَين إلى قاعدة البيانات في كل تحديث تفاعلي. بالنظر إلى أن Filament يُعيد رسم المكوّن عند كل تغيير في أي حقل reactive، ومع وجود حقول أخرى reactive في النموذج، قد يتراكم هذا. الحل الأمثل هو `once()` لكن هذا داخل closures مختلفة، لذا يمكن استخدام Filament `$get` لتخزين النتيجة مؤقتًا أو ببساطة دمج المنطق ليعتمد `hidden` على `options` بدلًا من إعادة الاستعلام.

## إسناد دور Spatie عند إنشاء حساب الموظف

منطق `provisionSystemUser` صحيح في بنيته: يُحدد الدور المطلوب عبر `match`، ثم يستعلم عنه بشرط `organization_id`، ثم يُسند. الإشكالية في السطر:

```php
if ($role) {
    $user->assignRole($role);
}
```

الغياب الصامت يعني أن موظفًا من نوع `LEAVES_OFFICER` مثلًا في مؤسسة لم تُنشئ لها الأدوار بعد (مؤسسة جديدة لم تمر بـ seeder) سيحصل على حساب دخول بدون أي دور. هذا يمكن أن يُعطله من الوصول لأي شيء في النظام دون أي إشارة. الحل: إضافة `Log::warning` أو `throw new \RuntimeException` في بيئة غير production.

</details>

---

<details>
<summary>الملفات المتأثرة</summary>

| الملف | التغيير |
|---|---|
| `app/Enums/ApprovalRole.php` | enum جديد بأربع حالات وأسماء عربية |
| `app/Models/Employee.php` | إضافة `approval_role` لـ `$fillable` و`$casts` |
| `app/Filament/Resources/EmployeeResource.php` | حقل Select تفاعلي، عمود جدول بشارة، وتحديث `provisionSystemUser` |
| `database/migrations/2026_10_08_150436_add_approval_role_to_employees_table.php` | إضافة عمود `approval_role VARCHAR(30) NULL` بعد `job_title` |

</details>
