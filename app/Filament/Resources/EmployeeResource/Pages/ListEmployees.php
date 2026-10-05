<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Imports\EmployeesImport;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Maatwebsite\Excel\Facades\Excel;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),

            Actions\Action::make('importEmployees')
                ->label('استيراد موظفين')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('info')
                ->form([
                    FileUpload::make('file')
                        ->label('ملف Excel / CSV')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv',
                            'application/vnd.ms-excel',
                        ])
                        ->required()
                        ->disk('local')
                        ->directory('imports/employees'),
                ])
                ->action(function (array $data): void {
                    $path = storage_path('app/' . $data['file']);
                    $import = new EmployeesImport();

                    Excel::import($import, $path);

                    $errors = $import->getErrors();
                    $errorCount = count($errors);

                    if ($errorCount === 0) {
                        Notification::make()
                            ->success()
                            ->title('تم الاستيراد بنجاح')
                            ->send();
                    } else {
                        Notification::make()
                            ->warning()
                            ->title("تم الاستيراد مع {$errorCount} خطأ")
                            ->body(implode("\n", array_slice($errors, 0, 5)))
                            ->send();
                    }
                }),
        ];
    }
}
