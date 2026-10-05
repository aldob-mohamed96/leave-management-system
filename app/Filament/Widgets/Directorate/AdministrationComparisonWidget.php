<?php

namespace App\Filament\Widgets\Directorate;

use App\Services\DashboardStatsService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class AdministrationComparisonWidget extends Widget
{
    protected static ?int $sort = 8;
    protected static ?string $heading = 'مقارنة الإدارات';
    protected int | string | array $columnSpan = 'full';
    protected static string $view = 'filament.widgets.administration-comparison';

    public static function canView(): bool
    {
        return Auth::user()?->organization?->isDirectorate() ?? false;
    }

    public function getData(): array
    {
        $service = app(DashboardStatsService::class);
        $org     = Auth::user()->organization;

        return ['rows' => $service->directorateAdminStats($org)];
    }
}
