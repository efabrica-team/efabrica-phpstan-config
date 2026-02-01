<?php declare(strict_types=1);

namespace PHPStanConfig\Rule\Hermes;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PHPStan\Rules\Rule;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\Type\ObjectType;
use PHPStan\Type\TypeCombinator;
use Tomaj\Hermes\Handler\HandlerInterface;

final class EnforceEnsureInHermesHandlerRule implements Rule
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof MethodCall) {
            return [];
        }

        // Only apply in Hermes handlers
        $classReflection = $scope->getClassReflection();
        if ($classReflection === null || !$classReflection->implementsInterface(HandlerInterface::class)) {
            return [];
        }

        // We care about calls on $this->*Repository
        if (!$node->var instanceof PropertyFetch) {
            return [];
        }
        $propertyFetch = $node->var;

        // Ensure it's $this->...
        if (!($propertyFetch->var instanceof Variable) || $propertyFetch->var->name !== 'this') {
            return [];
        }

        $propertyType = TypeCombinator::removeNull($scope->getType($propertyFetch));
        $propertyClass = $propertyType instanceof ObjectType ? $propertyType->getClassName() : null;
        if ($propertyClass === null || !is_subclass_of($propertyClass, 'NetteDatabaseRepository\Repository\BaseRepository')) {
            return [];
        }

        // Method name being called on the repository
        $methodName = $node->name instanceof Identifier ? $node->name->toString() : (string) $node->name;

        // Allow the ensure() call itself
        if (strcasecmp($methodName, 'ensure') === 0) {
            return [];
        }

        // Allow benign setters on repositories (common configuration calls)
        if (preg_match('~^set[A-Z_]~', $methodName) === 1) {
            return [];
        }

        // If this call is already inside any ensure(...) wrapper, it's OK
        if ($this->isInsideEnsure($node)) {
            return [];
        }

        // Property name
        $propName = $propertyFetch->name instanceof Identifier ? $propertyFetch->name->toString() : (string) $propertyFetch->name;

        return [
            RuleErrorBuilder::message(sprintf(
                "Repository call '%s' on '%s' must be wrapped in ensure() in Hermes handlers.",
                $methodName,
                '$this->' . $propName
            ))->build(),
        ];
    }

    private function isInsideEnsure(Node $node): bool
    {
        $current = $node;
        while ($current !== null) {
            $parent = $current->getAttribute('parent');
            if (!$parent instanceof Node) {
                break;
            }

            if ($parent instanceof MethodCall) {
                $parentName = $parent->name instanceof Identifier ? $parent->name->toString() : (string) $parent->name;
                if (strcasecmp($parentName, 'ensure') === 0) {
                    return true;
                }
            }

            $current = $parent;
        }

        return false;
    }
}
