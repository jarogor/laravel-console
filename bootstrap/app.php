<?php

require_once __DIR__ . '/../vendor/autoload.php';

$environment = new LaravelConsole\Bootstrap\LoadEnvironmentVariables(
    dirname(__DIR__)
);
$environment->bootstrap();

date_default_timezone_set(
    env('APP_TIMEZONE', 'UTC')
);

/*
 * Create The Application
 */
$application = new LaravelConsole\Application(
    dirname(__DIR__)
);

$application->withFacades();
$application->withEloquent();

/*
 * Register Container Bindings
 */
$application->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    LaravelConsole\Exceptions\Handler::class
);

$application->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

/*
 * Register Config Files
 */
$application->configure('app');

/*
 * Register Service Providers
 */
$application->register(App\Providers\AppServiceProvider::class);
$application->register(App\Providers\EventServiceProvider::class);

return $application;
