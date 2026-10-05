<?php

namespace App\Filament\Widgets\Administration;

use App\Services\DashboardStatsService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class TopLeaveTakersWidget extends Widget
{
    protected static ?int $sort = 5;
    protected static ?string $heading = 'أعلى موظفين استهلاكاً للإجازات';
    protected int | string | array $columnSpan = 'full';
    protected static string $view = 'filament.widgets.top-leave-takers';

    public static function canView(): bool
    {
        $org = Auth::user()?->organization;
        if (! $org) return false;
        return $org->isAdministration() || $org->isDirectorate();
    }

    public function getData(): array
    {
        $service = app(DashboardStatsService::class);
        $org     = Auth::user()->organization;

        return ['takers' => $service->topLeaveTakers($org, 10)];
    }
}
