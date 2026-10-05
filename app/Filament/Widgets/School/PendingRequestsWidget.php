<?php

namespace App\Filament\Widgets\School;

use App\Enums\LeaveStatus;
use App\Filament\Resources\LeaveRequestResource;
use App\Services\DashboardStatsService;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class PendingRequestsWidget extends BaseWidget
{
    protected static ?int $sort = 3;
    protected static ?string $heading = 'الطلبات المعلقة';
    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::user()?->organization?->isSchool() ?? false;
    }

    public function table(Table $table): Table
    {
        $service = app(DashboardStatsService::class);
        $org     = Auth::user()->organization;
        $records = $service->pendingRequests($org);
        $overdueDays = config('leave.overdue_days', 3);

        return $table
            ->query(fn () => \App\Models\LeaveRequest::withoutGlobalScopes()
                ->whereIn('id', $records->pluck('id')->toArray()))
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('رقم الطلب')
                    ->url(fn ($record) => LeaveRequestResource::getUrl('view', ['record' => $record])),
                Tables\Columns\TextColumn::make('employee.full_name')
                    ->label('اسم الموظف'),
                Tables\Columns\TextColumn::make('leaveType.name')
                    ->label('نوع الإجازة'),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('تاريخ التقديم')
                    ->dateTime('Y/m/d'),
                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label())
                    ->color(fn ($state) => $state->color()),
                Tables\Columns\IconColumn::make('is_overdue')
                    ->label('متأخر')
                    ->state(fn ($record) => $record->submitted_at
                        && $record->submitted_at->lt(now()->subDays($overdueDays)))
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-circle')
                    ->trueColor('danger')
                    ->falseIcon(''),
            ]);
    }
}
