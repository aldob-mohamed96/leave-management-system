# New Leave Request Navigation Item

## What changed and where

**File:** `app/Providers/Filament/AdminPanelProvider.php`

1. Added import: `use Filament\Navigation\NavigationItem;`
2. Added a `->navigationItems([...])` call after `->navigationGroups([...])` inside `panel()`:

```php
->navigationItems([
    NavigationItem::make('طلب إجازة جديدة')
        ->icon('heroicon-o-document-plus')
        ->group('طلبات الإجازات')
        ->sort(0)
        ->url(fn (): string => \App\Filament\Resources\LeaveRequestResource\Pages\CreateLeaveRequest::getUrl())
        ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.leave-requests.create'))
        ->visible(fn (): bool => auth()->check() && auth()->user()->can('create', \App\Models\LeaveRequest::class)),
])
```

## Permission check used

`auth()->user()->can('create', \App\Models\LeaveRequest::class)` dispatches through Laravel's policy gate to `LeaveRequestPolicy::create(User $user)`, which calls:

```php
$user->setOrganizationTeam();
return $user->hasPermissionTo('create_leave_request');
```

This is the correct gate — only users with the `create_leave_request` Spatie permission will see the nav item.

The `->visible()` closure is evaluated at render time (not boot time), so there are no boot-time authentication issues.

## Verification output

### PHP syntax check
```
No syntax errors detected in .../AdminPanelProvider.php
```

### route:list --path=leave-requests (relevant rows)
```
GET|HEAD  admin/leave-requests/create  filament.admin.resources.leave-requests.create
```
Route name matches the `->isActiveWhen()` pattern exactly.

### php artisan about
App boots cleanly (Laravel 11.57.0, PHP 8.5.8). No errors.

### Cache cleared
Config and application cache cleared successfully.

## Issues encountered

None. Implementation applied cleanly on first attempt.
