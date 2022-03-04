# Laravel Workflow [![PHP Composer](https://github.com/laravel-workflow/laravel-workflow/actions/workflows/php.yml/badge.svg)](https://github.com/laravel-workflow/laravel-workflow/actions/workflows/php.yml)

Durable workflow engine that allows users to write long running persistent distributed workflows (orchestrations) in PHP powered by [Laravel queues](https://laravel.com/docs/9.x/queues).

## Installation

This library is installable via [Composer](https://getcomposer.org). You must also publish the migrations for the `workflows` table.

```bash
composer require laravel-workflow/laravel-workflow
php artisan vendor:publish --provider="Workflow\Providers\WorkflowServiceProvider" --tag="migrations"
```

## Requirements

You can use any queue driver that Laravel supports but this is heavily tested against Redis.

## Usage

**1. Create a workflow.**
```php
class MyWorkflow extends Workflow
{
    public function execute()
    {
        $result = yield ActivityStub::make(MyActivity::class);
        return $result;
    }
}
```

**2. Create an activity.**
```php
class MyActivity extends Activity
{
    public function execute()
    {
        return 'activity';
    }
}
```

**3. Run the workflow.**
```php
$workflow = WorkflowStub::make(MyWorkflow::class);
$workflow->start();
while ($workflow->running());
$workflow->output();
=> 'activity'
```

## Failed Workflows

If a workflow fails or crashes at any point then it can be resumed from that point. Any activities that were successfully completed during the previous execution of the workflow will not be ran again.

```php
$workflow = WorkflowStub::load(1);
$workflow->resume();
while ($workflow->running());
$workflow->output();
=> 'activity'
```

## Retries

A workflow will only fail when the retries on the workflow or a failing activity have been exhausted.

Workflows and activies are based on Laravel jobs so you can use any options you normally would.

```php
public $tries = 3;

public $maxExceptions = 3;
```
