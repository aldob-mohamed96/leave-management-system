# Implementation Plan: Pending Approval Widget + Label Helper

## Files to Create
- `app/Filament/Widgets/Administration/PendingMyApprovalWidget.php` (new)

## Files to Modify
- `app/Filament/Pages/Dashboard.php`
- `app/Filament/Widgets/Administration/SchoolComparisonWidget.php`
- `app/Filament/Widgets/Administration/TopLeaveTakersWidget.php`
- `app/Filament/Widgets/Administration/AvgResponseTimeWidget.php`
- `app/Models/LeaveRequest.php`
- `app/Filament/Resources/LeaveRequestResource.php`

---

## Part 1 — PendingMyApprovalWidget

### Step 1 — Sort-order renumbering (must come first so sort values are consistent)

Three existing Administration widgets must shift up to make room for the new widget at sort 4.

| File | Current `$sort` | New `$sort` |
|---|---|---|
| `app/Filament/Widgets/Administration/SchoolComparisonWidget.php` | 4 | 5 |
| `app/Filament/Widgets/Administration/TopLeaveTakersWidget.php` | 5 | 6 |
| `app/Filament/Widgets/Administration/AvgResponseTimeWidget.php` | 6 | 7 |

In each file change only the line `protected static ?int $sort = N;`.

**Verify:** `php artisan route:list --quiet` exits 0 (confirms the app boots without errors).

---

### Step 2 — Create PendingMyApprovalWidget

Create the file at `app/Filament/Widgets/Administration/PendingMyApprovalWidget.php`.

```php
<?php

namespace App\Filament\Widgets\Administration;

use App\Enums\LeaveStatus;
use App\Models\LeaveRequest;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class PendingMyApprovalWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        $org = Auth::user()?->organization;
        if (! $org) {
            return false;
        }
        return $org->isAdministration() || $org->isDirectorate();
    }

    protected function getStats(): array
    {
        $user = Auth::user();
        $user->setOrganizationTeam();

        $orgIds = $user->organization?->subtreeIds() ?? [$user->organization_id];

        $stats = [];

        // Role → stage → label mapping
        $roleStageMap = [
            'مسؤول الإجازات' => [
                'stage' => 'leaves_officer',
                'label' => 'بانتظار اعتماد مسؤول الإجازات',
            ],
            'شؤون عاملين' => [
                'stage' => 'hr_affairs',
                'label' => 'بانتظار اعتماد شؤون العاملين',
            ],
            'مدير الإدارة' => [
                'stage' => 'admin_manager',
                'label' => 'بانتظار اعتماد مدير الإدارة',
            ],
        ];

        foreach ($roleStageMap as $role => $meta) {
            if (! $user->hasRole($role)) {
                continue;
            }

            $count = LeaveRequest::withoutGlobalScopes()
                ->whereIn('organization_id', $orgIds)
                ->where('current_stage', $meta['stage'])
                ->whereIn('status', [
                    LeaveStatus::SUBMITTED->value,
                    LeaveStatus::IN_REVIEW->value,
                ])
                ->count();

            $stats[] = Stat::make($meta['label'], $count)
                ->color($count > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clock');
        }

        return $stats;
    }
}
```

**Verify:** `php artisan route:list --quiet` exits 0.

---

### Step 3 — Register widget in Dashboard.php

In `app/Filament/Pages/Dashboard.php`:

1. **Add import** at the top with the other Administration widget imports:
   ```php
   use App\Filament\Widgets\Administration\PendingMyApprovalWidget;
   ```

2. **In `getWidgets()`**, insert `PendingMyApprovalWidget::class` immediately before `SchoolComparisonWidget::class`:
   ```php
   // Administration level (sort 4-7)
   PendingMyApprovalWidget::class,
   SchoolComparisonWidget::class,
   TopLeaveTakersWidget::class,
   AvgResponseTimeWidget::class,
   ```
   The comment update is optional but keeps the inline docs accurate.

**Verify:** `php artisan route:list --quiet` exits 0.

---

