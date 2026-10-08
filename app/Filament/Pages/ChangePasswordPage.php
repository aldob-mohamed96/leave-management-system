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

class ChangePasswordPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-lock-closed';
    protected static ?string $navigationLabel = 'تغيير كلمة المرور';
    protected static ?string $navigationGroup = 'الإعدادات';
    protected static ?int $navigationSort = 10;
    protected static string $view = 'filament.pages.change-password-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('current_password')
                    ->label('كلمة المرور الحالية')
                    ->password()
                    ->required()
                    ->revealable(),
                TextInput::make('new_password')
                    ->label('كلمة المرور الجديدة')
                    ->password()
                    ->required()
                    ->minLength(8)
                    ->revealable(),
                TextInput::make('new_password_confirmation')
                    ->label('تأكيد كلمة المرور الجديدة')
                    ->password()
                    ->required()
                    ->same('new_password')
                    ->revealable(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if (! Hash::check($data['current_password'], auth()->user()->password)) {
            throw ValidationException::withMessages([
                'data.current_password' => 'كلمة المرور الحالية غير صحيحة',
            ]);
        }

        auth()->user()->update(['password' => Hash::make($data['new_password'])]);

        Notification::make()
            ->title('تم تغيير كلمة المرور بنجاح')
            ->success()
            ->send();

        $this->form->fill();
    }
}
