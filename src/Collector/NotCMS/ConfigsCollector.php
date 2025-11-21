<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\NotCMS;

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
use ReflectionClass;
use ReflectionException;

/**
 * @implements Collector<Return_, Config[]>
 */
final class ConfigsCollector implements Collector
{
    use CollectorCommonTrait;

    private NameResolver $nameResolver;

    /** @var class-string */
    private string $pluginDefinitionInterface;

    /** @var class-string */
    private string $pluginConfigInterface;

    /** @var class-string */
    private string $configItemFactoryStorage;

    /**
     * @param class-string $pluginDefinitionInterface
     * @param class-string $pluginConfigInterface
     * @param class-string $configItemFactoryStorage
     */
    public function __construct(
        NameResolver $nameResolver,
        string $pluginDefinitionInterface,
        string $pluginConfigInterface,
        string $configItemFactoryStorage,
    ) {
        $this->nameResolver = $nameResolver;
        $this->pluginDefinitionInterface = $pluginDefinitionInterface;
        $this->pluginConfigInterface = $pluginConfigInterface;
        $this->configItemFactoryStorage = $configItemFactoryStorage;
    }

    public function getNodeType(): string
    {
        return Return_::class;
    }

    /**
     * @inheritDoc
     * @return Config[]|null
     */
    public function processNode(Node $node, Scope $scope): ?array
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

        /** @var class-string|null $className */
        $className = $this->nameResolver->resolve($class->namespacedName);

        if (!$className) {
            return null;
        }

        if (!(new ObjectType($this->pluginDefinitionInterface))->isSuperTypeOf(new ObjectType($className))->yes()) {
            return null;
        }

        $returnArray = $node->expr;
        if (!$returnArray instanceof Array_) {
            return null;
        }

        $pluginControlName = $this->getPluginControlName($class);
        $backendPluginControlName = $this->getPluginControlName($class, false);

        $classMethodName = $this->nameResolver->resolve($classMethod->name);
        if (in_array($classMethodName, ['configuration', 'buildConfiguration'], true)) {
            return $this->processConfig(
                $returnArray,
                $scope,
                ConfigContext::PLUGIN,
                $className,
                $pluginControlName,
                $backendPluginControlName,
            ) ?: null;
        } elseif ($classMethodName === 'globalConfiguration') {
            return $this->processConfig(
                $returnArray,
                $scope,
                ConfigContext::GLOBAL,
                $className,
                $pluginControlName,
                $backendPluginControlName,
            ) ?: null;
        } elseif ($classMethodName === 'pageConfiguration') {
            return $this->processConfig(
                $returnArray,
                $scope,
                ConfigContext::PAGE,
                $className,
                $pluginControlName,
                $backendPluginControlName,
            ) ?: null;
        }

