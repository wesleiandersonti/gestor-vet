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
            $encryptedServices = [
                \App\Services\PluginService::class => app_path('Services/PluginService.php.pix'),
                \App\Services\ModuleStatusService::class => app_path('Services/ModuleStatusService.php.pix'),
                \App\Services\TrialService::class => app_path('Services/TrialService.php.pix'),
                \App\Services\LicenseService::class => app_path('Services/LicenseService.php.pix'),
            ];

            foreach ($encryptedServices as $class => $file) {
                if (!class_exists($class, false)) {
                    EncryptedFileLoader::load($file);
                }

                if (class_exists($class, false)) {
                    $this->app->singleton($class, function () use ($class) {
                        return new $class();
                    });
                }
            }
        } catch (\Throwable $e) {
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
