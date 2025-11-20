<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\PluginBridge;

use PHPStanConfig\Collector\PluginBridge\Data\RegisterOrWillData;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Reflection\Php\PhpMethodFromParserNodeReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ObjectType;
use ReflectionClass;
use ReflectionException;

final class PluginBridgeRegistersReadsCollector implements Collector
{
    use CommonPhpParserAnalysisTrait;

    /** @var class-string */
    private string $bridgeBlueprintInterface;

    /** @var class-string */
    private string $pluginDefinitionInterface;

    /**
     * @param class-string $bridgeBlueprintInterface
     * @param class-string $pluginDefinitionInterface
     */
    public function __construct(
        string $bridgeBlueprintInterface,
        string $pluginDefinitionInterface,
    ) {
        $this->bridgeBlueprintInterface = $bridgeBlueprintInterface;
        $this->pluginDefinitionInterface = $pluginDefinitionInterface;
    }

    /**
     * @inheritDoc
     */
    public function getNodeType(): string
    {
        return Node\Expr\MethodCall::class;
    }

    /**
     * @inheritDoc
     */
    public function processNode(Node $node, Scope $scope): ?RegisterOrWillData
    {
        if (!$node instanceof Node\Expr\MethodCall) {
            return null;
        }

        if (!$node->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->name->name === 'setWill' || $node->name->name === 'setRequire') {
            try {
                $calledMethod = $node->name->name;
                $caller = $scope->getType($node->var);
                if (!$caller instanceof ObjectType) {
                    return null; // This call is not from object instance
                }
                /** @var class-string $callerClass */
                $callerClass = $caller->getClassName();

                if ($callerClass !== $this->bridgeBlueprintInterface) {
                    $reflectionClass = new ReflectionClass($callerClass);
                    if (!$reflectionClass->implementsInterface($this->bridgeBlueprintInterface)) {
                        return null; // This call is not from class implementing BridgeBlueprintInterface
                    }
                }

                $classMethod = $scope->getFunction();

                if (!$classMethod instanceof PhpMethodFromParserNodeReflection) {
                    return null; // This is not called from a method of class
                }

                $className = $classMethod->getDeclaringClass()->getName();
                $reflectionClass = new ReflectionClass($className);
                if (!$reflectionClass->implementsInterface($this->pluginDefinitionInterface)) {
                    return null; // This call is not from a plugin definition
                }

                $parentClassNode = $this->resolveClassStatement($node);
                $controlClassName = $this->findFrontendControlClassName($parentClassNode);

                // At this point we are sure that we have find what we looked for.

                $classMethodName = $classMethod->getName();
                if ($classMethodName !== 'bridgeBlueprint') {
                    return null; // This call is not a definition of resource
                }
                $methodReturnType = $scope->getFunction()?->getReturnType();
                if (!$methodReturnType instanceof ObjectType) {
                    return null; // This method is something else, not the one we are looking for
                }
                if ($methodReturnType->getClassName() !== $this->bridgeBlueprintInterface) {
                    return null; // This method does not return the proper type
                }
                $file = $scope->getFile();
                if (count($node->getArgs()) === 0) {
                    return null; // This call does not define any resource
                }
                $resource = $node->getArgs()[0]->value;
                $resourceIsString = $resource instanceof Node\Scalar\String_;
                $resourceName = $resource instanceof Node\Scalar\String_ ? $resource->value : null;
                if ($resource instanceof Node\Expr\ClassConstFetch) {
                    $fetchedResource = $this->getClassConst($resource, $className);
                    $resourceIsString = $fetchedResource !== null;
                    $resourceName = $fetchedResource;
                }

                return new RegisterOrWillData(
                    $file,
                    $className,
                    $classMethodName,
                    $calledMethod,
                    $resourceName,
                    $resourceIsString,
                    $node->getLine(),
                    $controlClassName,
                );
            } catch (ShouldNotHappenException|ReflectionException) {
                return null;
            }
        }

        return null;
    }

    private function resolveClassStatement(Node $node): ?Class_
    {
        $attributes = $node->getAttributes();
        if (!isset($attributes['parent'])) {
            return null;
        }
        $parent = $attributes['parent'];
        if ($parent instanceof Class_) {
            return $parent;
        }
        return $this->resolveClassStatement($parent);
    }
}