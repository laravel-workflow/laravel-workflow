<?php

declare(strict_types=1);

namespace Workflow\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:workflow')]
class WorkflowMakeCommand extends GeneratorCommand
{
    protected $name = 'make:workflow';

    protected $description = 'Create a new workflow class';

    protected $type = 'Workflow';

    protected function getStub()
    {
        return __DIR__ . '/stubs/workflow.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\\' . config('workflows.workflows_folder', 'Workflows');
    }

    protected function getOptions()
    {
        return [['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the workflow already exists']];
    }
}
