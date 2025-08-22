<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\NotCMS;

use Efabrica\Cms\Core\Plugin\Config\Factory\ConfigItemFactoryStorage;
use Efabrica\Cms\Core\Plugin\Config\PluginConfigInterface;
use Efabrica\Cms\Core\Plugin\PluginDefinitionInterface;
use Efabrica\PHPStanRules\Resolver\NameResolver;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\ObjectType;

final class ConfigsCollector implements Collector
{
    private NameResolver $nameResolver;

    public function __construct(NameResolver $nameResolver)
    {
        $this->nameResolver = $nameResolver;
    }

    public function getNodeType(): string
    {
        return Return_::class;
    }

    public function processNode(Node $node, Scope $scope)
    {
        if (!$node instanceof Return_) {
            return null;
        }

        if (!$node->getAttribute('parent')) {
            return null;
        }

        $classMethod = $node->getAttribute('parent');
        if (!$classMethod instanceof ClassMethod) {
            return null;
        }

        $class = $classMethod->getAttribute('parent');
        if (!$class instanceof Class_) {
            return null;
        }

        $className = $this->nameResolver->resolve($class->namespacedName);

        if (!$className) {
            return null;
        }

        if (!(new ObjectType(PluginDefinitionInterface::class))->isSuperTypeOf(new ObjectType($className))->yes()) {
            return null;
        }

        $returnArray = $node->expr;
        if (!$returnArray instanceof Array_) {
            return null;
        }

        $pluginControlName = $this->getPluginControlName($class);

        if (in_array($this->nameResolver->resolve($classMethod->name), ['configuration', 'buildConfiguration'], true)) {
            return $this->processConfig($returnArray, $scope, ConfigContext::PLUGIN, $className, $pluginControlName) ?: null;
        }

        if ($this->nameResolver->resolve($classMethod->name) === 'globalConfiguration') {
            return $this->processConfig($returnArray, $scope, ConfigContext::GLOBAL, $className, $pluginControlName) ?: null;
        }

        if ($this->nameResolver->resolve($classMethod->name) === 'pageConfiguration') {
            return $this->processConfig($returnArray, $scope, ConfigContext::PAGE, $className, $pluginControlName) ?: null;
        }

        return null;
    }

    private function getPluginControlName(Class_ $class): ?string
    {
        $frontendControlClassProperty = $class->getProperty('frontendControlClass');
        if (!$frontendControlClassProperty instanceof Property) {
            return null;
        }

        foreach ($frontendControlClassProperty->props as $prop) {
            if ($prop->default instanceof ClassConstFetch) {
                $frontendControlClassName = $this->nameResolver->resolve($prop->default->class);
                if ($frontendControlClassName !== null) {
                    return $frontendControlClassName;
                }
            } elseif ($prop->default instanceof String_) {
                return $prop->default->value;
            }
        }

        return null;
    }

    /**
     * @return Config[]
     */
    private function processConfig(Array_ $config, Scope $scope, string $context, string $definitionClass, ?string $pluginControlName): array
    {
        $configs = [];
        foreach ($config->items as $item) {
            if ($item->value instanceof Array_) {
                $configs = array_merge($configs, $this->processConfig($item->value, $scope, $context, $definitionClass, $pluginControlName));
                continue;
            }

            if ($item->value instanceof MethodCall) {
                $configs[] = $this->getConfigFromMethodCall($item->value, $scope, $context, $definitionClass, $pluginControlName);
            }

            if ($item->value instanceof New_) {
                $configs[] = $this->getConfigFromNew($item->value, $scope, $context, $definitionClass, $pluginControlName);
            }
        }

        return array_filter($configs);
    }

    private function getConfigFromMethodCall(MethodCall $methodCall, Scope $scope, string $context, string $definitionClass, ?string $pluginControlName): ?Config
    {
//        var_dump('create from method');

        $callerType = $scope->getType($methodCall->var);
        if ($callerType->equals(new ObjectType(ConfigItemFactoryStorage::class))) {
            // TODO resolve type based on class / method name - add config to constructor? default and fallback will be string
            $type = 'string';
            return $this->processArgs($methodCall->args, $scope, $context, $definitionClass, $type, $methodCall->getLine(), $pluginControlName);
        }

//        var_dump(get_class($methodCall->var));

        if ($methodCall->var instanceof MethodCall) {
            return $this->getConfigFromMethodCall($methodCall->var, $scope, $context, $definitionClass, $pluginControlName);
        }

        if ($methodCall->var instanceof New_) {
            return $this->getConfigFromNew($methodCall->var, $scope, $context, $definitionClass, $pluginControlName);
        }

//        exit;
        return null;
    }

    private function getConfigFromNew(New_ $new, Scope $scope, string $context, string $definitionClass, ?string $pluginControlName): ?Config
    {
//        var_dump('create from new');

//        var_dump($this->nameResolver->resolve($new->class));

        if (!(new ObjectType(PluginConfigInterface::class))->isSuperTypeOf($scope->getType($new))->yes()) {
            return null;
        }

        // TODO resolve type based on class / method name - add config to constructor? default and fallback will be string
        $type = 'string';
        return $this->processArgs($new->args, $scope, $context, $definitionClass, $type, $new->getLine(), $pluginControlName);
    }

    private function processArgs(array $args, Scope $scope, string $context, string $definitionClass, string $type, int $line, ?string $pluginControlName): ?Config
    {

        $configKeyArg = $args[0] ?? null;
        if (!$configKeyArg instanceof Arg) {
            return null;
        }

        if (!$configKeyArg->value instanceof String_) {
            return null;
        }

        return new Config($context, $definitionClass, $configKeyArg->value->value, $type, $scope->getFile(), $line, $pluginControlName);
    }
}
