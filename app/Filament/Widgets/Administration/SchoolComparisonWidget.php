<?php

namespace App\Filament\Widgets\Administration;

use App\Models\Organization;
use App\Services\DashboardStatsService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class SchoolComparisonWidget extends Widget
{
    protected static ?int $sort = 4;
    protected static ?string $heading = 'مقارنة المدارس';
    protected int | string | array $columnSpan = 'full';
    protected static string $view = 'filament.widgets.school-comparison';

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

        if ($org->isDirectorate()) {
            $admins = Organization::withoutGlobalScopes()
                ->where('parent_id', $org->id)->get();
            $rows = collect();
            foreach ($admins as $adm) {
                $rows = $rows->merge($service->administrationSchoolStats($adm));
            }
        } else {
            $rows = $service->administrationSchoolStats($org);
        }

        return ['rows' => $rows];
    }
}
