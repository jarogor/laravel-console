<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LogProcessor extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'app:log-processor {table}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed the database with records';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $table = ($this->input->getArgument('table'));

        $this->output->success($table);

        return Command::SUCCESS;
    }
}
