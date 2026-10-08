<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ChangePhonePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-phone';
    protected static ?string $navigationLabel = 'تغيير رقم الهاتف';
    protected static ?string $navigationGroup = 'الإعدادات';
    protected static ?int $navigationSort = 12;
    protected static string $view = 'filament.pages.change-phone-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('phone')
                    ->label('رقم الهاتف الجديد')
                    ->tel()
                    ->required(),
                TextInput::make('password')
                    ->label('كلمة المرور الحالية (للتأكيد)')
                    ->password()
                    ->required()
                    ->revealable(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if (! Hash::check($data['password'], auth()->user()->password)) {
            throw ValidationException::withMessages([
                'data.password' => 'كلمة المرور غير صحيحة',
            ]);
        }

        auth()->user()->update(['phone' => $data['phone']]);

        Notification::make()
            ->title('تم تغيير رقم الهاتف بنجاح')
            ->success()
            ->send();

        $this->form->fill();
    }
}
