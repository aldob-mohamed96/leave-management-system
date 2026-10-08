<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsurePasswordChanged;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(\App\Filament\Auth\Login::class)
            ->colors([
                'primary' => Color::Blue,
            ])
            ->brandName('نظام إدارة الإجازات — مديرية الأقصر')
            ->font('Tajawal', provider: GoogleFontProvider::class)
            ->navigationGroups([
                // Icons belong on items, not groups (Filament forbids both).
                NavigationGroup::make('المؤسسات والمستخدمون'),
                NavigationGroup::make('الموظفون'),
                NavigationGroup::make('طلبات الإجازات'),
                NavigationGroup::make('الإعدادات'),
            ])
            ->navigationItems([
                NavigationItem::make('طلب إجازة جديدة')
                    ->icon('heroicon-o-document-plus')
                    ->group('طلبات الإجازات')
                    ->sort(0)
                    ->url(fn (): string => \App\Filament\Resources\LeaveRequestResource\Pages\CreateLeaveRequest::getUrl())
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.admin.resources.leave-requests.create'))
                    ->visible(fn (): bool => auth()->check() && auth()->user()->can('create', \App\Models\LeaveRequest::class)),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_START,
                fn (): string => Blade::render('filament.hooks.context-bar'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnsurePasswordChanged::class,
            ]);
    }
}
