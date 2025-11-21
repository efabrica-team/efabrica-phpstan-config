<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\PluginBridge;

use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use ReflectionClass;
use ReflectionException;

trait CommonPhpParserAnalysisTrait
{
    /**
     * @param class-string $selfClass
     */
    private function getClassConst(ClassConstFetch $node, string $selfClass): ?string
    {
        if (!$node->class instanceof Name || !$node->name instanceof Identifier) {
            return null;
        }
        /** @var class-string $className */
        $className = $node->class->__toString();
        $constantName = $node->name->name;
        if (in_array($node->class->__toString(), ['self', 'static'], true)) {
            $className = $selfClass;
        }
        try {
            $classReflection = new ReflectionClass($className);
            $constant = $classReflection->getConstant($constantName);
            if ($constant === false) {
                return null;
            }
            if (is_string($constant)) {
                return $constant;
            }
            return null;
        } catch (ReflectionException) {
            return null;
        }
    }

    private function findFrontendControlClassName(?Class_ $class): ?string
    {
        if ($class === null) {
            return null;
        }

        $property = $this->findFrontendControlStatement($class);

        if ($property === null) {
            return null;
        }

        if ($property->default instanceof ClassConstFetch) {
            if (!$property->default->class instanceof Name) {
                return null;
            }
            return $property->default->class->toString();
        } elseif ($property->default instanceof String_) {
            return $property->default->value;
        }

        return null;
    }

    private function findFrontendControlStatement(Class_ $class): ?PropertyProperty
    {
        foreach ($class->stmts as $stmt) {
            if (!$stmt instanceof Property) {
                continue;
            }

            if (count($stmt->props) !== 1) {
                continue;
            }

            $prop = $stmt->props[0];

            if (!$prop instanceof PropertyProperty) {
                continue;
            }

            $name = $prop->name->name;

            if ($name !== 'frontendControlClass') {
                continue;
            }

            return $prop;
        }

        return null;
    }
}