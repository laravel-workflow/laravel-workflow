<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Mockery;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;
use Workflow\Webhook;
use Workflow\Webhooks;

final class WebhooksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Webhooks::routes('Tests\\Fixtures', __DIR__ . '/../Fixtures');
    }

    public function testScanDirectoryFindsPhpFiles()
    {
        $webhooksReflection = new ReflectionClass(Webhooks::class);
        $method = $webhooksReflection->getMethod('scanDirectory');
        $method->setAccessible(true);

        $files = $method->invoke(null, __DIR__ . '/../Fixtures');

        $this->assertIsArray($files);
        $this->assertTrue(count($files) > 0);
    }

    public function testGetClassFromFile()
    {
        $webhooksReflection = new ReflectionClass(Webhooks::class);
        $method = $webhooksReflection->getMethod('getClassFromFile');
        $method->setAccessible(true);

        $namespace = 'Tests\\Fixtures';
        $basePath = __DIR__ . '/../Fixtures';
        $filePath = __DIR__ . '/../Fixtures/TestWorkflow.php';

        $class = $method->invoke(null, $filePath, $namespace, $basePath);

        $this->assertEquals('Tests\\Fixtures\\TestWorkflow', $class);
    }

    public function testHasWebhookAttributeOnclassReturnsTrue()
    {
        $mockMethod = Mockery::mock(ReflectionClass::class);
        $mockMethod->shouldReceive('getAttributes')
            ->andReturn([new Webhook()]);

        $webhooksReflection = new ReflectionClass(Webhooks::class);
        $method = $webhooksReflection->getMethod('hasWebhookAttributeOnclass');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, $mockMethod));
    }

    public function testHasWebhookAttributeOnclassReturnsFalse()
    {
        $mockMethod = Mockery::mock(ReflectionClass::class);
        $mockMethod->shouldReceive('getAttributes')
            ->andReturn([]);

        $webhooksReflection = new ReflectionClass(Webhooks::class);
        $method = $webhooksReflection->getMethod('hasWebhookAttributeOnclass');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null, $mockMethod));
    }

    public function testHasWebhookAttributeOnMethodReturnsTrue()
    {
        $mockMethod = Mockery::mock(ReflectionMethod::class);
        $mockMethod->shouldReceive('getAttributes')
            ->andReturn([new Webhook()]);

        $webhooksReflection = new ReflectionClass(Webhooks::class);
        $method = $webhooksReflection->getMethod('hasWebhookAttributeOnMethod');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, $mockMethod));
    }

    public function testHasWebhookAttributeOnMethodReturnsFalse()
    {
        $mockMethod = Mockery::mock(ReflectionMethod::class);
        $mockMethod->shouldReceive('getAttributes')
            ->andReturn([]);

        $webhooksReflection = new ReflectionClass(Webhooks::class);
        $method = $webhooksReflection->getMethod('hasWebhookAttributeOnMethod');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null, $mockMethod));
    }

    public function testValidatesAuthForNone()
    {
        config([
            'workflows.webhook_auth.method' => 'none',
        ]);

        $request = Request::create('/webhooks/start/test-webhook-workflow', 'POST');

        $webhooksReflection = new ReflectionClass(Webhooks::class);
        $method = $webhooksReflection->getMethod('validateAuth');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, $request));
    }

    public function testValidatesAuthForTokenSuccess()
    {
        config([
            'workflows.webhook_auth.method' => 'token',
            'workflows.webhook_auth.token.token' => 'valid-token',
            'workflows.webhook_auth.token.header' => 'Authorization',
        ]);

        $request = Request::create('/webhooks/start/test-webhook-workflow', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'valid-token',
        ]);

        $webhooksReflection = new ReflectionClass(Webhooks::class);
        $method = $webhooksReflection->getMethod('validateAuth');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, $request));
    }

    public function testValidatesAuthForTokenFailure()
    {
        config([
            'workflows.webhook_auth.method' => 'token',
            'workflows.webhook_auth.token.token' => 'valid-token',
            'workflows.webhook_auth.token.header' => 'Authorization',
        ]);

        $request = Request::create('/webhooks/start/test-webhook-workflow', 'POST', [], [], [], [
            'HTTP_AUTHORIZATION' => 'invalid-token',
        ]);

        $webhooksReflection = new ReflectionClass(Webhooks::class);
        $method = $webhooksReflection->getMethod('validateAuth');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null, $request));
    }

    public function testValidatesAuthForSignatureSuccess()
    {
        config([
            'workflows.webhook_auth.method' => 'signature',
            'workflows.webhook_auth.signature.secret' => 'test-secret',
            'workflows.webhook_auth.signature.header' => 'X-Signature',
        ]);

        $payload = json_encode([
            'data' => 'test',
        ]);
        $signature = hash_hmac('sha256', $payload, 'test-secret');

        $request = Request::create('/webhooks/start/test-webhook-workflow', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE' => $signature,
        ], $payload);

        $webhooksReflection = new ReflectionClass(Webhooks::class);
        $method = $webhooksReflection->getMethod('validateAuth');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, $request));
    }

    public function testValidatesAuthForSignatureFailure()
    {
        config([
            'workflows.webhook_auth.method' => 'signature',
            'workflows.webhook_auth.signature.secret' => 'test-secret',
            'workflows.webhook_auth.signature.header' => 'X-Signature',
        ]);

        $payload = json_encode([
            'data' => 'test',
        ]);
        $invalidSignature = 'invalid-signature';

        $request = Request::create('/webhooks/start/test-webhook-workflow', 'POST', [], [], [], [
            'HTTP_X_SIGNATURE' => $invalidSignature,
        ], $payload);

        $webhooksReflection = new ReflectionClass(Webhooks::class);
        $method = $webhooksReflection->getMethod('validateAuth');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null, $request));
    }

    public function testResolveNamedParameters()
    {
        $payload = [
            'param1' => 'value1',
            'param2' => 'value2',
        ];

        $webhooksReflection = new ReflectionClass(Webhooks::class);
        $method = $webhooksReflection->getMethod('resolveNamedParameters');
        $method->setAccessible(true);

        $params = $method->invoke(null, TestClass::class, 'testMethod', $payload);

        $this->assertSame([
            'param1' => 'value1',
            'param2' => 'value2',
        ], $params);
    }

    public function testUnauthorizedRequestFails()
    {
        config([
            'workflows.webhook_auth.method' => 'unsupported',
        ]);

        $request = Request::create('/webhooks/start/test-webhook-workflow', 'POST');

        $webhooksReflection = new ReflectionClass(Webhooks::class);
        $method = $webhooksReflection->getMethod('validateAuth');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null, $request));
    }

    public function testResolveNamedParametersUsesDefaults()
    {
        $payload = [
            'param1' => 'value1',
        ];

        $webhooksReflection = new ReflectionClass(Webhooks::class);
        $method = $webhooksReflection->getMethod('resolveNamedParameters');
        $method->setAccessible(true);

        $params = $method->invoke(null, TestClass::class, 'testMethodWithDefault', $payload);

        $this->assertSame([
            'param1' => 'value1',
            'param2' => 'default_value',
        ], $params);
    }

    public function testWebhookRegistration()
    {
        Route::shouldReceive('post')
            ->once()
            ->withArgs(static function ($uri, $callback) {
                return str_contains($uri, 'webhooks/start/test-webhook-workflow');
            });

        Route::shouldReceive('post')
            ->once()
            ->withArgs(static function ($uri, $callback) {
                return str_contains($uri, 'webhooks/signal/test-webhook-workflow/{workflowId}/cancel');
            });

        Webhooks::routes('Tests\\Fixtures', __DIR__ . '/../Fixtures');
    }
}

class TestClass
{
    public function testMethod($param1, $param2)
    {
    }

    public function testMethodWithDefault($param1, $param2 = 'default_value')
    {
    }
}
