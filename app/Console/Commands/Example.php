<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputArgument;

final class Example extends Command
{
    public const NAME = 'app:example';

    public const ARG_VALUE = 'value';

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = self::NAME;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Example command';

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            [self::ARG_VALUE, InputArgument::REQUIRED, 'An example argument.'],
        ];
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $value = ($this->input->getArgument(self::ARG_VALUE));

        $this->line($value);

        return Command::SUCCESS;
    }
}
