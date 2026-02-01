<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\TemplateAssignment;

use PHPStanConfig\Collector\TemplateAssignment\Data\TemplateAssignment;
use PHPStanConfig\Collector\TemplateAssignment\Data\TypeInterface;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\CallableType;
use PHPStan\Type\ClosureType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\IntersectionType;
use PHPStan\Type\IterableType;
use PHPStan\Type\MixedType;
use PHPStan\Type\NullType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\ResourceType;
use PHPStan\Type\StringType;
use PHPStan\Type\ThisType;
use PHPStan\Type\Type;
use PHPStan\Type\UnionType;

final class TemplateAssignedVariablesCollector implements Collector
{
    /** @var array<class-string> */
    private array $templateEngines = [];

    /**
     * @param array<class-string> $templateEngines
     */
    public function __construct(
        array $templateEngines,
    ) {
        $this->templateEngines = $templateEngines;
    }

    /**
     * @inheritDoc
     */
    public function getNodeType(): string
    {
        return Node\Expr\Assign::class;
    }

    /**
     * @inheritDoc
     */
    public function processNode(Node $node, Scope $scope): ?TemplateAssignment
    {
        if (!$node instanceof Node\Expr\Assign) {
            return null;
        }

        if (!$node->var instanceof Node\Expr\PropertyFetch) {
            return null;
        }

        $calledUpOnType = $scope->getType($node->var->var);
        if ($calledUpOnType instanceof ThisType) {
            $calledUpOnType = $calledUpOnType->getStaticObjectType();
        }

        if (!$calledUpOnType instanceof ObjectType) {
            return null;
        }

        if (!in_array($calledUpOnType->getClassName(), $this->templateEngines, true)) {
            return null;
        }

        if (!$node->var->name instanceof Node\Identifier) {
            return null;
        }

        $targetTemplateVariable = $node->var->name;
        $assignedExpression = $node->expr;

        $simpleType = $this->getSimplifiedType($scope->getType($assignedExpression));

        if ($simpleType === null) {
            return null;
        }

        $file = $scope->getFile();
        $line = $node->getLine();
        $code = $node->getAttributes()['phpstan_cache_printer'];

        $definingClass = $this->findDefiningClass($node);

        return new TemplateAssignment(
            $file,
            $line,
            $targetTemplateVariable->toString(),
            $code,
            $simpleType,
            $definingClass?->namespacedName?->toString(),
        );
    }

    private function findDefiningClass(Node $node): ?Node\Stmt\Class_
    {
        do {
            if ($node instanceof Node\Stmt\Class_) {
                return $node;
            }
            $node = $node->getAttributes()['parent'] ?? null;
        } while ($node !== null);

        return null;
    }

    private function getSimplifiedType(Type $type): TypeInterface|null
    {
        if ($type instanceof StringType) {
            return new Data\Types\StringType();
        }
        if ($type instanceof IntegerType) {
            return new Data\Types\IntegerType();
        }
        if ($type instanceof BooleanType) {
            return new Data\Types\BooleanType();
        }
        if ($type instanceof FloatType) {
            return new Data\Types\FloatType();
        }
        if ($type instanceof NullType) {
            return new Data\Types\NullType();
        }
        if ($type instanceof MixedType) {
            return new Data\Types\MixedType();
        }
        if ($type instanceof ObjectType) {
            return new Data\Types\ObjectType($type->getClassName());
        }
        if ($type instanceof UnionType) {
            $subTypesSimplified = [];
            foreach ($type->getTypes() as $subType) {
                $subTypesSimplified[] = $this->getSimplifiedType($subType);
            }
            return new Data\Types\UnionType(array_filter($subTypesSimplified));
        }
        if ($type instanceof IntersectionType) {
            $subTypesSimplified = [];
            foreach ($type->getTypes() as $subType) {
                $subTypesSimplified[] = $this->getSimplifiedType($subType);
            }
            return new Data\Types\IntersectionType(array_filter($subTypesSimplified));
        }
        if ($type instanceof ArrayType) {
            $itemType = $this->getSimplifiedType($type->getItemType());
            $keyType = $this->getSimplifiedType($type->getKeyType());
            if ($itemType === null || $keyType === null) {
                return null;
            }
            return new Data\Types\ArrayType($keyType, $itemType);
        }
        if ($type instanceof IterableType) {
            $itemType = $this->getSimplifiedType($type->getIterableValueType());
            $keyType = $this->getSimplifiedType($type->getIterableKeyType());
            if ($itemType === null || $keyType === null) {
                return null;
            }
            return new Data\Types\IterableType($keyType, $itemType);
        }
        if ($type instanceof ResourceType) {
            return new Data\Types\ResourceType();
        }
        if ($type instanceof ClosureType) {
            return new Data\Types\ClosureType();
        }
        if ($type instanceof CallableType) {
            return new Data\Types\CallableType();
        }
        return null;
    }
}