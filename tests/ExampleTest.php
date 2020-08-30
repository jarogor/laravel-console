<?php

namespace Tests;

use App\Console\Commands\Example;
use Illuminate\Console\Command;
use LaravelConsole\Concerns\InteractsWithConsole;
use LaravelConsole\Testing\WithFaker;

class ExampleTest extends TestCase
{
    use InteractsWithConsole;
    use WithFaker;

    /**
     * A basic test example.
     *
     * @return void
     */
    public function testExample()
    {
        $name = $this->faker->word;

        $this->artisan(Example::NAME, [Example::ARG_VALUE => $name])
            ->expectsOutput($name)
            ->assertExitCode(Command::SUCCESS);
    }
}
