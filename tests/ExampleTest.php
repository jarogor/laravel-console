<?php

namespace Tests;

use App\Console\Commands\Example;
use Ctl\Concerns\InteractsWithConsole;
use Ctl\Testing\WithFaker;
use Illuminate\Console\Command;

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
