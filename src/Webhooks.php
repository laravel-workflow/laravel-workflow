<?php

declare(strict_types=1);

namespace Workflow;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class Webhooks
{
    public static function routes($customNamespace = null, $customAppPath = null)
    {
        $folder = config('workflows.workflows_folder', 'Workflows');
        $namespace = $customNamespace ?? "App\\{$folder}";
        $basePath = rtrim(config('workflows.webhooks_route', 'webhooks'), '/');

        foreach (self::discoverWorkflows($namespace, $customAppPath) as $workflow) {
            self::registerWorkflowWebhooks($workflow, $basePath);
            self::registerSignalWebhooks($workflow, $basePath);
        }
    }

    private static function discoverWorkflows($namespace, $customAppPath = null)
    {
        $basePath = $customAppPath ?? app_path(Str::replace('\\', '/', $namespace));
        if (! is_dir($basePath)) {
            return []; // no coverage
        }

        $files = self::scanDirectory($basePath);

        $filter = array_filter(
            array_map(static fn ($file) => self::getClassFromFile($file, $namespace, $basePath), $files),
            static fn ($class) => is_subclass_of($class, \Workflow\Workflow::class)
        );

        return $filter;
    }

    private static function scanDirectory($directory)
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && Str::endsWith($file->getFilename(), '.php')) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private static function getClassFromFile($file, $namespace, $basePath)
    {
        $relativePath = Str::replaceFirst($basePath . '/', '', $file);
        $classPath = str_replace(['/', '.php'], ['\\', ''], $relativePath);

        return "{$namespace}\\{$classPath}";
    }

    private static function registerWorkflowWebhooks($workflow, $basePath)
    {
        $reflection = new ReflectionClass($workflow);

        if (! self::hasWebhookAttributeOnClass($reflection)) {
            return;
        }

        foreach ($reflection->getMethods() as $method) {
            if ($method->getName() === 'execute') {
                $slug = Str::kebab(class_basename($workflow));
                Route::post("{$basePath}/start/{$slug}", static function (Request $request) use ($workflow) {
                    if (! self::validateAuth($request)) { // no coverage
                        return response()->json([
                            // no coverage
                            'error' => 'Unauthorized', // no coverage
                        ], 401); // no coverage
                    } // no coverage

                    $params = self::resolveNamedParameters($workflow, 'execute', $request->all()); // no coverage
                    WorkflowStub::make($workflow)->start(...$params); // no coverage
                    return response()->json([
                        // no coverage
                        'message' => 'Workflow started', // no coverage
                    ]); // no coverage
                });
            }
        }
    }

    private static function registerSignalWebhooks($workflow, $basePath)
    {
        foreach (self::getSignalMethods($workflow) as $method) {
            if (self::hasWebhookAttributeOnMethod($method)) {
                $slug = Str::kebab(class_basename($workflow));
                $signal = Str::kebab($method->getName());

                Route::post(
                    "{$basePath}/signal/{$slug}/{workflowId}/{$signal}",
                    static function (Request $request, $workflowId) use ($workflow, $method) {
                        if (! self::validateAuth($request)) { // no coverage
                            return response()->json([
                                // no coverage
                                'error' => 'Unauthorized', // no coverage
                            ], 401); // no coverage
                        } // no coverage

                        $workflowInstance = WorkflowStub::load($workflowId); // no coverage
                        $params = self::resolveNamedParameters( // no coverage
                            $workflow, // no coverage
                            $method->getName(), // no coverage
                            $request->except('workflow_id') // no coverage
                        ); // no coverage
                        $workflowInstance->{$method->getName()}(...$params); // no coverage

                        return response()->json([
                            // no coverage
                            'message' => 'Signal sent', // no coverage
                        ]); // no coverage
                    }
                );
            }
        }
    }

    private static function getSignalMethods($workflow)
    {
        return array_filter(
            (new ReflectionClass($workflow))->getMethods(ReflectionMethod::IS_PUBLIC),
            static fn ($method) => count($method->getAttributes(\Workflow\SignalMethod::class)) > 0
        );
    }

    private static function hasWebhookAttributeOnClass(ReflectionClass $class): bool
    {
        return count($class->getAttributes(Webhook::class)) > 0;
    }

    private static function hasWebhookAttributeOnMethod(ReflectionMethod $method): bool
    {
        return count($method->getAttributes(Webhook::class)) > 0;
    }

    private static function resolveNamedParameters($class, $method, $payload)
    {
        $reflection = new ReflectionClass($class);
        $method = $reflection->getMethod($method);
        $params = [];

        foreach ($method->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $payload)) {
                $params[$name] = $payload[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $params[$name] = $param->getDefaultValue();
            }
        }

        return $params;
    }

    private static function validateAuth(Request $request): bool
    {
        $config = config('workflows.webhook_auth');

        if ($config['method'] === 'none') {
            return true;
        }

        if ($config['method'] === 'signature') {
            $secret = $config['signature']['secret'];
            $header = $config['signature']['header'];
            $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);
            return $request->header($header) === $expectedSignature;
        }

        if ($config['method'] === 'token') {
            $token = $config['token']['token'];
            $header = $config['token']['header'];
            return $request->header($header) === $token;
        }

        return false; // no coverage
    }
}
