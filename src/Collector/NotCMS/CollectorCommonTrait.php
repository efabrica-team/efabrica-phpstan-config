<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\NotCMS;

use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionClass;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionException;
use UnitEnum;

trait CollectorCommonTrait
{
    /**
     * @param class-string|null $selfClass
     */
    private function getClassConstValue(ClassConstFetch $node, ?string $selfClass): ?string
    {
        if (!$node->class instanceof Name || !$node->name instanceof Identifier) {
            return null;
        }
        /** @var class-string $className */
        $className = $node->class->__toString();
        $constantName = $node->name->name;
        if (in_array($node->class->__toString(), ['self', 'static'], true)) {
            if ($selfClass === null) {
                return null;
            }
            $className = $selfClass;
        }
        try {
            $classReflection = new ReflectionClass($className);
            $constant = $classReflection->getConstant($constantName);
            if (!is_string($constant)) {
                return null;
            }
            return $constant;
        } catch (ReflectionException) {
            return null;
        }
    }

    private function getPropertyFetchValue(PropertyFetch $node): ?string
    {
        if ($node->var instanceof ClassConstFetch && $node->name instanceof Identifier
            && in_array($node->name->name, ['value', 'name'], true)) {
            if (!$node->var->class instanceof Name || !$node->var->name instanceof Identifier) {
                return null;
            }
            // THIS IS MOST PROBABLY THE ENUM value OR name FETCHING
            /** @var class-string<UnitEnum> $enumClass */
            $enumClass = $node->var->class->__toString();
            $enumCase = $node->var->name->name;
            $operation = $node->name->name;
            try {
                $enumClassReflection = new ReflectionEnum($enumClass);
                if (!$enumClassReflection->isBacked()) {
                    return null;
                }
                $caseReflection = $enumClassReflection->getCase($enumCase);
                if (!$caseReflection instanceof ReflectionEnumBackedCase) {
                    return null;
                }
                if ($operation === 'name') {
                    return $caseReflection->getName();
                }
                return (string)$caseReflection->getBackingValue();
            } catch (ReflectionException) {
                return null;
            }
        }
        return null;
    }
}
