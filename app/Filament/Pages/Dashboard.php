<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Administration\AvgResponseTimeWidget;
use App\Filament\Widgets\Administration\SchoolComparisonWidget;
use App\Filament\Widgets\Administration\TopLeaveTakersWidget;
use App\Filament\Widgets\Directorate\AdministrationComparisonWidget;
use App\Filament\Widgets\Directorate\MonthlyTrendWidget;
use App\Filament\Widgets\Directorate\YearlyComparisonWidget;
use App\Filament\Widgets\School\OnLeaveTodayWidget;
use App\Filament\Widgets\School\PendingRequestsWidget;
use App\Filament\Widgets\School\SchoolStatusOverview;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'الرئيسية';

    public function getHeading(): string
    {
        return 'لوحة تحكم نظام الإجازات';
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
