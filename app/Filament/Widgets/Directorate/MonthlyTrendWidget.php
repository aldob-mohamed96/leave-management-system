<?php

namespace App\Filament\Widgets\Directorate;

use App\Services\DashboardStatsService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class MonthlyTrendWidget extends ChartWidget
{
    protected static ?int $sort = 8;
    protected static ?string $heading = 'اتجاه الطلبات الشهري';
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->organization?->isDirectorate() ?? false;
    }

    protected function getData(): array
    {
        $service = app(DashboardStatsService::class);
        $org     = Auth::user()->organization;
        $counts  = $service->monthlyRequestTrend($org, now()->year);

        $months = [
            'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو',
            'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر',
        ];

        return [
            'datasets' => [
                [
                    'label'           => 'عدد الطلبات',
                    'data'            => $counts,
                    'borderColor'     => '#3b82f6',
                    'backgroundColor' => 'rgba(59,130,246,0.1)',
                    'fill'            => true,
                    'tension'         => 0.4,
                ],
            ],
            'labels' => $months,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
