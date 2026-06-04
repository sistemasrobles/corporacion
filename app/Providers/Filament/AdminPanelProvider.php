<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
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
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('erp')
            ->login()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->brandName('Corporación')
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->navigationGroups([
                NavigationGroup::make('Órdenes'),
                NavigationGroup::make('Configuración'),
            ])
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
            ])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => new HtmlString('
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/es.js"></script>
<style>
/* ── Repeater table ───────────────────────────────────────────────── */
.fi-items-table .fi-fo-repeater-item{position:relative!important;border-radius:0!important;box-shadow:none!important;background:transparent!important;border-bottom:1px solid #f1f5f9}
.fi-items-table .fi-fo-repeater-item>div+div{border-top-width:0!important}
.fi-items-table .fi-fo-repeater-item-header{position:absolute!important;right:4px!important;top:50%!important;transform:translateY(-50%)!important;padding:0!important;background:transparent!important;overflow:visible!important}
.fi-items-table .fi-fo-repeater-item-header ul{margin-left:0!important}
.fi-items-table .fi-fo-repeater-item-content{padding:6px 40px 6px 0!important}
/* ── Flatpickr range ──────────────────────────────────────────────── */
.flatpickr-calendar{border-radius:12px!important;box-shadow:0 20px 60px rgba(0,0,0,.5)!important;border:1px solid #334155!important}
.flatpickr-day.selected,.flatpickr-day.startRange,.flatpickr-day.endRange{background:#4f46e5!important;border-color:#4f46e5!important;color:#fff!important}
.flatpickr-day.inRange{background:rgba(79,70,229,.18)!important;border-color:transparent!important;box-shadow:-5px 0 0 rgba(79,70,229,.18),5px 0 0 rgba(79,70,229,.18)!important}
.flatpickr-day:hover{background:#1e293b!important}
.flatpickr-months .flatpickr-month,.flatpickr-weekdays,.flatpickr-weekday{background:#0f172a!important}
.flatpickr-current-month .flatpickr-monthDropdown-months{background:#0f172a!important}
</style>
')
            );
    }
}
