<?php

namespace LaravelConsole\Testing;

use Exception;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Events;
use Illuminate\Support\Facades\Facade;
use LaravelConsole\Application;
use Mockery;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The application instance.
     *
     * @var Application
     */
    protected $app;

    /**
     * The callbacks that should be run before the application is destroyed.
     *
     * @var array
     */
    protected $beforeApplicationDestroyedCallbacks = [];

    /**
     * Creates the application.
     *
     * Needs to be implemented by subclasses.
     */
    abstract public function createApplication();

    /**
     * Refresh the application instance.
     *
     * @return void
     */
    protected function refreshApplication()
    {
        Facade::clearResolvedInstances();

        $this->app = $this->createApplication();

        $this->app->boot();
    }

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        if (! $this->app) {
            $this->refreshApplication();
        }

        $this->setUpTraits();
    }

    /**
     * Boot the testing helper traits.
     *
     * @return void
     */
    protected function setUpTraits()
    {
        $uses = array_flip(class_uses_recursive(get_class($this)));

        if (isset($uses[DatabaseMigrations::class])) {
            $this->runDatabaseMigrations();
        }

        if (isset($uses[DatabaseTransactions::class])) {
            $this->beginDatabaseTransaction();
        }

        if (isset($uses[WithoutEvents::class])) {
            $this->disableEventsForAllTests();
        }

        if (isset($uses[WithFaker::class])) {
            $this->setUpFaker();
        }
    }

    /**
     * Clean up the testing environment before the next test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        if (class_exists('Mockery')) {
            if (($container = Mockery::getContainer()) !== null) {
                $this->addToAssertionCount($container->mockery_getExpectationCount());
            }

            Mockery::close();
        }

        if ($this->app) {
            foreach ($this->beforeApplicationDestroyedCallbacks as $callback) {
                call_user_func($callback);
            }

            $this->app->flush();
            $this->app = null;
        }
    }

    /**
     * Assert that a given where condition exists in the database.
     *
     * @param string $table
     * @param array $data
     * @param string|null $onConnection
     *
     * @return $this
     */
    protected function seeInDatabase($table, array $data, $onConnection = null)
    {
        $count = $this->app->make('db')->connection($onConnection)->table($table)->where($data)->count();

        self::assertGreaterThan(0, $count, sprintf(
            'Unable to find row in database table [%s] that matched attributes [%s].',
            $table,
            json_encode($data)
        ));

        return $this;
    }

    /**
     * Assert that a given where condition does not exist in the database.
     *
     * @param string $table
     * @param array $data
     * @param string|null $onConnection
     *
     * @return $this
     */
    protected function missingFromDatabase($table, array $data, $onConnection = null)
    {
        return $this->notSeeInDatabase($table, $data, $onConnection);
    }

    /**
     * Assert that a given where condition does not exist in the database.
     *
     * @param string $table
     * @param array $data
     * @param string|null $onConnection
     *
     * @return $this
     */
    protected function notSeeInDatabase($table, array $data, $onConnection = null)
    {
        $count = $this->app->make('db')->connection($onConnection)->table($table)->where($data)->count();

        self::assertEquals(0, $count, sprintf(
            'Found unexpected records in database table [%s] that matched attributes [%s].',
            $table,
            json_encode($data)
        ));

        return $this;
    }

    /**
     * Specify a list of events that should be fired for the given operation.
     *
     * These events will be mocked, so that handlers will not actually be executed.
     *
     * @param array|string $events
     *
     * @return $this
     */
    public function expectsEvents($events)
    {
        $events = is_array($events) ? $events : func_get_args();

        $mock = Mockery::spy(Events\Dispatcher::class);

        $mock->shouldReceive('dispatch')->andReturnUsing(function ($called) use (&$events) {
            foreach ($events as $key => $event) {
                if ((is_object($called) && $called instanceof $event)
                    || (is_string($called) && ($called === $event || is_subclass_of($called, $event)))
                ) {
                    unset($events[$key]);
                }
            }
        });

        $this->beforeApplicationDestroyed(function () use (&$events) {
            if ($events) {
                throw new Exception(
                    'The following events were not fired: [' . implode(', ', $events) . ']'
                );
            }
        });

        $this->app->instance('events', $mock);

        return $this;
    }

    /**
     * Mock the event dispatcher so all events are silenced.
     *
     * @return $this
     */
    protected function withoutEvents()
    {
        $mock = Mockery::mock(Events\Dispatcher::class);

        $mock->shouldReceive('dispatch');

        $this->app->instance('events', $mock);

        return $this;
    }

    /**
     * Call artisan command and return code.
     *
     * @param string $command
     * @param array $parameters
     *
     * @return int
     */
    public function artisan($command, $parameters = [])
    {
        return $this->code = $this->app[Kernel::class]->call($command, $parameters);
    }

    /**
     * Register a callback to be run before the application is destroyed.
     *
     * @param callable $callback
     *
     * @return void
     */
    protected function beforeApplicationDestroyed(callable $callback)
    {
        $this->beforeApplicationDestroyedCallbacks[] = $callback;
    }
}
