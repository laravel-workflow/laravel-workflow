<p align="center">
<img alt="logo" src="https://user-images.githubusercontent.com/1130888/210139313-43f0d7ed-2005-4b71-9149-540f124c2c2f.png">
</p>
<p align="center"><a href="https://github.com/laravel-workflow/laravel-workflow/actions/workflows/php.yml"><img src="https://img.shields.io/github/actions/workflow/status/laravel-workflow/laravel-workflow/php.yml" alt="GitHub Workflow Status"></a> <a href="https://scrutinizer-ci.com/g/laravel-workflow/laravel-workflow/?branch=master"><img src="https://img.shields.io/scrutinizer/quality/g/laravel-workflow/laravel-workflow" alt="Scrutinizer code quality (GitHub/Bitbucket)"></a> <a href="https://scrutinizer-ci.com/g/laravel-workflow/laravel-workflow/?branch=master"><img src="https://img.shields.io/scrutinizer/coverage/g/laravel-workflow/laravel-workflow" alt="Scrutinizer coverage (GitHub/BitBucket)"></a> <a href="https://laravel-workflow.com/docs/installation"><img src="https://img.shields.io/badge/docs-read%20now-brightgreen" alt="Docs"></a> <a href="https://github.com/laravel-workflow/laravel-workflow/blob/master/LICENSE"><img alt="Packagist License" src="https://img.shields.io/packagist/l/laravel-workflow/laravel-workflow?color=bright-green"></a></p>

Laravel Workflow is a package for the Laravel web framework that provides tools for defining and managing workflows and activities. A workflow is a series of interconnected activities that are executed in a specific order to achieve a desired result. Activities are individual tasks or pieces of logic that are executed as part of a workflow.

Laravel Workflow can be used to automate and manage complex processes, such as financial transactions, data analysis, data pipelines, microservices, job tracking, user signup flows and other business processes. By using Laravel Workflow, developers can break down large, complex processes into smaller, modular units that can be easily maintained and updated.

Some key features and benefits of Laravel Workflow include:

- Support for defining workflows and activities using simple, declarative PHP classes.
- Tools for starting, monitoring, and managing workflows, including support for queuing and parallel execution.
- Built-in support for handling errors and retries, ensuring that workflows are executed reliably and consistently.
- Integration with Laravel's queue and event systems, allowing workflows to be executed asynchronously on worker servers.
- Extensive documentation and a growing community of developers who use and contribute to Laravel Workflow.

## Documentation

Documentation for Laravel Workflow can be found on the [Laravel Workflow website](https://laravel-workflow.com/docs/installation).

## Community

You can find us in the [GitHub discussions](https://github.com/laravel-workflow/laravel-workflow/discussions) and also on our [Discord channel](https://discord.gg/xu5aDDpqVy).

## Sample App

There's also a [sample application](https://github.com/laravel-workflow/sample-app) that you can run directly from GitHub in your browser.

## Usage

**1. Create a workflow.**
```php
use Workflow\ActivityStub;
use Workflow\Workflow;

class MyWorkflow extends Workflow
{
    public function execute($name)
    {
        $result = yield ActivityStub::make(MyActivity::class, $name);
        return $result;
    }
}
```

**2. Create an activity.**
```php
use Workflow\Activity;

class MyActivity extends Activity
{
    public function execute($name)
    {
        return "Hello, {$name}!";
    }
}
```

**3. Run the workflow.**
```php
use Workflow\WorkflowStub;

$workflow = WorkflowStub::make(MyWorkflow::class);
$workflow->start('world');
while ($workflow->running());
$workflow->output();
=> 'Hello, world!'
```

## Monitoring

[Waterline](https://github.com/laravel-workflow/waterline) is a separate UI that works nicely alongside Horizon. Think of Waterline as being to workflows what Horizon is to queues.

### Dashboard View

![waterline_dashboard](https://user-images.githubusercontent.com/1130888/202866614-4adad485-60d1-403c-976f-d3063e928287.png)

### Workflow View

![workflow](https://user-images.githubusercontent.com/1130888/202866616-98a214d3-a916-4ae1-952e-ca8267ddf4a7.png)

Refer to https://github.com/laravel-workflow/waterline for installation and configuration instructions.


<sub><sup>"Laravel" is a registered trademark of Taylor Otwell. This project is not affiliated, associated, endorsed, or sponsored by Taylor Otwell, nor has it been reviewed, tested, or certified by Taylor Otwell. The use of the trademark "Laravel" is for informational and descriptive purposes only. Laravel Workflow is not officially related to the Laravel trademark or Taylor Otwell.</sup></sub>
