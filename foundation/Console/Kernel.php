<?php

namespace Ctl\Console;

use Ctl\Application;
use Ctl\Exceptions\Handler;
use Illuminate\Console\Application as Artisan;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Console\Scheduling\ScheduleRunCommand;
use Illuminate\Contracts\Console\Kernel as KernelContract;
use Illuminate\Contracts\Debug\ExceptionHandler;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class Kernel implements KernelContract
{
    /**
     * The application implementation.
     *
     * @var Application
     */
    protected $app;

    /**
     * The Artisan application instance.
     *
     * @var Artisan
     */
    protected $artisan;

    /**
     * Indicates if facade aliases are enabled for the console.
     *
     * @var bool
     */
    protected $aliases = true;

    /**
     * The Artisan commands provided by the application.
     *
     * @var array
     */
    protected $commands = [];

    /**
     * Create a new console kernel instance.
     */
    public function __construct(Application $app)
    {
        $this->app = $app;

        $this->app->prepareForConsoleCommand($this->aliases);
        $this->defineConsoleSchedule();
    }

    /**
     * Define the application's command schedule.
     */
    protected function defineConsoleSchedule(): void
    {
        $this->app->instance(
            Schedule::class, $schedule = new Schedule
        );

        $this->schedule($schedule);
    }

    /**
     * Run the console application.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    public function handle($input, $output = null)
    {
        try {
            $this->app->boot();

            return $this->getArtisan()->run($input, $output);
        } catch (Throwable $e) {
            $this->reportException($e);

            $this->renderException($output, $e);

            return 1;
        }
    }

    /**
     * Terminate the application.
     *
     * @param InputInterface $input
     * @param int $status
     */
    public function terminate($input, $status)
    {
        //
    }

    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     */
    protected function schedule(Schedule $schedule): void
    {
        //
    }

    /**
     * Run an Artisan console command by name.
     *
     * @param string $command
     * @param array $parameters
     *
     * @return int
     */
    public function call($command, array $parameters = [], $outputBuffer = null)
    {
        return $this->getArtisan()->call($command, $parameters, $outputBuffer);
    }

    /**
     * Queue the given console command.
     *
     * @param string $command
     * @param array $parameters
     */
    public function queue($command, array $parameters = [])
    {
        throw new RuntimeException('Queueing Artisan commands is not supported by Lumen.');
    }

    /**
     * Get all of the commands registered with the console.
     *
     * @return array
     */
    public function all()
    {
        return $this->getArtisan()->all();
    }

    /**
     * Get the output for the last run command.
     *
     * @return string
     */
    public function output()
    {
        return $this->getArtisan()->output();
    }

    /**
     * Get the Artisan application instance.
     */
    protected function getArtisan(): Artisan
    {
        if (is_null($this->artisan)) {
            return $this->artisan = (new Artisan(
                $this->app,
                $this->app->make('events'),
                $this->app->version())
            )
                ->resolveCommands($this->getCommands());
        }

        return $this->artisan;
    }

    /**
     * Get the commands to add to the application.
     */
    protected function getCommands(): array
    {
        return array_merge($this->commands, [
            ScheduleRunCommand::class,
        ]);
    }

    /**
     * Report the exception to the exception handler.
     */
    protected function reportException(Throwable $e): void
    {
        $this->resolveExceptionHandler()->report($e);
    }

    /**
     * Report the exception to the exception handler.
     */
    protected function renderException(OutputInterface $output, Throwable $e): void
    {
        $this->resolveExceptionHandler()->renderForConsole($output, $e);
    }

    /**
     * Get the exception handler from the container.
     */
    protected function resolveExceptionHandler(): ExceptionHandler
    {
        if ($this->app->bound(ExceptionHandler::class)) {
            return $this->app->make(ExceptionHandler::class);
        }

        return $this->app->make(Handler::class);
    }
}
