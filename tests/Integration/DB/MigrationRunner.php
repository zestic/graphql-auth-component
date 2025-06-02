<?php

namespace Tests\Integration\DB;

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

    public function migrate(string $environment = 'development', string $config = 'phinx.mysql.yml'): int
    {
        $input = new StringInput("migrate -c $config -e $environment");

        $result = $this->app->run($input, $this->output);

        if ($result !== 0) {
            throw new \RuntimeException("Migration failed with exit code: $result");
        }

        return $result;
    }

    public function reset(string $environment = 'testing', string $config = 'phinx.mysql.yml'): void
    {
        $this->rollback($environment, '0', $config);

        $this->migrate($environment, $config);
    }

    public function rollback(string $environment = 'development', ?string $target = null, string $config = 'phinx.mysql.yml'): int
    {
        $command = "rollback -c $config -e $environment";
        if ($target !== null) {
            $command .= " -t $target";
        }
        $input = new StringInput($command);

        $result = $this->app->run($input, $this->output);

        // Don't throw exception for rollback failures as they might be expected
        // (e.g., when there are no migrations to rollback)

        return $result;
    }

    public function status(string $environment = 'development', string $config = 'phinx.mysql.yml'): int
    {
        $input = new StringInput("status -c $config -e $environment");

        return $this->app->run($input, $this->output);
    }
}
