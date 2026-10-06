<?php

namespace App\Filament\Widgets\School;

use App\Services\DashboardStatsService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OnLeaveTodayWidget extends BaseWidget
{
    protected static ?int $sort = 2;
    protected static ?string $heading = 'الموظفون في إجازة اليوم';
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->organization?->isSchool() ?? false;
    }

    public function table(Table $table): Table
    {
        $service = app(DashboardStatsService::class);
        $org     = Auth::user()->organization;
        $records = $service->onLeaveToday($org);

        return $table
            ->query(fn () => \App\Models\LeaveRequest::withoutGlobalScopes()
                ->whereIn('id', $records->pluck('id')->toArray()))
            ->paginated(false)
            ->emptyStateHeading('لا يوجد موظفون في إجازة اليوم')
            ->emptyStateDescription('عند وجود إجازات سارية ستظهر هنا.')
            ->columns([
                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('اسم الموظف')
                    ->searchable(),
                Tables\Columns\TextColumn::make('employee.job_title')
                    ->label('الوظيفة'),
                Tables\Columns\TextColumn::make('leaveType.name')
                    ->label('نوع الإجازة'),
                Tables\Columns\TextColumn::make('start_date')
                    ->label('من')
                    ->date('d F Y'),
                Tables\Columns\TextColumn::make('end_date')
                    ->label('إلى')
                    ->date('d F Y'),
                Tables\Columns\TextColumn::make('days')
                    ->label('عدد الأيام')
                    ->suffix(' يوم'),
            ]);
    }
}
