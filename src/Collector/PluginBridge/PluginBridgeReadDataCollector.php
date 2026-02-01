<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\PluginBridge;

use PHPStanConfig\Collector\PluginBridge\Data\ReadData;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Reflection\Php\PhpMethodFromParserNodeReflection;
use PHPStan\ShouldNotHappenException;
use PHPStan\Type\ThisType;

final class PluginBridgeReadDataCollector implements Collector
{
    use CommonPhpParserAnalysisTrait;

    /** @var class-string */
    private string $frontendPluginControlClass;

    /**
     * @param class-string $frontendPluginControlClass
     */
    public function __construct(
        string $frontendPluginControlClass,
    ) {
        $this->frontendPluginControlClass = $frontendPluginControlClass;
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
    public function processNode(Node $node, Scope $scope): ?ReadData
    {
        if (!$node instanceof Node\Expr\MethodCall) {
            return null;
        }

        if (!$node->name instanceof Node\Identifier) {
            return null;
        }

        $nodeName = $node->name->name;

        return match ($nodeName) {
            'getBridgeDataItem' => $this->processGetBridgeDataItem($node, $scope),
            'getBridgeData' => $this->processGetBridgeData($node, $scope),
            default => null,
        };
    }

    private function processGetBridgeDataItem(Node\Expr\MethodCall $node, Scope $scope): ?ReadData
    {
        try {
            if (!$node->name instanceof Node\Identifier) {
                return null;
            }
            $file = $scope->getFile();
            $caller = $scope->getType($node->var);
            if (!$caller instanceof ThisType) {
                return null;
            }
            $classMethod = $scope->getFunction();
            if (!$classMethod instanceof PhpMethodFromParserNodeReflection) {
                return null;
            }
            /** @var class-string $callerClass */
            $callerClass = $caller->getClassName();
            $reflectionCallerClass = $caller->getClassReflection();
            $callerClassIsFrontendPluginControlInstance = $reflectionCallerClass->isSubclassOf($this->frontendPluginControlClass);
            if (count($node->args) === 0 || !$node->args[0] instanceof Node\Arg) {
                return new ReadData(
                    $file,
                    $node->name->name,
                    $classMethod->getName(),
                    $callerClassIsFrontendPluginControlInstance ? $callerClass : null,
                    true,
                    false,
                    null,
                    $node->getLine(),
                );
            }
            $arg1 = $node->args[0]->value;
            $resourceIsString = $arg1 instanceof Node\Scalar\String_;
            $resourceName = $arg1 instanceof Node\Scalar\String_ ? $arg1->value : null;
            if ($arg1 instanceof Node\Expr\ClassConstFetch) {
                $fetchedResource = $this->getClassConst(
                    $arg1,
                    $callerClassIsFrontendPluginControlInstance
                        ? $callerClass
                        : $classMethod->getDeclaringClass()->getName(),
                );
                $resourceIsString = $fetchedResource !== null;
                $resourceName = $fetchedResource;
            }
            return new ReadData(
                $file,
                $node->name->name,
                $classMethod->getName(),
                $callerClassIsFrontendPluginControlInstance ? $callerClass : null,
                true,
                $resourceIsString,
                $resourceName,
                $node->getLine(),
            );
        } catch (ShouldNotHappenException) {
            return null;
        }
    }

    private function processGetBridgeData(Node\Expr\MethodCall $node, Scope $scope): ?ReadData
    {
        try {
            if (!$node->name instanceof Node\Identifier) {
                return null;
            }
            $file = $scope->getFile();
            $parentNode = $node->getAttributes()['parent'];
            $caller = $scope->getType($node->var);
            if (!$caller instanceof ThisType) {
                return null;
            }
            $classMethod = $scope->getFunction();
            if (!$classMethod instanceof PhpMethodFromParserNodeReflection) {
                return null;
            }
            if ($classMethod->getName() === 'getBridgeDataItem') {
                return null;
            }
            /** @var class-string $callerClass */
            $callerClass = $caller->getClassName();
            $reflectionCallerClass = $caller->getClassReflection();
            $callerClassIsFrontendPluginControlInstance = $reflectionCallerClass->isSubclassOf($this->frontendPluginControlClass);
            if ($parentNode instanceof Node\Expr\ArrayDimFetch) {
                $nextNode = $node->getAttributes()['next'];
                $resourceIsString = $nextNode instanceof Node\Scalar\String_;
                $resourceName = $nextNode instanceof Node\Scalar\String_ ? $nextNode->value : null;
                if ($nextNode instanceof Node\Expr\ClassConstFetch) {
                    $fetchedResource = $this->getClassConst(
                        $nextNode,
                        $callerClassIsFrontendPluginControlInstance
                            ? $callerClass
                            : $classMethod->getDeclaringClass()->getName(),
                    );
                    $resourceIsString = $fetchedResource !== null;
                    $resourceName = $fetchedResource;
                }
                return new ReadData(
                    $file,
                    $node->name->name,
                    $classMethod->getName(),
                    $callerClassIsFrontendPluginControlInstance ? $callerClass : null,
                    true,
                    $resourceIsString,
                    $resourceName,
                    $node->getLine(),
                );
            }
            return new ReadData(
                $file,
                $node->name->name,
                $classMethod->getName(),
                $callerClassIsFrontendPluginControlInstance ? $callerClass : null,
                false,
                false,
                null,
                $node->getLine(),
            );
        } catch (ShouldNotHappenException) {
            return null;
        }
    }
}