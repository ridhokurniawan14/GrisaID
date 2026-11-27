<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigation(function (NavigationBuilder $builder) use ($panel): NavigationBuilder {
                // Urutan grup (atas → bawah). Grup yang tidak tercantum ditaruh sebelum "Pengaturan".
                $groupOrder = ['Data Master', 'Akademik', 'Integrasi', 'Pengaturan'];
                // Urutan menu di dalam grup (label). Yang tidak tercantum ikut di bawahnya, urut navigationSort.
                $itemOrder = [
                    'Siswa',
                    'Guru',
                    'Wali Kelas',
                    'Kelas',
                    'Jurusan',
                    'Tahun Ajaran',
                    'Pengguna',
                    'Peran & Hak Akses',
                    'Pengaturan Lembaga',
                    'Log Aktivitas',
                ];
                $all = collect([...$panel->getResources(), ...$panel->getPages()])
                    ->filter(fn(string $c): bool => ! method_exists($c, 'shouldRegisterNavigation') || $c::shouldRegisterNavigation())
                    ->flatMap(fn(string $c): array => $c::getNavigationItems())
                    ->filter(fn($item): bool => $item->isVisible());

                $rank = function ($item) use ($itemOrder): array {
                    $pos = array_search((string) $item->getLabel(), $itemOrder, true);

                    return [$pos === false ? 999 : $pos, (int) ($item->getSort() ?? 500), (string) $item->getLabel()];
                };
                // Menu tanpa grup (Dasbor, dll.) paling atas.
                $builder->items($all->filter(fn($i): bool => blank($i->getGroup()))->sortBy($rank)->values()->all());

                $groups = $all->filter(fn($i): bool => filled($i->getGroup()))
                    ->groupBy(fn($i): string => (string) $i->getGroup())
                    ->sortBy(function ($items, string $title) use ($groupOrder): int {
                        $pos = array_search($title, $groupOrder, true);

                        return $title === 'Pengaturan' ? 1000 : ($pos === false ? 500 : $pos);
                    })
                    ->map(fn($items, string $title) => NavigationGroup::make($title)->items($items->sortBy($rank)->values()->all()))
                    ->values()
                    ->all();

                return $builder->groups($groups);
            })

            ->renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_START, fn(): string => view('filament.menu-search')->render())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])

            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