        return null;
    }

    private function getPluginControlName(Class_ $class, bool $frontend = true): ?string
    {
        $controlClassProperty = $class->getProperty(
            $frontend ? 'frontendControlClass' : 'backendControlClass',
        );
        if (!$controlClassProperty instanceof Property) {
            return null;
        }

        foreach ($controlClassProperty->props as $prop) {
            if ($prop->default instanceof ClassConstFetch) {
                $controlClassName = $this->nameResolver->resolve($prop->default->class);
                if ($controlClassName !== null) {
                    return $controlClassName;
                }
            } elseif ($prop->default instanceof String_) {
                return $prop->default->value;
            }
        }

        return null;
    }

    /**
     * @param ConfigContext::* $context
     * @param class-string $definitionClass
     * @return Config[]
     */
    private function processConfig(
        Array_ $config,
        Scope $scope,
        string $context,
        string $definitionClass,
        ?string $pluginControlName,
        ?string $backendPluginControlName = null,
    ): array {
        $configs = [];
        foreach ($config->items as $item) {
            if ($item?->value instanceof Array_) {
                $configs = $configs + $this->processConfig(
                    $item->value,
                    $scope,
                    $context,
                    $definitionClass,
                    $pluginControlName,
                    $backendPluginControlName,
                );
                continue;
            }

            if ($item?->value instanceof MethodCall) {
                $configs[] = $this->getConfigFromMethodCall(
                    $item->value,
                    $scope,
                    $context,
                    $definitionClass,
                    $pluginControlName,
                    $backendPluginControlName,
                );
            }

            if ($item?->value instanceof New_) {
                $configs[] = $this->getConfigFromNew(
                    $item->value,
                    $scope,
                    $context,
                    $definitionClass,
                    $pluginControlName,
                    $backendPluginControlName,
                );
            }
        }

        return array_filter($configs);
    }

    /**
     * @param ConfigContext::* $context
     * @param class-string $definitionClass
     */
    private function getConfigFromMethodCall(
        MethodCall $methodCall,
        Scope $scope,
        string $context,
        string $definitionClass,
        ?string $pluginControlName,
        ?string $backendPluginControlName = null,
    ): ?Config {
        $callerType = $scope->getType($methodCall->var);
        if ($callerType->equals(new ObjectType($this->configItemFactoryStorage))) {
            if (!$methodCall->name instanceof Node\Identifier) {
                return null;
            }
            $methodReflection = $scope->getMethodReflection($callerType, $methodCall->name->name);
            $methodVariants = $methodReflection?->getVariants() ?? [];
            if ($methodVariants === []) {
                return null;
            }
            $returnType = $methodVariants[0]->getReturnType();
            if (!$returnType instanceof ObjectType) {
                return null;
            }
            /** @var class-string $resolvedReturnType */
            $resolvedReturnType = $returnType->getClassName();
            $returnTypeReflection = new ReflectionClass($resolvedReturnType);
            if (!$returnTypeReflection->isSubclassOf($this->pluginConfigInterface)) {
                return null;
            }
            return $this->processArgs(
                $methodCall->args,
                $scope,
                $context,
                $definitionClass,
                $resolvedReturnType,
                $methodCall->getLine(),
                $pluginControlName,
                $backendPluginControlName,
            );
        }

        if ($methodCall->var instanceof MethodCall) {
            return $this->getConfigFromMethodCall(
                $methodCall->var,
                $scope,
                $context,
                $definitionClass,
                $pluginControlName,
                $backendPluginControlName,
            );
        }

        if ($methodCall->var instanceof New_) {
            return $this->getConfigFromNew(
                $methodCall->var,
                $scope,
                $context,
                $definitionClass,
                $pluginControlName,
                $backendPluginControlName,
            );
        }

        return null;
    }

    /**
     * @param ConfigContext::* $context
     * @param class-string $definitionClass
     */
    private function getConfigFromNew(
        New_ $new,
        Scope $scope,
        string $context,
        string $definitionClass,
        ?string $pluginControlName,
        ?string $backendPluginControlName = null,
    ): ?Config {
        $newType = $scope->getType($new);

        if (!$newType instanceof ObjectType) {
            return null;
        }

        if (!(new ObjectType($this->pluginConfigInterface))->isSuperTypeOf($newType)->yes()) {
            return null;
        }

        return $this->processArgs(
            $new->args,
            $scope,
            $context,
            $definitionClass,
            $newType->getClassName(),
            $new->getLine(),
            $pluginControlName,
            $backendPluginControlName,
        );
    }

    /**
     * @param Arg[]|Node\VariadicPlaceholder[] $args
     * @param ConfigContext::* $context
     * @param class-string $definitionClass
     */
    private function processArgs(
        array $args,
        Scope $scope,
        string $context,
        string $definitionClass,
        string $type,
        int $line,
        ?string $pluginControlName,
        ?string $backendPluginControlName = null,
    ): ?Config {
        $configKeyArg = $args[0] ?? null;
        if (!$configKeyArg instanceof Arg) {
            return null;
        }

        $configKey = $configKeyArg->value instanceof String_ ? $configKeyArg->value->value : null;
        if ($configKey === null && $configKeyArg->value instanceof ClassConstFetch) {
            $configKey = $this->getClassConstValue($configKeyArg->value, $definitionClass);
        } elseif ($configKey === null && $configKeyArg->value instanceof Node\Expr\PropertyFetch) {
            $configKey = $this->getPropertyFetchValue($configKeyArg->value);
        }

        if ($configKey === null) {
            return null;
        }

        return new Config(
            $context,
            $definitionClass,
            $configKey,
            $type,
            $scope->getFile(),
            $line,
            $pluginControlName,
            $backendPluginControlName,
        );
    }
}
