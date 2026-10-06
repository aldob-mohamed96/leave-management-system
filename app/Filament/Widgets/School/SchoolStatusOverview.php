<?php

namespace App\Filament\Widgets\School;

use App\Enums\LeaveStatus;
use App\Services\DashboardStatsService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class SchoolStatusOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        $org = Auth::user()?->organization;
        return $org?->isSchool() ?? false;
    }

    protected function getStats(): array
    {
        $org     = Auth::user()->organization;
        $service = app(DashboardStatsService::class);
        $counts  = $service->schoolStatusCounts($org);

        $colorMap = [
            LeaveStatus::DRAFT->value     => 'gray',
            LeaveStatus::SUBMITTED->value => 'info',
            LeaveStatus::IN_REVIEW->value => 'warning',
            LeaveStatus::RETURNED->value  => 'warning',
            LeaveStatus::APPROVED->value  => 'success',
            LeaveStatus::REJECTED->value  => 'danger',
            LeaveStatus::CANCELLED->value => 'danger',
        ];

        $stats = [];
        foreach (LeaveStatus::cases() as $status) {
            $stats[] = Stat::make(
                $status->label(),
                $counts[$status->value] ?? 0
            )->color($colorMap[$status->value] ?? 'gray');
        }

        return $stats;
    }
}
