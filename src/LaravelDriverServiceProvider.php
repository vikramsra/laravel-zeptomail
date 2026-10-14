<?php

namespace ZohoMail\LaravelZeptoMail;

use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;
use ZohoMail\LaravelZeptoMail\Transport\ZeptoMailTransport;

class LaravelDriverServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/zeptomail-driver.php', 'zeptomail-driver');
    }

    public function boot(): void
    {
        $this->callAfterResolving('mail.manager', function (MailManager $manager) {
            $manager->extend('zeptomail', function (array $config) {
                $config = array_merge($this->app['config']->get('zeptomail-driver', []), array_filter($config, fn ($value) => $value !== null));

                return new ZeptoMailTransport(
                    (string) ($config['api_key'] ?? ''),
                    (string) ($config['host'] ?? 'zoho.com'),
                    null,
                    (float) ($config['timeout'] ?? 30)
                );
            });
        });

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/zeptomail-driver.php' => config_path('zeptomail-driver.php'),
            ], ['zeptomail-config', 'config']);
        }
    }
}
