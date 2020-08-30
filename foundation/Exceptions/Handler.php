<?php

namespace LaravelConsole\Exceptions;

use Exception;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Application as ConsoleApplication;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class Handler implements ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [];

    /**
     * Report or log an exception.
     * @throws Exception|Throwable
     * @return void
     */
    public function report(Throwable $e)
    {
        if (! $this->shouldntReport($e)) {
            if (method_exists($e, 'report')) {
                return $e->report();
            }

            try {
                $logger = app(LoggerInterface::class);
            } catch (Exception $ex) {
                throw $e; // throw the original exception
            }

            $logger->error($e, ['exception' => $e]);
        }
    }

    /**
     * Determine if the exception should be reported.
     *
     * @param Throwable $e
     *
     * @return bool
     */
    public function shouldReport(Throwable $e)
    {
        return ! $this->shouldntReport($e);
    }

    /**
     * Determine if the exception is in the "do not report" list.
     *
     * @param Throwable $e
     *
     * @return bool
     */
    protected function shouldntReport(Throwable $e)
    {
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Render an exception into an HTTP response.
     * @throws Throwable
     */
    public function render($request, Throwable $e)
    {
        throw new RuntimeException('Feature not implemented.');
    }

    /**
     * Render an exception to the console.
     *
     * @param OutputInterface $output
     * @param Throwable $e
     *
     * @return void
     */
    public function renderForConsole($output, Throwable $e)
    {
        (new ConsoleApplication())->renderThrowable($e, $output);
    }
}
