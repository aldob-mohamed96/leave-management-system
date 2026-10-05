<?php

namespace App\Filament\Widgets\Directorate;

use App\Services\DashboardStatsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class YearlyComparisonWidget extends ChartWidget
{
    protected static ?int $sort = 9;
    protected static ?string $heading = 'مقارنة الطلبات السنوية';

    public static function canView(): bool
    {
        return Auth::user()?->organization?->isDirectorate() ?? false;
    }

    protected function getData(): array
    {
        $service = app(DashboardStatsService::class);
        $org     = Auth::user()->organization;
        $data    = $service->yearlyComparison($org, 3);

        return [
            'datasets' => [
                [
                    'label'           => 'عدد الطلبات',
                    'data'            => array_values($data),
                    'backgroundColor' => ['#3b82f6', '#10b981', '#f59e0b'],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
