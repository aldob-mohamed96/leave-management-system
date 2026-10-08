<?php

namespace App\Filament\Resources;

use App\Enums\OrganizationType;
use App\Filament\Resources\EmployeeResource\Pages;
use App\Models\Employee;
use App\Models\EntitlementGrade;
use App\Models\Organization;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

/**
 * مورد إدارة بيانات الموظفين مع عرض رصيد الإجازات.
 */
class EmployeeResource extends Resource
{
    protected static ?string $model = Employee::class;

    protected static ?string $navigationGroup = 'الموظفون';
    protected static ?string $navigationLabel = 'الموظفون';
    protected static ?string $navigationIcon  = 'heroicon-o-identification';
    protected static ?string $modelLabel      = 'موظف';
    protected static ?string $pluralModelLabel = 'الموظفون';

    public static function canViewAny(): bool
    {
        $user = auth()->user();
        $user?->setOrganizationTeam();

        return (bool) $user?->can('viewAny', Employee::class);
    }

    public static function isSchoolActor(): bool
    {
        return auth()->user()?->organization?->isSchool() ?? false;
    }

    // -------------------------------------------------------------------------
    // Form
    // -------------------------------------------------------------------------

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('employee_code')
                ->label('كود الموظف')
                ->required()
                ->maxLength(50)
                ->extraInputAttributes(['dir' => 'ltr', 'style' => 'unicode-bidi: plaintext;'])
                ->dehydrateStateUsing(fn (?string $state): ?string => $state === null
                    ? null
                    : trim((string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{200B}-\x{200D}\x{FEFF}]/u', '', $state)))
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at'),
                )
                ->validationMessages([
                    'unique' => 'كود الموظف مستخدم بالفعل، اختر كوداً آخر.',
                    'required' => 'كود الموظف مطلوب.',
                ]),

            Forms\Components\TextInput::make('full_name')
                ->label('الاسم الكامل')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('job_title')
                ->label('المسمى الوظيفي')
                ->maxLength(255),

            Forms\Components\Select::make('organization_id')
                ->label('المؤسسة')
                ->options(fn (): array => static::organizationOptions())
                ->searchable()
                ->required(fn (): bool => ! static::isSchoolActor())
                ->visible(fn (): bool => ! static::isSchoolActor())
                ->default(fn () => auth()->user()?->organization_id),

            Forms\Components\Select::make('entitlement_grade')
                ->label('الدرجة الوظيفية')
                ->options(function (?Employee $record): array {
                    $options = EntitlementGrade::options(activeOnly: true);

                    if ($record?->entitlement_grade && ! isset($options[$record->entitlement_grade])) {
                        $current = EntitlementGrade::findByCode($record->entitlement_grade);
                        if ($current) {
                            $options[$current->code] = "{$current->name} — {$current->yearly_days} يوم (غير نشط)";
                        }
                    }

                    return $options;
                })
                ->searchable()
                ->helperText('تُدار الدرجات من الإعدادات ← الدرجات الوظيفية'),

            // الإدارة: ربط بحساب موجود (بدل إنشاء جديد)
            Forms\Components\Select::make('user_id')
                ->label('ربط بحساب موجود')
                ->options(
                    User::orderBy('name')
                        ->pluck('name', 'id')
                        ->toArray()
                )
                ->searchable()
                ->nullable()
                ->placeholder('— لا يوجد —')
                ->visible(fn (Get $get): bool => ! static::isSchoolActor() && ! (bool) $get('is_system_employee'))
                ->helperText('أو فعّل «إنشاء حساب دخول» بالأسفل بدل الربط.'),

            // مدرسة أو إدارة: إنشاء حساب دخول جديد مرتبط بالموظف
            Forms\Components\Section::make('حساب دخول للنظام')
                ->description('فعّل الخيار إذا كان الموظف يحتاج الدخول للنظام. الدور: موظف مدرسة.')
                ->schema([
                    Forms\Components\Toggle::make('is_system_employee')
                        ->label('إنشاء حساب دخول')
                        ->live()
                        ->dehydrated(false)
                        ->default(fn (?Employee $record): bool => filled($record?->user_id))
                        ->disabled(fn (?Employee $record): bool => filled($record?->user_id)),

                    Forms\Components\Placeholder::make('linked_user_info')
                        ->label('الحساب المرتبط')
                        ->content(function (?Employee $record): string {
                            if (! $record?->user) {
                                return '—';
                            }

                            $parts = array_filter([
                                $record->user->email,
                                $record->user->phone ? 'تليفون: '.$record->user->phone : null,
                            ]);

                            return implode(' | ', $parts) ?: '—';
                        })
                        ->visible(fn (?Employee $record): bool => filled($record?->user_id)),

                    Forms\Components\TextInput::make('system_email')
                        ->label('البريد الإلكتروني')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(table: User::class, column: 'email')
                        ->visible(fn (Get $get, ?Employee $record): bool => (bool) $get('is_system_employee') && blank($record?->user_id))
                        ->dehydrated(false)
                        ->validationMessages([
                            'unique' => 'البريد الإلكتروني مستخدم بالفعل.',
                            'required' => 'البريد الإلكتروني مطلوب لحساب الدخول.',
                        ]),

                    Forms\Components\TextInput::make('system_phone')
                        ->label('رقم التليفون (للدخول)')
                        ->tel()
                        ->maxLength(20)
                        ->nullable()
                        ->default(fn (Get $get): ?string => $get('phone'))
                        ->dehydrateStateUsing(fn (?string $state): ?string => User::normalizePhone($state))
                        ->unique(table: User::class, column: 'phone')
                        ->visible(fn (Get $get, ?Employee $record): bool => (bool) $get('is_system_employee') && blank($record?->user_id))
                        ->dehydrated(false)
                        ->helperText('يمكن الدخول بهذا الرقم بدل البريد.')
                        ->validationMessages([
                            'unique' => 'رقم التليفون مستخدم بالفعل.',
                        ]),

                    Forms\Components\TextInput::make('system_password')
                        ->label('كلمة المرور')
                        ->password()
                        ->revealable()
                        ->required()
                        ->minLength(8)
                        ->visible(fn (Get $get, ?Employee $record): bool => (bool) $get('is_system_employee') && blank($record?->user_id))
                        ->dehydrated(false)
                        ->validationMessages([
                            'required' => 'كلمة المرور مطلوبة لحساب الدخول.',
                            'min' => 'كلمة المرور يجب ألا تقل عن 8 أحرف.',
                        ]),
                ])
                ->columns(1),

            Forms\Components\DatePicker::make('birth_date')
                ->label('تاريخ الميلاد')
                ->nullable(),

            Forms\Components\DatePicker::make('hire_date')
                ->label('تاريخ التعيين')
                ->nullable(),

            Forms\Components\DatePicker::make('work_start_date')
                ->label('تاريخ بداية العمل')
                ->nullable(),

            Forms\Components\TextInput::make('phone')
                ->label('رقم الهاتف')
                ->tel()
                ->maxLength(20)
                ->live(onBlur: true)
                ->dehydrateStateUsing(fn (?string $state): ?string => User::normalizePhone($state)),

            Forms\Components\Toggle::make('is_active')
                ->label('نشط')
                ->default(true),
        ]);
    }

    /**
     * Create a system login user for an employee and assign school_employee role.
     */
    public static function provisionSystemUser(
        Employee $employee,
        string $email,
        string $password,
        ?string $phone = null,
    ): User {
        $organizationId = (int) $employee->organization_id;
        $phone = User::normalizePhone($phone ?: $employee->phone);

        $user = User::create([
            'name'                 => $employee->full_name,
            'email'                => $email,
            'phone'                => $phone,
            'password'             => $password,
            'organization_id'      => $organizationId,
            'is_active'            => true,
            'must_change_password' => true,
        ]);

        setPermissionsTeamId($organizationId);

        $role = \Spatie\Permission\Models\Role::query()
            ->where('name', 'موظف مدرسة')
            ->where('organization_id', $organizationId)
            ->first();

        if ($role) {
            $user->assignRole($role);
        }

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        setPermissionsTeamId(null);

        $employee->update(['user_id' => $user->id]);

        return $user;
    }

    /**
     * @return array<int|string, string>
     */
    public static function organizationOptions(): array
    {
        $user = auth()->user();
        $query = Organization::withoutGlobalScopes()->orderBy('name');

        if ($user?->organization?->isAdministration()) {
            $query->where('parent_id', $user->organization_id)
                ->where('type', OrganizationType::SCHOOL->value);
        } elseif ($user?->organization?->isSchool()) {
            $query->where('id', $user->organization_id);
        }

        return $query->pluck('name', 'id')->toArray();
    }

    // -------------------------------------------------------------------------
    // Table
    // -------------------------------------------------------------------------

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee_code')
                    ->label('الكود')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('full_name')
                    ->label('الاسم الكامل')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('job_title')
                    ->label('المسمى الوظيفي')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('entitlementGrade.name')
                    ->label('الدرجة')
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('organization.name')
                    ->label('المؤسسة')
                    ->placeholder('—')
                    ->sortable()
                    ->visible(fn (): bool => ! static::isSchoolActor()),

                Tables\Columns\IconColumn::make('user_id')
                    ->label('حساب دخول')
                    ->boolean()
                    ->getStateUsing(fn (Employee $record): bool => filled($record->user_id)),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),

                Tables\Columns\ViewColumn::make('balance_badge')
                    ->label('رصيد الإجازات')
                    ->view('filament.columns.balance-badge'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('organization_id')
                    ->label('المؤسسة')
                    ->options(fn (): array => static::organizationOptions())
                    ->visible(fn (): bool => ! static::isSchoolActor()),

                Tables\Filters\SelectFilter::make('entitlement_grade')
                    ->label('الدرجة الوظيفية')
                    ->options(fn (): array => EntitlementGrade::query()
                        ->ordered()
                        ->pluck('name', 'code')
                        ->all()),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('الحالة'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('leave_history')
                    ->label('تاريخ الإجازات')
                    ->icon('heroicon-o-calendar-days')
                    ->color('info')
                    ->url(fn (Employee $record): string => EmployeeResource::getUrl('leave-history', ['record' => $record->id]))
                    ->openUrlInNewTab(false),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('full_name');
    }

    // -------------------------------------------------------------------------
    // Pages
    // -------------------------------------------------------------------------

    public static function getPages(): array
    {
        return [
            'index'         => Pages\ListEmployees::route('/'),
            'create'        => Pages\CreateEmployee::route('/create'),
            'edit'          => Pages\EditEmployee::route('/{record}/edit'),
            'leave-history' => Pages\ViewEmployeeLeaveHistory::route('/{record}/leave-history'),
        ];
    }
}
