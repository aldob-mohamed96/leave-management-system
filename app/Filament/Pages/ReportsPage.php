<?php

namespace App\Filament\Pages;

use App\Exports\LeaveRequestsExport;
use App\Models\LeaveType;
use App\Models\Organization;
use App\Services\ReportPdfService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;

class ReportsPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'التقارير والإحصاءات';
    protected static ?string $navigationGroup = 'التقارير';
    protected static ?int    $navigationSort  = 10;
    protected static string  $view = 'filament.pages.reports-page';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();
        $user?->setOrganizationTeam();

        return (bool) $user?->can('view_reports');
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                DatePicker::make('from')
                    ->label('من تاريخ')
                    ->native(false),

                DatePicker::make('to')
                    ->label('إلى تاريخ')
                    ->native(false),

                Select::make('organization_id')
                    ->label('المؤسسة')
                    ->options(fn () => Organization::withoutGlobalScopes()
                        ->whereIn('id', Auth::user()->organization
                            ? Auth::user()->organization->subtreeIds()
                            : Organization::withoutGlobalScopes()->pluck('id')->toArray())
                        ->pluck('name', 'id')
                        ->toArray())
                    ->searchable()
                    ->nullable(),

                Select::make('leave_type_id')
                    ->label('نوع الإجازة')
                    ->options(LeaveType::active()->pluck('name', 'id'))
                    ->nullable(),

                Select::make('status')
                    ->label('الحالة')
                    ->options(collect(\App\Enums\LeaveStatus::cases())
                        ->mapWithKeys(fn($s) => [$s->value => $s->label()])
                        ->toArray())
                    ->nullable(),
            ])
            ->columns(3)
            ->statePath('data');
    }

    public function exportExcel(): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $filters = array_filter($this->data);
        return Excel::download(
            new LeaveRequestsExport($filters),
            'leave-requests-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    public function exportPdf(): \Illuminate\Http\Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $filters = array_filter($this->data);
        $service = app(ReportPdfService::class);
        $pdf     = $service->generateSummary($filters);

        return response()->streamDownload(
            fn () => print($pdf),
            'report-' . now()->format('Y-m-d') . '.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}
