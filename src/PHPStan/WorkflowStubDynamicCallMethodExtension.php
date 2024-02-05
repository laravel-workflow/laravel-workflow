<?php

declare(strict_types=1);

namespace Workflow\PHPStan;

use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateObjectType;
use PHPStan\Type\Type;
use Workflow\QueryMethod;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class WorkflowStubDynamicCallMethodExtension implements MethodsClassReflectionExtension
{
    /**
     * @var array<string, MethodReflection>
     */
    private array $cache = [];

    private ReflectionProvider $reflectionProvider;

    public function __construct(ReflectionProvider $reflectionProvider)
    {
        $this->reflectionProvider = $reflectionProvider;
    }

    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        if (array_key_exists($classReflection->getCacheKey() . '-' . $methodName, $this->cache)) {
            return true;
        }

        $methodReflection = $this->findMethod($classReflection, $methodName);

        if ($methodReflection !== null) {
            $this->cache[$classReflection->getCacheKey() . '-' . $methodName] = $methodReflection;

            return true;
        }

        return false;
    }

    public function getMethod(
        ClassReflection $classReflection,
        string $methodName
    ): \PHPStan\Reflection\MethodReflection {
        return $this->cache[$classReflection->getCacheKey() . '-' . $methodName];
    }

    private function findMethod(ClassReflection $classReflection, string $methodName): ?MethodReflection
    {
        if ($classReflection->getName() !== WorkflowStub::class) {
            return null;
        }

        $workflowType = $classReflection->getActiveTemplateTypeMap()
            ->getType('TWorkflow');

        // Generic type is not specified
        if ($workflowType === null) {
            if (! $classReflection->isGeneric() && ($classReflection->getParentClass()?->isGeneric() ?? false)) {
                $workflowType = $classReflection->getParentClass()
                    ->getActiveTemplateTypeMap()
                    ->getType('TWorkflow');
            }
        }

        if ($workflowType === null) {
            return null;
        }

        if ($workflowType instanceof TemplateObjectType) {
            $workflowType = $workflowType->getBound();
        }

        if ($workflowType->getObjectClassReflections() !== []) {
            $workflowReflection = $workflowType->getObjectClassReflections()[0];
        } else {
            $workflowReflection = $this->reflectionProvider->getClass(Workflow::class);
        }

        if (! $workflowReflection->hasNativeMethod($methodName)) {
            return null;
        }

        if (! collect($workflowReflection->getNativeReflection()->getMethod($methodName)->getAttributes())
            ->contains(
                static fn ($attribute): bool => in_array($attribute->getName(), [
                    SignalMethod::class,
                    QueryMethod::class,
                ], true)
            )) {
            return null;
        }

        $methodReflection = $workflowReflection->getNativeMethod($methodName);

        return new class($classReflection, $methodName, $methodReflection) implements MethodReflection {
            /**
             * @var ClassReflection
             */
            private $classReflection;

            /**
             * @var string
             */
            private $methodName;

            /**
             * @var MethodReflection
             */
            private $methodReflection;

            public function __construct(
                ClassReflection $classReflection,
                string $methodName,
                MethodReflection $methodReflection
            ) {
                $this->classReflection = $classReflection;
                $this->methodName = $methodName;
                $this->methodReflection = $methodReflection;
            }

            public function getDeclaringClass(): ClassReflection
            {
                return $this->classReflection;
            }

            public function isStatic(): bool
            {
                return false;
            }

            public function isPrivate(): bool
            {
                return false;
            }

            public function isPublic(): bool
            {
                return true;
            }

            public function getDocComment(): ?string
            {
                return null;
            }

            public function getName(): string
            {
                return $this->methodName;
            }

            public function getPrototype(): ClassMemberReflection
            {
                return $this;
            }

            public function getVariants(): array
            {
                return $this->methodReflection->getVariants();
            }

            public function isDeprecated(): TrinaryLogic
            {
                return TrinaryLogic::createNo();
            }

            public function getDeprecatedDescription(): ?string
            {
                return null;
            }

            public function isFinal(): TrinaryLogic
            {
                return TrinaryLogic::createNo();
            }

            public function isInternal(): TrinaryLogic
            {
                return TrinaryLogic::createNo();
            }

            public function getThrowType(): ?Type
            {
                return null;
            }

            public function hasSideEffects(): TrinaryLogic
            {
                return TrinaryLogic::createYes();
            }
        };
    }
}
