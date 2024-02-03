<?php

declare(strict_types=1);

namespace Workflow\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:activity')]
class ActivityMakeCommand extends GeneratorCommand
{
    protected $name = 'make:activity';

    protected $description = 'Create a new activity class';

    protected $type = 'Activity';

    protected function getStub()
    {
        return __DIR__ . '/stubs/activity.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\\' . config('workflows.workflows_folder', 'Workflows');
    }

    protected function getOptions()
    {
        return [['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the activity already exists']];
    }
}
