# Laravel Workflow [![PHP Composer](https://github.com/laravel-workflow/laravel-workflow/actions/workflows/php.yml/badge.svg)](https://github.com/laravel-workflow/laravel-workflow/actions/workflows/php.yml) [![Gitter](https://badges.gitter.im/laravel-workflow/community.svg)](https://gitter.im/laravel-workflow/community?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)

Durable workflow engine that allows users to write long running persistent distributed workflows (orchestrations) in PHP powered by [Laravel Queues](https://laravel.com/docs/9.x/queues).

## Installation

This library is installable via [Composer](https://getcomposer.org). You must also publish the migrations for the `workflows` table.

```bash
composer require laravel-workflow/laravel-workflow

php artisan vendor:publish --provider="Workflow\Providers\WorkflowServiceProvider" --tag="migrations"
```

## Requirements

You can use any queue driver that Laravel supports but this is heavily tested against Redis. Your cache driver must support locks. (Read: [Laravel Queues](https://laravel.com/docs/9.x/queues#unique-jobs))

## Usage

**1. Create a workflow.**
```php
use Workflow\ActivityStub;
use Workflow\Workflow;

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
use Workflow\Activity;

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
use Workflow\WorkflowStub;

$workflow = WorkflowStub::make(MyWorkflow::class);
$workflow->start();
while ($workflow->running());
$workflow->output();
=> 'activity'
```

## Signals

Using `WorkflowStub::await()` along with signal methods allows a workflow to wait for an external event.

```php
use Workflow\ActivityStub;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;


class MyWorkflow extends Workflow
{
    private bool $isReady = false;

    #[SignalMethod]
    public function ready()
    {
        $this->isReady = true;
    }

    public function execute()
    {
        $result = yield ActivityStub::make(MyActivity::class);

        yield WorkflowStub::await(fn () => $this->isReady);

        $otherResult = yield ActivityStub::make(MyOtherActivity::class);

        return $result . $otherResult;
    }
}
```

The workflow will reach the call to `WorkflowStub::await()` and then hibernate until some external code signals the workflow like this.

```php
$workflow->ready();
```

## Timers

Using `WorkflowStub::timer($seconds)` allows a workflow to wait for a fixed amount of time in seconds.

```php
use Workflow\ActivityStub;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class MyWorkflow extends Workflow
{
    public function execute()
    {
        $result = yield ActivityStub::make(MyActivity::class);

        yield WorkflowStub::timer(300);

        $otherResult = yield ActivityStub::make(MyOtherActivity::class);

        return $result . $otherResult;
    }
}
```

The workflow will reach the call to `WorkflowStub::timer()` and then hibernate for 5 minutes. After that time has passed, it will continue execution.

## Signal + Timer

In most cases you don't want to wait forever for a signal.

Instead, you want to wait for some amount of time and then give up. It's possible to combine a signal with a timer yourself to achieve this but a convenience method exists to do this for you, `WorkflowStub::awaitWithTimeout()`.

```php
use Workflow\WorkflowStub;

$result = yield WorkflowStub::awaitWithTimeout(300, fn () => $this->isReady);
```

This will wait like the previous signal example but it will timeout after 5 minutes. If a timeout occurs, the result will be `false`.

## Concurrent Activities

Rather than running each activity in order, it is possible to use `ActivityStub::all()` to wait for the completion of a group of activities and collect their results before continuing (fan-out/fan-in).

```php
use Workflow\ActivityStub;
use Workflow\Workflow;

class MyWorkflow extends Workflow
{
    public function execute()
    {
        $activity1 = ActivityStub::make(MyActivity::class);
        $activity2 = ActivityStub::make(MyActivity::class);
        $result = yield ActivityStub::all([$activity1, $activity2]);
        return $result;
    }
}
```

The difference is, instead of calling `yield` on each activity, we collect them into an array via `ActivityStub::all()` and `yield` that.

## Failed Workflows

If a workflow fails or crashes at any point then it can be resumed from that point. Any activities that were successfully completed during the previous execution of the workflow will not be run again.

```php
use Workflow\WorkflowStub;

$workflow = WorkflowStub::load(1);
$workflow->resume();
while ($workflow->running());
$workflow->output();
=> 'activity'
```

## Retries

A workflow will only fail when the retries on the failing activity have been exhausted.

The default activity retry policy is to retry activities forever with an exponential backoff that decays to 2 minutes. If your activity fails because of a transient error (something that fixes itself) then it will keep retrying and eventually recover automatically. However, if your activity fails because of a permanent error then you will have to fix it manually via a code deploy and restart your queue workers. The activity will then retry again using the new code and complete successfully.

Workflows and activities are based on [Laravel Queues](https://laravel.com/docs/9.x/queues) so you can use any options you normally would.

## Workflow Constraints

Workflows and activities have a key difference. Workflows cannot have any side effects other than running activities and they must be deterministic. The following list only applies to workflows, not activities.

- No IO.
- No mutable global variables.
- No non-deterministic functions like non-seeded `rand()` or `Str::uuid()`.
- No `Carbon::now()`, use `WorkflowStub::now()` to get the current time.
- No `sleep()`, use `yield WorkflowStub::timer()` to wait.

All of these types of operations should be done in activities.

## Activity Constraints

Activities have none of the above constraints. However, because activities are retryable they should still be idempotent. If your activity creates a charge for a customer then retrying it should not create a duplicate charge.

Many external APIs support passing an `Idempotency-Key`. See [Stripe](https://stripe.com/docs/api/idempotent_requests) for an example.

Many operations are naturally idempotent. If you encode a video twice, while it may be a waste of time, you still have the same video. If you delete the same file twice, the second deletion does nothing.

Some operations are not idempotent but duplication may be tolerable. If you are unsure if an email was actually sent, sending a duplicate email might be preferable to risking that no email was sent at all.

## Constraints Summary

| Workflows | Activities |
| ------------- | ------------- |
| ❌ IO | ✔️ IO |
| ❌ mutable global variables | ✔️ mutable global variables |
| ❌ non-deterministic functions | ✔️ non-deterministic functions |
| ❌ `Carbon::now()` | ✔️ `Carbon::now()` |
| ❌ `sleep()` | ✔️ `sleep()` |
| ❌ non-idempotent | ❌ non-idempotent |

## Monitoring

[Waterline](https://github.com/laravel-workflow/waterline) is a separate UI that works nicely alongside Horizon. Think of Waterline as being to workflows what Horizon is to queues.

### Dashboard View

![waterline_dashboard](https://user-images.githubusercontent.com/1130888/202866614-4adad485-60d1-403c-976f-d3063e928287.png)

### Workflow View

![workflow](https://user-images.githubusercontent.com/1130888/202866616-98a214d3-a916-4ae1-952e-ca8267ddf4a7.png)

Refer to https://github.com/laravel-workflow/waterline for installation and configuration instructions.

## Supporters

[![Stargazers repo roster for @laravel-workflow/laravel-workflow](https://reporoster.com/stars/dark/laravel-workflow/laravel-workflow)](https://github.com/laravel-workflow/laravel-workflow/stargazers)