## Part 2 — pendingApprovalLabel helper + resource updates

### Step 4 — Add `pendingApprovalLabel` static method to LeaveRequest model

In `app/Models/LeaveRequest.php`, insert the following method **directly after** the closing brace of `stageLabel()` (currently ends around line 193). Do NOT modify `stageLabel()`.

```php
public static function pendingApprovalLabel(?string $stage): string
{
    return match ($stage) {
        'leaves_officer'  => 'بانتظار اعتماد مسؤول الإجازات',
        'hr_affairs'      => 'بانتظار اعتماد شؤون العاملين',
        'admin_manager'   => 'بانتظار اعتماد مدير الإدارة',
        'school_principal', 'direct_manager' => 'بانتظار اعتماد مدير المدرسة',
        default => $stage ? str_replace('_', ' ', $stage) : '—',
    };
}
```

**Verify:** `php artisan route:list --quiet` exits 0.

---

### Step 5 — Update LeaveRequestResource: TABLE `current_stage` column

In `app/Filament/Resources/LeaveRequestResource.php`, locate the TABLE definition for `current_stage` (currently **line 339–342**):

```php
Tables\Columns\TextColumn::make('current_stage')
    ->label('المرحلة الحالية')
    ->formatStateUsing(fn (?string $state): string => LeaveRequest::stageLabel($state))
    ->placeholder('—'),
```

Change the `formatStateUsing` callback from `stageLabel` to `pendingApprovalLabel`:

```php
Tables\Columns\TextColumn::make('current_stage')
    ->label('المرحلة الحالية')
    ->formatStateUsing(fn (?string $state): string => LeaveRequest::pendingApprovalLabel($state))
    ->placeholder('—'),
```

**Verify:** `php artisan route:list --quiet` exits 0.

---

### Step 6 — Update LeaveRequestResource: INFOLIST `current_stage` entry

In `app/Filament/Resources/LeaveRequestResource.php`, locate the INFOLIST `current_stage` TextEntry (currently **line 226–229**):

```php
Infolists\Components\TextEntry::make('current_stage')
    ->label('المرحلة الحالية')
    ->formatStateUsing(fn (?string $state): string => LeaveRequest::stageLabel($state))
    ->placeholder('—'),
```

Change the `formatStateUsing` callback from `stageLabel` to `pendingApprovalLabel`:

```php
Infolists\Components\TextEntry::make('current_stage')
    ->label('المرحلة الحالية')
    ->formatStateUsing(fn (?string $state): string => LeaveRequest::pendingApprovalLabel($state))
    ->placeholder('—'),
```

**Note:** The `current_stage` TextEntry inside the `steps` RepeatableEntry (line ~265) uses `stageLabel` to display the historical step label — leave that one unchanged, it is intentionally separate.

**Verify:** `php artisan route:list --quiet` exits 0 and the app loads without errors.

---

## Summary of all line-level changes

| File | Change |
|---|---|
| `SchoolComparisonWidget.php` | `$sort` 4 → 5 |
| `TopLeaveTakersWidget.php` | `$sort` 5 → 6 |
| `AvgResponseTimeWidget.php` | `$sort` 6 → 7 |
| `PendingMyApprovalWidget.php` | Create new file with `$sort = 4` |
| `Dashboard.php` | Add import + insert widget before SchoolComparisonWidget |
| `LeaveRequest.php` | Add `pendingApprovalLabel()` after `stageLabel()` (~line 193) |
| `LeaveRequestResource.php` line ~341 | TABLE `current_stage` → `pendingApprovalLabel` |
| `LeaveRequestResource.php` line ~228 | INFOLIST `current_stage` → `pendingApprovalLabel` |

## Import statements needed

Only `Dashboard.php` needs a new import:
```php
use App\Filament\Widgets\Administration\PendingMyApprovalWidget;
```

All other files already import `LeaveRequest`, `Auth`, `LeaveStatus`, and `StatsOverviewWidget`/`Stat` where needed. `PendingMyApprovalWidget.php` is a new file whose namespace and use statements are included in the class definition above.
