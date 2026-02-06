<?php

namespace App\Providers;

use App\Services\DashboardTotalsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DashboardTotalsService::class, fn() => new DashboardTotalsService());
    }

    public function boot(): void
    {
        View::composer(['*'], function ($view) {
            // Só injeta nos dashboards/páginas autenticadas
            $route = request()?->route()?->getName();

            if (!$route) return;

            $isDashboardRoute = str_contains($route, 'dashboard') || str_contains($route, 'app-ecommerce-dashboard');

            if (!$isDashboardRoute) return;

            /** @var DashboardTotalsService $svc */
            $svc = app(DashboardTotalsService::class);

            // Tenta auth padrão
            $user = Auth::user();
            $guard = null;

            // Se existir guard cliente, usa-o quando logado
            if (Auth::guard('cliente')->check()) {
                $user  = Auth::guard('cliente')->user();
                $guard = 'cliente';
            }

            $totals = $svc->getTotalsFor($user, $guard);

            $view->with('dashboardTotals', $totals);
        });
    }
}
