<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\Rules\Password;

class ForcePasswordChange extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static string $view = 'filament.pages.force-password-change';

    protected static ?string $slug = 'force-password-change';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'تغيير كلمة المرور';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->must_change_password;
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
                Forms\Components\TextInput::make('current_password')
                    ->label('كلمة المرور الحالية')
                    ->password()
                    ->revealable()
                    ->required()
                    ->currentPassword(),

                Forms\Components\TextInput::make('password')
                    ->label('كلمة المرور الجديدة')
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(Password::defaults())
                    ->different('current_password'),

                Forms\Components\TextInput::make('password_confirmation')
                    ->label('تأكيد كلمة المرور الجديدة')
                    ->password()
                    ->revealable()
                    ->required()
                    ->same('password'),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $user = Filament::auth()->user();

        $user->forceFill([
            'password'             => $data['password'],
            'must_change_password' => false,
        ])->save();

        Notification::make()
            ->title('تم تغيير كلمة المرور بنجاح')
            ->success()
            ->send();

        $this->redirect(Filament::getUrl());
    }

    public function getHeading(): string
    {
        return 'يجب تغيير كلمة المرور';
    }

    public function getSubheading(): ?string
    {
        return 'لأمان حسابك، غيّر كلمة المرور المؤقتة قبل متابعة استخدام النظام.';
    }
}
