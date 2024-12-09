<?php

namespace Zestic\GraphQL\AuthComponent\DB;

use Phinx\Console\PhinxApplication;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

class MigrationRunner
{
    private PhinxApplication $app;
    private OutputInterface $output;

    public function __construct(OutputInterface $output = null)
    {
        $this->app = new PhinxApplication();
        $this->app->setAutoExit(false);
        $this->output = $output ?? new NullOutput();
    }

    public function migrate(string $environment = 'development'): int
    {
        $input = new StringInput("migrate -e $environment");
        return $this->app->run($input, $this->output);
    }

    public function reset(string $environment = 'testing'): void
    {
        $this->rollback($environment, '0');

        $this->migrate($environment);
    }

    public function rollback(string $environment = 'development', ?string $target = null): int
    {
        $command = "rollback -e $environment";
        if ($target !== null) {
            $command .= " -t $target";
        }
        $input = new StringInput($command);
        return $this->app->run($input, $this->output);
    }

    public function status(string $environment = 'development'): int
    {
        $input = new StringInput("status -e $environment");
        return $this->app->run($input, $this->output);
    }
}
