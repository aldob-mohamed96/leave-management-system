<?php

namespace App\Filament\Widgets\Administration;

use App\Services\DashboardStatsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class AvgResponseTimeWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 7;

    public static function canView(): bool
    {
        $org = Auth::user()?->organization;
        if (! $org) return false;
        return $org->isAdministration() || $org->isDirectorate();
    }

    protected function getStats(): array
    {
        $service = app(DashboardStatsService::class);
        $org     = Auth::user()->organization;

        $avgHours     = $service->avgResponseTime($org);
        $overdueCount = $service->overdueCount($org);

        // Rejection rate
        $allStats = $service->administrationSchoolStats($org);
        $totalReq  = $allStats->sum('total_requests');
        $totalRej  = $allStats->sum('rejected');
        $rejRate   = $totalReq > 0
            ? round($totalRej / $totalReq * 100, 1)
            : 0.0;

        return [
            Stat::make('متوسط وقت الاستجابة', $avgHours . ' ساعة')
                ->description('من التقديم حتى القرار')
                ->color($avgHours > 48 ? 'danger' : 'success')
                ->icon('heroicon-o-clock'),

            Stat::make('نسبة الرفض', $rejRate . '%')
                ->color($rejRate > 20 ? 'danger' : 'warning')
                ->icon('heroicon-o-x-circle'),

            Stat::make('طلبات متأخرة', $overdueCount)
                ->description('بدون اتخاذ إجراء > ' . config('leave.overdue_days', 3) . ' أيام')
                ->color($overdueCount > 0 ? 'danger' : 'success')
                ->icon('heroicon-o-exclamation-triangle'),
        ];
    }
}
