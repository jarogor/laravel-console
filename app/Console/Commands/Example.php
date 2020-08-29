<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

final class Example extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'app:example {name}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Example command';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $name = ($this->input->getArgument('name'));

        $this->output->success($name);

        return Command::SUCCESS;
    }
}
