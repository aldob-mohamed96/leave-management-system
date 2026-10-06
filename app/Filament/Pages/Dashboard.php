<?php

namespace App\Filament\Pages;

use App\Enums\OrganizationType;
use App\Filament\Widgets\Administration\AvgResponseTimeWidget;
use App\Filament\Widgets\Administration\SchoolComparisonWidget;
use App\Filament\Widgets\Administration\TopLeaveTakersWidget;
use App\Filament\Widgets\Directorate\AdministrationComparisonWidget;
use App\Filament\Widgets\Directorate\MonthlyTrendWidget;
use App\Filament\Widgets\Directorate\YearlyComparisonWidget;
use App\Filament\Widgets\School\OnLeaveTodayWidget;
use App\Filament\Widgets\School\PendingRequestsWidget;
use App\Filament\Widgets\School\SchoolStatusOverview;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'الرئيسية';

    public function getHeading(): string
    {
        $org = $this->currentOrganization();

        return $org?->name ?: 'لوحة تحكم نظام الإجازات';
    }

    public function getSubheading(): ?string
    {
        $user = Auth::user();

        if (! $user) {
            return null;
        }

        $user->setOrganizationTeam();
        $role = $user->getRoleNames()->first() ?: 'بدون دور';
        $org = $this->currentOrganization();

        $parts = ["مرحباً، {$user->name}", $role];

        if ($org?->type === OrganizationType::SCHOOL && $org->parent_id) {
            $admin = Organization::withoutGlobalScopes()->find($org->parent_id);
            if ($admin) {
                $parts[] = $admin->name;
            }
        }

        return implode(' — ', $parts);
    }

    private function currentOrganization(): ?Organization
    {
        $user = Auth::user();

        if (! $user?->organization_id) {
            return null;
        }

        return Organization::withoutGlobalScopes()->find($user->organization_id);
    }

    public function getWidgets(): array
    {
        return [
            // School level (sort 1-3)
            SchoolStatusOverview::class,
            OnLeaveTodayWidget::class,
            PendingRequestsWidget::class,
            // Administration level (sort 4-6)
            SchoolComparisonWidget::class,
            TopLeaveTakersWidget::class,
            AvgResponseTimeWidget::class,
            // Directorate level (sort 7-9)
            MonthlyTrendWidget::class,
            AdministrationComparisonWidget::class,
            YearlyComparisonWidget::class,
        ];
    }
}
