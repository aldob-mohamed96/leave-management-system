<?php

namespace App\Filament\Pages;

use App\Enums\OrganizationType;
use App\Models\Organization;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SchoolDatabasePage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon  = 'heroicon-o-circle-stack';
    protected static ?string $navigationLabel = 'قاعدة البيانات';
    protected static ?string $navigationGroup = 'المؤسسات والمستخدمون';
    protected static ?int    $navigationSort  = 5;
    protected static string  $view = 'filament.pages.school-database-page';
    protected static ?string $slug = 'school-database';

    public static function canAccess(): bool
    {
        $user = Auth::user();
        $user?->setOrganizationTeam();
        $type = $user?->organization?->type;

        return $type === OrganizationType::ADMINISTRATION
            || $type === OrganizationType::DIRECTORATE;
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    protected function getTableQuery(): Builder
    {
        $user = Auth::user();
        $org  = $user?->organization;

        $query = Organization::withoutGlobalScopes()
            ->where('type', OrganizationType::SCHOOL->value);

        if ($org?->isAdministration()) {
            $query->where('parent_id', $org->id);
        } elseif ($org?->isDirectorate()) {
            $adminIds = Organization::withoutGlobalScopes()
                ->where('parent_id', $org->id)
                ->where('type', OrganizationType::ADMINISTRATION->value)
                ->pluck('id');
            $query->whereIn('parent_id', $adminIds);
        } else {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => $this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم المدرسة')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('الكود')
                    ->searchable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->formatStateUsing(fn ($state) => $state instanceof OrganizationType ? $state->label() : $state)
                    ->badge()
                    ->color('primary'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('نشطة')
                    ->boolean(),
            ])
            ->recordUrl(fn (Organization $record): string =>
                SchoolDetailPage::getUrl() . '?school=' . $record->id
            )
            ->emptyStateHeading('لا توجد مدارس');
    }
}
