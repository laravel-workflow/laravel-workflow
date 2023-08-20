<?php

declare(strict_types=1);

namespace Workflow\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(name: 'make:workflow')]
class WorkflowMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:workflow';

    /**
     * The name of the console command.
     *
     * This name is used to identify the command during lazy loading.
     *
     * @var string|null
     *
     * @deprecated
     */
    protected static $defaultName = 'make:workflow';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new workflow class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Workflow';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__ . '/stubs/workflow.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace . '\\' . config('workflows.workflows_folder', 'Workflows');
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the workflow already exists']];
    }
}
