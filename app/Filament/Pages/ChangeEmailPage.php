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
use Illuminate\Validation\Rule;

class ChangeEmailPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'تغيير البريد الإلكتروني';
    protected static ?string $navigationGroup = 'الإعدادات';
    protected static ?int $navigationSort = 11;
    protected static string $view = 'filament.pages.change-email-page';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('email')
                    ->label('البريد الإلكتروني الجديد')
                    ->email()
                    ->required()
                    ->rules([
                        Rule::unique('users', 'email')->ignore(auth()->id()),
                    ]),
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

        auth()->user()->update(['email' => $data['email']]);

        Notification::make()
            ->title('تم تغيير البريد الإلكتروني بنجاح')
            ->success()
            ->send();

        $this->form->fill();
    }
}
