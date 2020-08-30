<?php

namespace Ctl;

use Ctl\Console\ConsoleServiceProvider;
use ErrorException;
use Illuminate\Cache\CacheServiceProvider;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Database\MigrationServiceProvider;
use Illuminate\Encryption\EncryptionServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemServiceProvider;
use Illuminate\Hashing\HashServiceProvider;
use Illuminate\Log\LogManager;
use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Translation\TranslationServiceProvider;
use Illuminate\Validation\ValidationServiceProvider;
use Illuminate\View\ViewServiceProvider;
use Psr\Log\LoggerInterface;
use RuntimeException;

class Application extends Container implements \Illuminate\Contracts\Foundation\Application
{
    use Concerns\RegistersExceptionHandlers;

    /**
     * Indicates if the class aliases have been registered.
     *
     * @var bool
     */
    protected static $aliasesRegistered = false;

    /**
     * The base path of the application installation.
     *
     * @var string
     */
    protected $basePath;

    /**
     * All of the loaded configuration files.
     *
     * @var array
     */
    protected $loadedConfigurations = [];

    /**
     * Indicates if the application has been bootstrapped before.
     *
     * @var bool
     */
    protected $hasBeenBootstrapped = false;

    /**
     * Indicates if the application has "booted".
     *
     * @var bool
     */
    protected $booted = false;

    /**
     * The array of booting callbacks.
     *
     * @var callable[]
     */
    protected $bootingCallbacks = [];

    /**
     * The array of booted callbacks.
     *
     * @var callable[]
     */
    protected $bootedCallbacks = [];

    /**
     * The loaded service providers.
     *
     * @var array
     */
    protected $loadedProviders = [];

    /**
     * The service binding methods that have been executed.
     *
     * @var array
     */
    protected $ranServiceBinders = [];

    /**
     * The custom storage path defined by the developer.
     *
     * @var string
     */
    protected $storagePath;

    /**
     * Indicates if the application is running in the console.
     *
     * @var bool|null
     */
    protected $isRunningInConsole;

    /**
     * The application namespace.
     *
     * @var string
     */
    protected $namespace;

    /**
     * Create a new application instance.
     *
     * @param string|null $basePath
     *
     * @throws ErrorException
     */
    public function __construct(?string $basePath = null)
    {
        $this->basePath = $basePath;

        $this->bootstrapContainer();
        $this->registerErrorHandling();
    }

    /**
     * Bootstrap the application container.
     */
    protected function bootstrapContainer(): void
    {
        static::setInstance($this);

        $this->instance('app', $this);
        $this->instance(self::class, $this);
        $this->instance('env', $this->environment());

        $this->instance('path', $this->path());
        $this->instance('path.base', $this->basePath());
        $this->instance('path.lang', $this->langPath());
        $this->instance('path.config', $this->configPath());
        $this->instance('path.storage', $this->storagePath());
        $this->instance('path.database', $this->databasePath());
        $this->instance('path.resources', $this->resourcePath());
        $this->instance('path.bootstrap', $this->bootstrapPath());

        $this->registerContainerAliases();
    }

    /**
     * Get the version number of the application.
     */
    public function version(): string
    {
        return 'Based on Lumen (7.2.1) (Laravel Components ^7.0)';
    }

    /**
     * Determine if the application is currently down for maintenance.
     */
    public function isDownForMaintenance(): bool
    {
        return false;
    }

    /**
     * Get or check the current application environment.
     *
     * @param mixed
     *
     * @return string|bool
     */
    public function environment(...$environment)
    {
        $env = env('APP_ENV', config('app.env', 'production'));

        if (! empty($environment)) {
            $patterns = is_array($environment[0]) ? $environment[0] : $environment;

            foreach ($patterns as $pattern) {
                if (Str::is($pattern, $env)) {
                    return true;
                }
            }

            return false;
        }

        return $env;
    }

    /**
     * Determine if the given service provider is loaded.
     *
     * @param string $provider
     *
     * @return bool
     */
    public function providerIsLoaded(string $provider): bool
    {
        return isset($this->loadedProviders[$provider]);
    }

    /**
     * Register a service provider with the application.
     *
     * @param ServiceProvider|string $provider
     * @param bool $force
     */
    public function register($provider, $force = false)
    {
        if (! $provider instanceof ServiceProvider) {
            $provider = new $provider($this);
        }

        if (array_key_exists($providerName = get_class($provider), $this->loadedProviders)) {
            return;
        }

        $this->loadedProviders[$providerName] = $provider;

        if (method_exists($provider, 'register')) {
            $provider->register();
        }

        if ($this->booted) {
            $this->bootProvider($provider);
        }
    }

