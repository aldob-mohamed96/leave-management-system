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
        if (! $user) {
            return [];
        }

        $user->setOrganizationTeam();

        $orgIds = $user->organization?->subtreeIds() ?? [$user->organization_id];

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

        $stats = [];

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

            $stats[] = Stat::make($meta['label'], (string) $count)
                ->color($count > 0 ? 'warning' : 'success')
                ->icon('heroicon-o-clock');
        }

        return $stats;
    }
}
