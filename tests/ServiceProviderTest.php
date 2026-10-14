<?php

namespace ZohoMail\LaravelZeptoMail\Tests;

use Illuminate\Support\Facades\Mail;
use Orchestra\Testbench\TestCase;
use ZohoMail\LaravelZeptoMail\LaravelDriverServiceProvider;
use ZohoMail\LaravelZeptoMail\Transport\ZeptoMailTransport;

class ServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [LaravelDriverServiceProvider::class];
    }

    protected function defineEnvironment($app)
    {
        $app['config']->set('zeptomail-driver.api_key', 'secret');
        $app['config']->set('mail.mailers.zeptomail', ['transport' => 'zeptomail']);
    }

    public function test_the_zeptomail_transport_is_registered(): void
    {
        $transport = Mail::mailer('zeptomail')->getSymfonyTransport();

        $this->assertInstanceOf(ZeptoMailTransport::class, $transport);
        $this->assertSame('zeptomail', (string) $transport);
    }

    public function test_mailer_config_overrides_package_config(): void
    {
        $this->app['config']->set('mail.mailers.zeptomail', ['transport' => 'zeptomail', 'host' => 'zoho.in']);

        $transport = Mail::mailer('zeptomail')->getSymfonyTransport();
        $endpoint = (fn () => $this->getEndpoint())->call($transport);

        $this->assertSame('https://zeptomail.zoho.in/v1.1/email', $endpoint);
    }

    public function test_the_config_file_is_publishable(): void
    {
        $paths = LaravelDriverServiceProvider::pathsToPublish(LaravelDriverServiceProvider::class, 'zeptomail-config');

        $this->assertCount(1, $paths);
        $this->assertFileExists(array_key_first($paths));
    }
}
