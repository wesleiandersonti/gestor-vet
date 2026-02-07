<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\CompanyDetail;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class MercadoPagoServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        try {
            if (!Schema::hasTable('users') || !Schema::hasTable('company_details')) {
                return;
            }

            $adminUser = User::where('role_id', 1)->first();
            if ($adminUser) {
                $companyDetail = CompanyDetail::where('user_id', $adminUser->id)->first();
                if ($companyDetail) {
                    Config::set('mercado_pago.public_key', $companyDetail->public_key);
                    Config::set('mercado_pago.access_token', $companyDetail->access_token);
                    Config::set('mercado_pago.site_id', $companyDetail->site_id ?? 'MLB');
                }
            }
        } catch (\Throwable $e) {
            Log::warning('MercadoPagoServiceProvider bootstrap skipped: '.$e->getMessage());
        }
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
