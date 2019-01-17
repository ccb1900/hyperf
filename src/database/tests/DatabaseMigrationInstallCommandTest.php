<?php

namespace HyperfTest\Database;

use Mockery as m;
use PHPUnit\Framework\TestCase;
use Hyperf\Foundation\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Hyperf\Database\Console\Migrations\InstallCommand;
use Hyperf\Database\Migrations\MigrationRepositoryInterface;

class DatabaseMigrationInstallCommandTest extends TestCase
{
    public function tearDown()
    {
        m::close();
    }

    public function testFireCallsRepositoryToInstall()
    {
        $command = new InstallCommand($repo = m::mock(MigrationRepositoryInterface::class));
        $command->setLaravel(new Application);
        $repo->shouldReceive('setSource')->once()->with('foo');
        $repo->shouldReceive('createRepository')->once();

        $this->runCommand($command, ['--database' => 'foo']);
    }

    protected function runCommand($command, $options = [])
    {
        return $command->run(new ArrayInput($options), new NullOutput);
    }
}