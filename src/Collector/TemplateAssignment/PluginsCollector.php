<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\TemplateAssignment;

use PHPStanConfig\Collector\TemplateAssignment\Data\PluginClass;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use ReflectionClass;
use ReflectionException;

final class PluginsCollector implements Collector
{
    /** @var class-string */
    private string $basePluginDefinitionClass;

    /** @var class-string */
    private string $pluginMapperInterface;

    /**
     * @param class-string $basePluginDefinitionClass
     * @param class-string $pluginMapperInterface
     */
    public function __construct(
        string $basePluginDefinitionClass,
        string $pluginMapperInterface
    ) {
        $this->basePluginDefinitionClass = $basePluginDefinitionClass;
        $this->pluginMapperInterface = $pluginMapperInterface;
    }

    /**
     * @inheritDoc
     */
    public function getNodeType(): string
    {
        return Node\Stmt\Class_::class;
    }

    /**
     * @inheritDoc
     * @throws ReflectionException
     */
    public function processNode(Node $node, Scope $scope): ?PluginClass
    {
        if (!$node instanceof Node\Stmt\Class_) {
            return null;
        }

        $className = $node->namespacedName?->toString();
        if ($className === null || !class_exists($className)) {
            return null;
        }
        $classReflection = new ReflectionClass($className);
        if ($classReflection->isSubclassOf($this->basePluginDefinitionClass)) {
            return $this->parsePlugin($node, $scope);
        } elseif ($classReflection->isSubclassOf($this->pluginMapperInterface)) {
            return $this->parseMapper($node, $scope);
        }

        return null;
    }

    /**
     * @throws ReflectionException
     */
    private function parseMapper(Node\Stmt\Class_ $node, Scope $scope): ?PluginClass
    {
        $className = $node->namespacedName?->toString();
        $identifier = $this->getPluginIdentifierFromMapper($node);

        if ($identifier === null || $className === null) {
            return null;
        }

        return new PluginClass(
            null,
            null,
            $identifier,
            $className,
        );
    }

    /**
     * @throws ReflectionException
     */
    private function parsePlugin(Node\Stmt\Class_ $node, Scope $scope): ?PluginClass
    {
        $className = $node->namespacedName?->toString();
        $frontendControl = $this->findPropertyDefaultValue($node);
        $identifier = $this->findPropertyDefaultValue($node, 'identifier');

        if ($frontendControl === null || $identifier === null || $className === null) {
            return null;
        }

        return new PluginClass(
            $className,
            $frontendControl,
            $identifier,
            null,
        );
    }

    /**
     * @throws ReflectionException
     */
    private function findPropertyDefaultValue(Node\Stmt\Class_ $node, string $propertyName = 'frontendControlClass'): ?string
    {
        $defaultValue = null;

        foreach ($node->stmts as $statement) {
            if (!$statement instanceof Node\Stmt\Property) {
                continue;
            }
            if (\count($statement->props) !== 1) {
                continue;
            }
            if ($statement->props[0] instanceof Node\Stmt\PropertyProperty && $statement->props[0]->name->name === $propertyName) {
                $defaultValue = $statement->props[0]->default;
                break;
            }
        }

        if ($defaultValue === null) {
            return null;
        }

        return $this->getStringValueOf($defaultValue, $node);
    }

    /**
     * @throws ReflectionException
     */
    private function getStringValueOf(Node $defaultValue, Node\Stmt\Class_ $node): ?string
    {
        if ($defaultValue instanceof Node\Scalar\String_) {
            return $defaultValue->value;
        } elseif ($defaultValue instanceof Node\Expr\ClassConstFetch) {
            if (!$defaultValue->class instanceof Node\Name || !$defaultValue->name instanceof Node\Identifier) {
                return null;
            }
            if (in_array($defaultValue->class->toString(), ['self', 'static'], true)) {
                if (!$node->namespacedName instanceof Node\Name) {
                    return null;
                }
                /** @var class-string $class */
                $class = $node->namespacedName->toString();
            } else {
                /** @var class-string $class */
                $class = $defaultValue->class->toString();
            }
            if ($defaultValue->name->toString() === 'class') {
                return $class;
            }
            $reflectionClass = new ReflectionClass($class);
            $reflectionConstant = $reflectionClass->getConstant($defaultValue->name->toString());
            if ($reflectionConstant === false) {
                return null;
            }
            if (is_string($reflectionConstant)) {
                return $reflectionConstant;
            }
            return null;
        }
        return null;
    }

    /**
     * @throws ReflectionException
     */
    private function getPluginIdentifierFromMapper(Node\Stmt\Class_ $node): ?string
    {
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod && $stmt->name->name === 'pluginIdentifier') {
                $methodStatements = $stmt->stmts;
                foreach ($methodStatements ?? [] as $methodStatement) {
                    if ($methodStatement instanceof Node\Stmt\Return_) {
                        if ($methodStatement->expr === null) {
                            continue;
                        }
                        return $this->getStringValueOf($methodStatement->expr, $node);
                    }
                }
            }
        }

        return null;
    }
}