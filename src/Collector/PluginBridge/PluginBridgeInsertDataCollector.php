<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\PluginBridge;

use PHPStanConfig\Collector\PluginBridge\Data\ProvideData;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Reflection\Php\PhpMethodFromParserNodeReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ObjectType;

final class PluginBridgeInsertDataCollector implements Collector
{
    use CommonPhpParserAnalysisTrait;

    const NUM_ARGUMENTS = 2;

    /** @var class-string */
    private string $frontendPluginControlClass;

    /** @var class-string */
    private string $pluginBridgeInterface;

    /**
     * @param class-string $frontendPluginControlClass
     * @param class-string $pluginBridgeInterface
     */
    public function __construct(
        string $frontendPluginControlClass,
        string $pluginBridgeInterface,
    ) {
        $this->frontendPluginControlClass = $frontendPluginControlClass;
        $this->pluginBridgeInterface = $pluginBridgeInterface;
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
    public function processNode(Node $node, Scope $scope): ?ProvideData
    {
        if (!$node instanceof Node\Expr\MethodCall) {
            return null;
        }

        if (!$node->name instanceof Node\Identifier) {
            return null;
        }

        if ($node->name->name === 'register' || $node->name->name === 'add') {
            try {
                $nodeName = $node->name->name;
                $caller = $scope->getType($node->var);
                if (!$caller instanceof ObjectType) {
                    return null;
                }
                /** @var class-string $callerClass */
                $callerClass = $caller->getClassName();
                if ($callerClass !== $this->pluginBridgeInterface) {
                    $reflectionCallerClass = new \ReflectionClass($callerClass);
                    if (!$reflectionCallerClass->implementsInterface($this->pluginBridgeInterface)) {
                        return null;
                    }
                }

                $callerClassMethod = $scope->getFunction();

                if (!$callerClassMethod instanceof PhpMethodFromParserNodeReflection) {
                    return null;
                }
                $className = $callerClassMethod->getDeclaringClass()->getName();
                $reflectionClass = new \ReflectionClass($className);
                if (!$reflectionClass->isSubclassOf($this->frontendPluginControlClass)) {
                    return null;
                }
                $callerClassMethodName = $callerClassMethod->getName();
                if ($callerClassMethodName !== 'registerBridgeData') {
                    return null;
                }
                $file = $scope->getFile();
                if (count($node->getArgs()) !== self::NUM_ARGUMENTS) {
                    return null;
                }
                $resource = $node->getArgs()[0]->value;
                $resourceIsString = $resource instanceof Node\Scalar\String_;
                $resourceName = $resource instanceof Node\Scalar\String_ ? $resource->value : null;
                if ($resource instanceof Node\Expr\ClassConstFetch) {
                    $fetchedResource = $this->getClassConst($resource, $className);
                    $resourceIsString = $fetchedResource !== null;
                    $resourceName = $fetchedResource;
                }
                return new ProvideData(
                    $file,
                    $className,
                    $callerClassMethodName,
                    $nodeName,
                    $resourceName,
                    $resourceIsString,
                    $node->getLine(),
                );
            } catch (\ReflectionException|ShouldNotHappenException) {
                return null;
            }
        }

        return null;
    }
}