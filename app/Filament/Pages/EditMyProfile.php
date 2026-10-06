<?php

namespace App\Filament\Pages;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

class EditMyProfile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'حسابي';

    protected static ?string $title = 'حسابي';

    protected static ?string $slug = 'my-profile';

    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.edit-my-profile';

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user();
    }

    public function mount(): void
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        $this->form->fill([
            'name'  => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('بيانات الحساب')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('البريد الإلكتروني')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                table: User::class,
                                column: 'email',
                                ignorable: fn () => Filament::auth()->user(),
                            )
                            ->validationMessages([
                                'unique' => 'البريد الإلكتروني مستخدم بالفعل.',
                            ]),

                        Forms\Components\TextInput::make('phone')
                            ->label('رقم التليفون')
                            ->tel()
                            ->maxLength(20)
                            ->nullable()
                            ->dehydrateStateUsing(fn (?string $state): ?string => User::normalizePhone($state))
                            ->rule(function (): Unique {
                                return (new Unique('users', 'phone'))
                                    ->ignore(Filament::auth()->id());
                            })
                            ->validationMessages([
                                'unique' => 'رقم التليفون مستخدم بالفعل.',
                            ]),
                    ])
                    ->columns(1),

                Forms\Components\Section::make('تغيير كلمة المرور')
                    ->description('اترك الحقول فارغة إذا لم ترد تغيير كلمة المرور.')
                    ->schema([
                        Forms\Components\TextInput::make('current_password')
                            ->label('كلمة المرور الحالية')
                            ->password()
                            ->revealable()
                            ->required(fn (Get $get): bool => filled($get('password')))
                            ->currentPassword()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('password')
                            ->label('كلمة المرور الجديدة')
                            ->password()
                            ->revealable()
                            ->rule(Password::defaults())
                            ->same('password_confirmation')
                            ->dehydrated(fn (?string $state): bool => filled($state)),

                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('تأكيد كلمة المرور الجديدة')
                            ->password()
                            ->revealable()
                            ->required(fn (Get $get): bool => filled($get('password')))
                            ->dehydrated(false),
                    ])
                    ->columns(1),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        /** @var User $user */
        $user = Filament::auth()->user();

        $payload = [
            'name'  => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
        ];

        if (! empty($data['password'])) {
            $payload['password'] = $data['password'];
            $payload['must_change_password'] = false;
        }

        $user->forceFill($payload)->save();

        // Keep linked employee phone in sync when profile phone changes
        if ($user->employee && array_key_exists('phone', $payload)) {
            $user->employee->update(['phone' => $payload['phone']]);
        }

        Notification::make()
            ->title('تم حفظ بيانات الحساب بنجاح')
            ->success()
            ->send();

        $this->form->fill([
            'name'  => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
        ]);
    }
}