    /**
     * Register a deferred provider and service.
     *
     * @param string $provider
     * @param string|null $service
     *
     * @return void
     */
    public function registerDeferredProvider($provider, $service = null)
    {
        $this->register($provider);
    }

    /**
     * Boots the registered providers.
     */
    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        foreach ($this->loadedProviders as $provider) {
            $this->bootProvider($provider);
        }

        $this->booted = true;
    }

    /**
     * Boot the given service provider.
     *
     * @param ServiceProvider $provider
     *
     * @return mixed
     */
    protected function bootProvider(ServiceProvider $provider)
    {
        if (method_exists($provider, 'boot')) {
            return $this->call([$provider, 'boot']);
        }

        return false;
    }

    /**
     * Resolve the given type from the container.
     *
     * @param string $abstract
     * @param array $parameters
     *
     * @throws BindingResolutionException
     * @return mixed
     */
    public function make($abstract, array $parameters = [])
    {
        $abstract = $this->getAlias($abstract);

        if (array_key_exists($abstract, $this->availableBindings)
            && ! array_key_exists($this->availableBindings[$abstract], $this->ranServiceBinders)
            && ! $this->bound($abstract)
        ) {
            $this->{$method = $this->availableBindings[$abstract]}();

            $this->ranServiceBinders[$method] = true;
        }

        return parent::make($abstract, $parameters);
    }

    /**
     * Register container bindings for the application.
     */
    protected function registerCacheBindings(): void
    {
        $this->singleton('cache', function () {
            return $this->loadComponent('cache', CacheServiceProvider::class);
        });
        $this->singleton('cache.store', function () {
            return $this->loadComponent('cache', CacheServiceProvider::class, 'cache.store');
        });
    }

    /**
     * Register container bindings for the application.
     */
    protected function registerComposerBindings(): void
    {
        $this->singleton('composer', function ($app) {
            return new Composer($app->make('files'), $this->basePath());
        });
    }

    /**
     * Register container bindings for the application.
     */
    protected function registerConfigBindings(): void
    {
        $this->singleton('config', static function () {
            return new ConfigRepository();
        });
    }

    /**
     * Register container bindings for the application.
     */
    protected function registerDatabaseBindings(): void
    {
        $this->singleton('db', function () {
            $this->configure('app');

            return $this->loadComponent(
                'database',
                [DatabaseServiceProvider::class],
                'db'
            );
        });
    }

    /**
     * Register container bindings for the application.
     */
    protected function registerEncrypterBindings(): void
    {
        $this->singleton('encrypter', function () {
            return $this->loadComponent('app', EncryptionServiceProvider::class, 'encrypter');
        });
    }

    /**
     * Register container bindings for the application.
     */
    protected function registerEventBindings(): void
    {
        $this->singleton('events', function () {
            $this->register(EventServiceProvider::class);

            return $this->make('events');
        });
    }

    /**
     * Register container bindings for the application.
     */
    protected function registerFilesBindings(): void
    {
        $this->singleton('files', static function () {
            return new Filesystem();
        });
    }

    /**
     * Register container bindings for the application.
     */
    protected function registerFilesystemBindings(): void
    {
        $this->singleton('filesystem', function () {
            return $this->loadComponent('filesystems', FilesystemServiceProvider::class, 'filesystem');
        });
        $this->singleton('filesystem.disk', function () {
            return $this->loadComponent('filesystems', FilesystemServiceProvider::class, 'filesystem.disk');
        });
    }

    /**
     * Register container bindings for the application.
     */
    protected function registerHashBindings(): void
    {
        $this->singleton('hash', function () {
            $this->register(HashServiceProvider::class);

            return $this->make('hash');
        });
    }

    /**
     * Register container bindings for the application.
     */
    protected function registerLogBindings(): void
    {
        $this->singleton(LoggerInterface::class, function () {
            $this->configure('logging');

            /** @var \Illuminate\Contracts\Foundation\Application $this */
            return new LogManager($this);
        });
    }

    /**
     * Register container bindings for the application.
     *
     * @return void
     */
    protected function registerTranslationBindings()
    {
        $this->singleton('translator', function () {
            $this->configure('app');

            $this->instance('path.lang', $this->getLanguagePath());

            $this->register(TranslationServiceProvider::class);

            return $this->make('translator');
        });
    }

    /**
     * Get the path to the application's language files.
     */
    protected function getLanguagePath(): ?string
    {
        if (is_dir($langPath = $this->basePath() . '/resources/lang')) {
            return $langPath;
        }

        return __DIR__ . '/../resources/lang';
    }

    /**
     * Register container bindings for the application.
     */
    protected function registerValidatorBindings(): void
    {
        $this->singleton('validator', function () {
            $this->register(ValidationServiceProvider::class);

            return $this->make('validator');
        });
    }

    /**
     * Register container bindings for the application.
     */
    protected function registerViewBindings(): void
    {
        $this->singleton('view', function () {
            return $this->loadComponent('view', ViewServiceProvider::class);
        });
    }

    /**
     * Configure and load the given component and provider.
     *
     * @param string $config
     * @param array|string $providers
     * @param string|null $return
     *
     * @throws BindingResolutionException
     * @return mixed
     */
    public function loadComponent(string $config, $providers, ?string $return = null)
    {
        $this->configure($config);

        foreach ((array) $providers as $provider) {
            $this->register($provider);
        }

        return $this->make($return ?: $config);
    }

    /**
     * Load a configuration file into the application.
     *
     * @param string $name
     *
     * @throws BindingResolutionException
     */
    public function configure(string $name): void
    {
        if (isset($this->loadedConfigurations[$name])) {
            return;
        }

        $this->loadedConfigurations[$name] = true;

        $path = $this->getConfigurationPath($name);

        if ($path) {
            $this->make('config')->set($name, require $path);
        }
    }

    /**
     * Get the path to the given configuration file.
     *
     * If no name is provided, then we'll return the path to the config folder.
     *
     * @param string|null $name
     *
     * @return string|null
     */
    public function getConfigurationPath(?string $name = null): ?string
    {
        $nameFile = $name
            ? $name . '.php'
            : '';

        $appConfig = $this->basePath('config') . '/' . $nameFile;

        if (file_exists($appConfig)) {
            return $appConfig;
        }

        if (file_exists($path = __DIR__ . '/../config/' . $nameFile)) {
            return $path;
        }

        return null;
    }

    /**
     * Register the facades for the application.
     *
     * @param bool $aliases
     * @param array $userAliases
     */
    public function withFacades(bool $aliases = true, array $userAliases = []): void
    {
        /** @var \Illuminate\Contracts\Foundation\Application $this */
        Facade::setFacadeApplication($this);

        if ($aliases) {
            $this->withAliases($userAliases);
        }
    }

    /**
     * Register the aliases for the application.
     *
     * @param array $userAliases
     */
    public function withAliases(array $userAliases = []): void
    {
        $defaults = [
            \Illuminate\Support\Facades\Cache::class => 'Cache',
            \Illuminate\Support\Facades\DB::class => 'DB',
            \Illuminate\Support\Facades\Event::class => 'Event',
            \Illuminate\Support\Facades\Log::class => 'Log',
            \Illuminate\Support\Facades\Route::class => 'Route',
            \Illuminate\Support\Facades\Schema::class => 'Schema',
            \Illuminate\Support\Facades\Storage::class => 'Storage',
            \Illuminate\Support\Facades\Validator::class => 'Validator',
        ];

        if (! static::$aliasesRegistered) {
            static::$aliasesRegistered = true;

            $merged = array_merge($defaults, $userAliases);

            foreach ($merged as $original => $alias) {
                class_alias($original, $alias);
            }
        }
    }

    /**
     * Load the Eloquent library for the application.
     * @throws BindingResolutionException
     */
    public function withEloquent(): void
    {
        $this->make('db');
    }

    /**
     * Get the path to the application "app" directory.
     */
    public function path(): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'app';
    }

    /**
     * Get the base path for the application.
     *
     * @param string|null $path
     *
     * @return string
     */
    public function basePath($path = null): string
    {
        if (isset($this->basePath)) {
            return $this->basePath . ($path ? '/' . $path : $path);
        }

        $this->basePath = getcwd();

        return $this->basePath($path);
    }

    /**
     * Get the path to the application configuration files.
     *
     * @param string $path
     *
     * @return string
     */
    public function configPath($path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'config' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the database directory.
     *
     * @param string $path
     *
     * @return string
     */
    public function databasePath($path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'database' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the language files.
     *
     * @return string
     */
    public function langPath(): string
    {
        return $this->resourcePath() . DIRECTORY_SEPARATOR . 'lang';
    }

    /**
     * Get the storage path for the application.
     *
     * @param string|null $path
     *
     * @return string
     */
    public function storagePath(?string $path = ''): string
    {
        return ($this->storagePath ?: $this->basePath . DIRECTORY_SEPARATOR . 'storage') . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Get the path to the resources directory.
     *
     * @param string|null $path
     *
     * @return string
     */
    public function resourcePath($path = ''): string
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'resources' . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    /**
     * Determine if the application events are cached.
     */
    public function eventsAreCached(): bool
    {
        return false;
    }

    /**
     * Determine if we are running unit tests.
     */
    public function runningUnitTests(): bool
    {
        return $this->environment() === 'testing';
    }

    /**
     * Prepare the application to execute a console command.
     *
     * @param bool $aliases
     *
     * @throws BindingResolutionException
     */
    public function prepareForConsoleCommand(bool $aliases = true): void
    {
        $this->withFacades($aliases);

        $this->make('cache');

        $this->configure('database');

        $this->register(MigrationServiceProvider::class);
        $this->register(ConsoleServiceProvider::class);
    }

    /**
     * Get the application namespace.
     *
     * @throws RuntimeException
     */
    public function getNamespace(): string
    {
        if (! is_null($this->namespace)) {
            return $this->namespace;
        }

        $composer = json_decode(file_get_contents(base_path('composer.json')), true);

        foreach ((array) data_get($composer, 'autoload.psr-4') as $namespace => $path) {
            foreach ((array) $path as $pathChoice) {
                if (realpath(app()->path()) === realpath(base_path() . '/' . $pathChoice)) {
                    return $this->namespace = $namespace;
                }
            }
        }

        throw new RuntimeException('Unable to detect application namespace.');
    }

    /**
     * Flush the container of all bindings and resolved instances.
     */
    public function flush()
    {
        parent::flush();

        $this->loadedProviders = [];
        $this->bootedCallbacks = [];
        $this->bootingCallbacks = [];
        $this->reboundCallbacks = [];
        $this->resolvingCallbacks = [];
        $this->availableBindings = [];
        $this->ranServiceBinders = [];
        $this->loadedConfigurations = [];
        $this->afterResolvingCallbacks = [];

        static::$instance = null;
    }

    /**
     * Get the current application locale.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this['config']->get('app.locale');
    }

    /**
     * Set the current application locale.
     *
     * @param string $locale
     *
     * @return void
     */
    public function setLocale($locale)
    {
        $this['config']->set('app.locale', $locale);
        $this['translator']->setLocale($locale);
    }

    /**
     * Determine if application locale is the given locale.
     *
     * @param string $locale
     *
     * @return bool
     */
    public function isLocale($locale)
    {
        return $this->getLocale() == $locale;
    }

    /**
     * Register the core container aliases.
     */
    protected function registerContainerAliases(): void
    {
        $this->aliases = [
            \Illuminate\Contracts\Foundation\Application::class => 'app',
            \Illuminate\Contracts\Cache\Factory::class => 'cache',
            \Illuminate\Contracts\Cache\Repository::class => 'cache.store',
            \Illuminate\Contracts\Config\Repository::class => 'config',
            \Illuminate\Container\Container::class => 'app',
            \Illuminate\Contracts\Container\Container::class => 'app',
            \Illuminate\Database\ConnectionResolverInterface::class => 'db',
            \Illuminate\Database\DatabaseManager::class => 'db',
            \Illuminate\Contracts\Encryption\Encrypter::class => 'encrypter',
            \Illuminate\Contracts\Events\Dispatcher::class => 'events',
            \Illuminate\Contracts\Filesystem\Factory::class => 'filesystem',
            \Illuminate\Contracts\Filesystem\Filesystem::class => 'filesystem.disk',
            \Illuminate\Contracts\Hashing\Hasher::class => 'hash',
            'log' => LoggerInterface::class,
            \Illuminate\Contracts\Translation\Translator::class => 'translator',
            \Illuminate\Contracts\Validation\Factory::class => 'validator',
            \Illuminate\Contracts\View\Factory::class => 'view',
        ];
    }

    /**
     * The available container bindings and their respective load methods.
     *
     * @var array
     */
    public $availableBindings = [
        'cache' => 'registerCacheBindings',
        'cache.store' => 'registerCacheBindings',
        \Illuminate\Contracts\Cache\Factory::class => 'registerCacheBindings',
        \Illuminate\Contracts\Cache\Repository::class => 'registerCacheBindings',
        'composer' => 'registerComposerBindings',
        'config' => 'registerConfigBindings',
        'db' => 'registerDatabaseBindings',
        \Illuminate\Database\Eloquent\Factory::class => 'registerDatabaseBindings',
        'filesystem' => 'registerFilesystemBindings',
        'filesystem.disk' => 'registerFilesystemBindings',
        \Illuminate\Contracts\Filesystem\Filesystem::class => 'registerFilesystemBindings',
        \Illuminate\Contracts\Filesystem\Factory::class => 'registerFilesystemBindings',
        'encrypter' => 'registerEncrypterBindings',
        \Illuminate\Contracts\Encryption\Encrypter::class => 'registerEncrypterBindings',
        'events' => 'registerEventBindings',
        \Illuminate\Contracts\Events\Dispatcher::class => 'registerEventBindings',
        'files' => 'registerFilesBindings',
        'hash' => 'registerHashBindings',
        \Illuminate\Contracts\Hashing\Hasher::class => 'registerHashBindings',
        'log' => 'registerLogBindings',
        \Psr\Log\LoggerInterface::class => 'registerLogBindings',
        'translator' => 'registerTranslationBindings',
        'validator' => 'registerValidatorBindings',
        \Illuminate\Contracts\Validation\Factory::class => 'registerValidatorBindings',
        'view' => 'registerViewBindings',
        \Illuminate\Contracts\View\Factory::class => 'registerViewBindings',
    ];

    /**
     * Get the path to the bootstrap directory.
     *
     * @param string $path Optionally, a path to append to the bootstrap path
     *
     * @return string
     */
    public function bootstrapPath($path = '')
    {
        return $this->basePath . DIRECTORY_SEPARATOR . 'bootstrap'
            . ($path ? DIRECTORY_SEPARATOR . $path : $path);
    }

    public function runningInConsole()
    {
        throw new RuntimeException('Feature not implemented.');
    }

    public function registerConfiguredProviders()
    {
        throw new RuntimeException('Feature not implemented.');
    }

    /**
     * Resolve a service provider instance from the class name.
     *
     * @param string $provider
     *
     * @return ServiceProvider
     */
    public function resolveProvider($provider)
    {
        return new $provider($this);
    }

    /**
     * Determine if the application has booted.
     *
     * @return bool
     */
    public function isBooted()
    {
        return $this->booted;
    }

    /**
     * Register a new boot listener.
     *
     * @param callable $callback
     *
     * @return void
     */
    public function booting($callback)
    {
        $this->bootingCallbacks[] = $callback;
    }

    /**
     * Register a new "booted" listener.
     *
     * @param callable $callback
     *
     * @return void
     */
    public function booted($callback)
    {
        $this->bootedCallbacks[] = $callback;

        if ($this->isBooted()) {
            $this->fireAppCallbacks([$callback]);
        }
    }

    /**
     * Call the booting callbacks for the application.
     *
     * @param callable[] $callbacks
     *
     * @return void
     */
    protected function fireAppCallbacks(array $callbacks)
    {
        foreach ($callbacks as $callback) {
            $callback($this);
        }
    }

    /**
     * Run the given array of bootstrap classes.
     *
     * @param string[] $bootstrappers
     *
     * @return void
     */
    public function bootstrapWith(array $bootstrappers)
    {
        $this->hasBeenBootstrapped = true;

        foreach ($bootstrappers as $bootstrapper) {
            $this['events']->dispatch('bootstrapping: ' . $bootstrapper, [$this]);

            $this->make($bootstrapper)->bootstrap($this);

            $this['events']->dispatch('bootstrapped: ' . $bootstrapper, [$this]);
        }
    }

    /**
     * Determine if the application has been bootstrapped before.
     *
     * @return bool
     */
    public function hasBeenBootstrapped()
    {
        return $this->hasBeenBootstrapped;
    }

    public function getProviders($provider)
    {
        throw new RuntimeException('Feature not implemented.');
    }

    public function loadDeferredProviders()
    {
        throw new RuntimeException('Feature not implemented.');
    }

    public function shouldSkipMiddleware()
    {
        throw new RuntimeException('Feature not implemented.');
    }

    public function terminate()
    {
        throw new RuntimeException('Feature not implemented.');
    }
}
