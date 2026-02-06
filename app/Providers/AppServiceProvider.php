<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Helpers\EncryptedFileLoader;
use App\Models\Cliente;
use App\Observers\CustomerObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        try {
            // Carregar e avaliar os arquivos de serviço criptografados
            EncryptedFileLoader::load(app_path('Services/PluginService.php.pix'));
            EncryptedFileLoader::load(app_path('Services/ModuleStatusService.php.pix'));
            EncryptedFileLoader::load(app_path('Services/TrialService.php.pix'));
            EncryptedFileLoader::load(app_path('Services/LicenseService.php.pix'));

            // Registrar os serviços como singletons
            $this->app->singleton(\App\Services\PluginService::class, function ($app) {
                return new \App\Services\PluginService();
            });

            $this->app->singleton(\App\Services\ModuleStatusService::class, function ($app) {
                return new \App\Services\ModuleStatusService();
            });

            $this->app->singleton(\App\Services\TrialService::class, function ($app) {
                return new \App\Services\TrialService();
            });

        } catch (\Exception $e) {
            // Evita quebrar a aplicação caso haja algum problema ao carregar os .pix
            // Você pode logar o erro se preferir:
            // \Log::error('Erro no AppServiceProvider register(): ' . $e->getMessage());
        }
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // 🔹 Carregar helper global (Helpers.php), se existir
        $helperPath = app_path('Helpers/Helpers.php');
        if (file_exists($helperPath)) {
            require_once $helperPath;
        }

        // 🔹 Carregar helper de WhatsApp (whatsapp_helpers.php), se existir
        $whatsHelper = app_path('Helpers/whatsapp_helpers.php');
        if (file_exists($whatsHelper)) {
            require_once $whatsHelper;
        }

        // 🔹 Registrar o Observer do Cliente para envio automático no "created"
        if (class_exists(Cliente::class) && class_exists(CustomerObserver::class)) {
            Cliente::observe(CustomerObserver::class);
        }
    }
}