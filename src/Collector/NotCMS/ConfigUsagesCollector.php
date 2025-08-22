<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\NotCMS;

use Efabrica\Cms\Core\Plugin\BasePluginControl;
use Efabrica\PHPStanRules\Resolver\NameResolver;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Scalar\String_;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\ObjectType;

final class ConfigUsagesCollector implements Collector
{
    private NameResolver $nameResolver;

    public function __construct(NameResolver $nameResolver)
    {
        $this->nameResolver = $nameResolver;
    }

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope)
    {
        if (!$node instanceof MethodCall) {
            return null;
        }

        // TODO application config can be called from everywhere

        $callerType = $scope->getType($node->var);
        if (!(new ObjectType(BasePluginControl::class))->isSuperTypeOf($callerType)->yes()) {
            return null;
        }

        $methodName = $this->nameResolver->resolve($node->name);

        $methodName = strtolower($methodName);
        if (!str_contains($methodName, 'setting')) {
            return null;
        }

        $context = ConfigContext::PLUGIN;
        if (str_starts_with($methodName, 'page')) {
            $context = ConfigContext::PAGE;
        } elseif (str_starts_with($methodName, 'global')) {
            $context = ConfigContext::GLOBAL;
        }

        $configKeyArg = $node->args[0] ?? null;
        if (!$configKeyArg instanceof Arg) {
            return null;
        }

        if (!$configKeyArg->value instanceof String_) {
            return null;
        }

        $classNode = $this->findClassNode($node);
        if ($classNode === null) {
            return null;
        }

        $className = $this->nameResolver->resolve($classNode->namespacedName);
        return new ConfigUsage($context, $className, $configKeyArg->value->value, 'string'); // TODO type by method name
    }

    private function findClassNode(Node $node): ?Node\Stmt\Class_
    {
        do {
            $parent = $this->getParentNode($node);
            $node = $parent;
        } while (!$parent instanceof Node\Stmt\Class_ && $parent !== null);
        return $parent;
    }

    private function getParentNode(Node $node): ?Node
    {
        return $node->getAttribute('parent');
    }
}
