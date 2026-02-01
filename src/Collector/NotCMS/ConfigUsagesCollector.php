<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\NotCMS;

use Efabrica\PHPStanRules\Resolver\NameResolver;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ThisType;

/**
 * @implements Collector<MethodCall, ConfigUsage>
 */
final class ConfigUsagesCollector implements Collector
{
    use CollectorCommonTrait;

    private const PLUGIN_CONTROL_PATTERN = '/^(?P<context>(getactual)?page|global)?setting(?P<type>bool|string|int|array|date)$/i';
    private const APP_CONFIG_PATTERN = '/^get(?P<type>bool|string|int|array|date)?$/i';

    private NameResolver $nameResolver;

    /** @var class-string */
    private string $applicationConfigClass;

    /** @var class-string */
    private string $basePluginControlClass;

    /**
     * @param class-string $applicationConfigClass
     * @param class-string $basePluginControlClass
     */
    public function __construct(
        NameResolver $nameResolver,
        string $applicationConfigClass,
        string $basePluginControlClass,
    ) {
        $this->nameResolver = $nameResolver;
        $this->applicationConfigClass = $applicationConfigClass;
        $this->basePluginControlClass = $basePluginControlClass;
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): ?ConfigUsage
    {
        if (!$node instanceof MethodCall) {
            return null;
        }

        $callerType = $scope->getType($node->var);
        if ((new ObjectType($this->basePluginControlClass))->isSuperTypeOf($callerType)->yes()) {
            return $this->collectBasePluginControlData($node, $scope);
        } elseif ((new ObjectType($this->applicationConfigClass))->isSuperTypeOf($callerType)->yes()) {
            return $this->collectApplicationConfigData($node, $scope);
        }

        return null;
    }

    private function collectBasePluginControlData(MethodCall $node, Scope $scope): ?ConfigUsage
    {
        $callerType = $scope->getType($node->var);
        $callerTypeClass = null;
        if ($callerType instanceof ObjectType || $callerType instanceof ThisType) {
            $callerTypeClass = $callerType->getClassName();
        }

        $methodName = $this->nameResolver->resolve($node->name);

        if ($methodName === null) {
            return null;
        }

        $methodName = strtolower($methodName);
        if (!preg_match(self::PLUGIN_CONTROL_PATTERN, $methodName, $matches)) {
            return null;
        }

        $context = match ($matches['context']) {
            'global' => ConfigContext::GLOBAL,
            'page', 'getactualpage' => ConfigContext::PAGE,
            default => ConfigContext::PLUGIN,
        };
        $type = $matches['type'];

        $classNode = $this->findClassNode($node);
        if ($classNode === null) {
            return null;
        }

        /** @var null|class-string $className */
        $className = $this->nameResolver->resolve($classNode->namespacedName);

        $configKeyArg = $node->args[0] ?? null;
        if (!$configKeyArg instanceof Arg) {
            return null;
        }

        $name = null;
        if ($configKeyArg->value instanceof String_) {
            $name = $configKeyArg->value->value;
        } elseif ($configKeyArg->value instanceof ClassConstFetch) {
            $name = $this->getClassConstValue($configKeyArg->value, $className);
        } elseif ($configKeyArg->value instanceof PropertyFetch && $configKeyArg->value->var instanceof ClassConstFetch) {
            $name = $this->getPropertyFetchValue($configKeyArg->value);
        }

        if ($name === null) {
            return null;
        }

        $definingFunctionName = $scope->getFunctionName();

        $line = $node->getLine();
        $file = $scope->getFile();
        return new ConfigUsage(
            $context,
            $callerTypeClass,
            $className,
            $name,
            $type,
            $definingFunctionName,
            $line,
            $file,
        );
    }

    private function collectApplicationConfigData(MethodCall $node, Scope $scope): ?ConfigUsage
    {
        $methodName = $this->nameResolver->resolve($node->name);
        if ($methodName !== null) {
            $methodName = strtolower($methodName);
        }

        if ($methodName === null || !preg_match(self::APP_CONFIG_PATTERN, $methodName, $matches)) {
            return null;
        }
        $context = 'global';
        $type = $matches['type'] ?? null;

        $classNode = $this->findClassNode($node);
        if ($classNode === null) {
            return null;
        }

        $configKeyArg = $node->args[0] ?? null;
        if (!$configKeyArg instanceof Arg) {
            return null;
        }

        $name = null;
        if ($configKeyArg->value instanceof String_) {
            $name = $configKeyArg->value->value;
        } elseif ($configKeyArg->value instanceof ClassConstFetch) {
            /** @var null|class-string $classNodeClass */
            $classNodeClass = $classNode->namespacedName?->__toString();
            $name = $this->getClassConstValue($configKeyArg->value, $classNodeClass);
        } elseif ($configKeyArg->value instanceof PropertyFetch && $configKeyArg->value->var instanceof ClassConstFetch) {
            $name = $this->getPropertyFetchValue($configKeyArg->value);
        }

        if ($name === null) {
            return null;
        }

        $definingFunctionName = $scope->getFunctionName();

        $line = $node->getLine();
        $file = $scope->getFile();

        return new ConfigUsage(
            $context,
            null,
            $classNode->namespacedName?->toString(),
            $name,
            $type,
            $definingFunctionName,
            $line,
            $file,
        );
    }

    private function findClassNode(Node $node): ?Node\Stmt\Class_
    {
        do {
            $parent = $this->getParentNode($node);
            $node = $parent;
            if ($node === null) {
                return null;
            }
        } while (!$parent instanceof Node\Stmt\Class_);
        return $parent;
    }

    private function getParentNode(Node $node): ?Node
    {
        return $node->getAttribute('parent');
    }
}
