<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;

class YearEndRolloverPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'تجديد الأرصدة السنوية';
    protected static ?string $navigationGroup = 'الإعدادات';
    protected static ?int $navigationSort = 10;
    protected static string $view = 'filament.pages.year-end-rollover-page';

    public ?array $data = [];
    public ?string $output = null;

    public function mount(): void
    {
        $this->form->fill(['year' => now()->year]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Select::make('year')
                ->label('السنة')
                ->options(collect(range(now()->year - 2, now()->year + 1))
                    ->mapWithKeys(fn($y) => [$y => (string) $y])
                    ->toArray())
                ->default(now()->year)
                ->required(),
        ])->statePath('data');
    }

    public function run(): void
    {
        $year = $this->data['year'] ?? now()->year;
        Artisan::call('leave:year-end', ['--year' => $year]);
        $this->output = Artisan::output();
        Notification::make()
            ->success()
            ->title("تم تجديد الأرصدة لسنة {$year}")
            ->send();
    }

    public function dryRun(): void
    {
        $year = $this->data['year'] ?? now()->year;
        Artisan::call('leave:year-end', ['--year' => $year, '--dry-run' => true]);
        $this->output = Artisan::output();
        Notification::make()
            ->info()
            ->title("معاينة تجديد الأرصدة لسنة {$year} (بدون حفظ)")
            ->send();
    }
}
