<?php

namespace Ctl\Concerns;

use Ctl\Exceptions\Handler;
use ErrorException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\ErrorHandler\Error\FatalError;
use Throwable;

trait RegistersExceptionHandlers
{
    /**
     * Set the error handling for the application.
     */
    protected function registerErrorHandling(): void
    {
        error_reporting(-1);

        set_error_handler(function ($level, $message, $file = '', $line = 0) {
            if (error_reporting() & $level) {
                throw new ErrorException($message, 0, $level, $file, $line);
            }
        });

        set_exception_handler(function ($e) {
            $this->handleException($e);
        });

        register_shutdown_function(function () {
            $this->handleShutdown();
        });
    }

    /**
     * Handle the PHP shutdown event.
     */
    public function handleShutdown(): void
    {
        if (! is_null($error = error_get_last()) && $this->isFatal($error['type'])) {
            $this->handleException($this->fatalErrorFromPhpError($error, 0));
        }
    }

    /**
     * Create a new fatal error instance from an error array.
     */
    protected function fatalErrorFromPhpError(array $error, ?int $traceOffset = null): FatalError
    {
        return new FatalError($error['message'], 0, $error, $traceOffset);
    }

    /**
     * Determine if the error type is fatal.
     */
    protected function isFatal(int $type): bool
    {
        return in_array($type, [E_COMPILE_ERROR, E_CORE_ERROR, E_ERROR, E_PARSE], true);
    }

    /**
     * Handle an uncaught exception instance.
     */
    protected function handleException(Throwable $e): void
    {
        $handler = $this->resolveExceptionHandler();

        $handler->report($e);
        $handler->renderForConsole(new ConsoleOutput(), $e);
    }

    /**
     * Get the exception handler from the container.
     *
     * @return ExceptionHandler
     */
    protected function resolveExceptionHandler(): ExceptionHandler
    {
        if ($this->bound(ExceptionHandler::class)) {
            return $this->make(ExceptionHandler::class);
        }

        return $this->make(Handler::class);
    }
}
