<?php

declare(strict_types=1);

namespace PHPStanConfig\Rule\TemplateAssignment;

use PHPStanConfig\Collector\TemplateAssignment\Data\Iterator\PluginClassIterator;
use PHPStanConfig\Collector\TemplateAssignment\Data\Iterator\TemplateAssignmentIterator;
use PHPStanConfig\Collector\TemplateAssignment\Data\TemplateAssignment;
use PHPStanConfig\Collector\TemplateAssignment\Data\TypeInterface;
use PHPStanConfig\Collector\TemplateAssignment\Data\Types\ArrayType;
use PHPStanConfig\Collector\TemplateAssignment\Data\Types\CallableType;
use PHPStanConfig\Collector\TemplateAssignment\Data\Types\ClosureType;
use PHPStanConfig\Collector\TemplateAssignment\Data\Types\IntersectionType;
use PHPStanConfig\Collector\TemplateAssignment\Data\Types\IterableType;
use PHPStanConfig\Collector\TemplateAssignment\Data\Types\MixedType;
use PHPStanConfig\Collector\TemplateAssignment\Data\Types\ObjectType;
use PHPStanConfig\Collector\TemplateAssignment\Data\Types\ResourceType;
use PHPStanConfig\Collector\TemplateAssignment\Data\Types\UnionType;
use PHPStanConfig\Collector\TemplateAssignment\PluginsCollector;
use PHPStanConfig\Collector\TemplateAssignment\TemplateAssignedVariablesCollector;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use ReflectionException;
use Throwable;

final class SerializableValuesRule implements Rule
{
    /**
     * @param array<class-string> $disallowedClasses list of disallowed classes (with their subclasses)
     * @param array<class-string> $exactlyDisallowedClasses list of disallowed classes (exact fully qualified class
     * names, no subclass check)
     */
    public function __construct(
        private array $disallowedClasses = [],
        private array $exactlyDisallowedClasses = [],
    ) {
    }

    /**
     * @inheritDoc
     */
    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * @inheritDoc
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof CollectedDataNode) {
            return [];
        }

        $data = $node->get(TemplateAssignedVariablesCollector::class);
        $pluginClasses = $node->get(PluginsCollector::class);
        $errors = [];

        $pluginData = [];

        $pluginMappers = [];
        foreach (PluginClassIterator::iterate($pluginClasses) as $pluginClass) {
            if ($pluginClass->pluginClassName === null) {
                $pluginMappers[] = $pluginClass;
                continue;
            }
            $pluginData['plugins'][$pluginClass->pluginClassName] = [
                'identifier' => $pluginClass->pluginIdentifier,
                'frontendControl' => $pluginClass->frontendPluginControlClassName,
                'mappers' => [],
            ];
            $pluginData['identifiers'][$pluginClass->pluginIdentifier] = $pluginClass->pluginClassName;
            $pluginData['frontendControls'][$pluginClass->frontendPluginControlClassName][] = $pluginClass->pluginClassName;
        }

        foreach ($pluginMappers as $pluginMapper) {
            $pluginClass = $pluginData['identifiers'][$pluginMapper->pluginIdentifier] ?? null;
            if ($pluginClass === null) {
                continue;
            }
            $pluginData['plugins'][$pluginClass]['mappers'][] = $pluginMapper->pluginMapperClassName;
        }

        foreach (TemplateAssignmentIterator::iterate($data) as $assignment) {
            try {
                if ($assignment->insideClassName === null) {
                    continue;
                }
                $plugins = $pluginData['frontendControls'][$assignment->insideClassName] ?? [];
                if ($plugins === []) {
                    continue;
                }
                $isMapped = false;
                foreach ($plugins as $pluginClass) {
                    $plugin = $pluginData['plugins'][$pluginClass] ?? null;
                    if ($plugin === null) {
                        continue;
                    }
                    if ($plugin['mappers'] === []) {
                        continue;
                    }
                    $isMapped = true;
                    break;
                }
                if (!$isMapped) {
                    continue;
                }
                $foundErrors = $this->getTypeErrors($assignment, $assignment->type);
                foreach ($foundErrors as $foundError) {
                    try {
                        $errors[] = RuleErrorBuilder::message($foundError)
                            ->file($assignment->file)
                            ->line($assignment->line)
                            ->build();
                    } catch (Throwable) {}
                }
            } catch (Throwable) {}
        }

        return $errors;
    }

    /**
     * @return string[]
     * @throws ReflectionException
     */
    private function getTypeErrors(TemplateAssignment $assignment, TypeInterface $type): array
    {
        if ($type instanceof MixedType) {
            return [
                sprintf(
                    'Mixed type, part of value which type was deducted as "%s", may not be serializable and must be typed properly to be used for template variable "%s" in: %s;',
                    $assignment->type->describe(),
                    $assignment->templateVariableName,
                    $assignment->assignmentCodeSnippet,
                ),
            ];
        }
        if ($type instanceof ResourceType || $type instanceof IterableType || $type instanceof ClosureType
            || $type instanceof CallableType
        ) {
            $typeName = match($type::class) {
                ResourceType::class => 'Resource',
                IterableType::class => 'Iterable',
                ClosureType::class => 'Closure',
                CallableType::class => 'Callable',
            };
            return [
                sprintf(
                    '%s type, part of value which type was deducted as "%s", is not serializable, thus it can\'t be used for template variable "%s" in: %s;',
                    $typeName,
                    $assignment->type->describe(),
                    $assignment->templateVariableName,
                    $assignment->assignmentCodeSnippet,
                )
            ];
        }
        if ($type instanceof ObjectType) {
            return $this->determineObjectTypeErrors($assignment, $type->class);
        }
        if ($type instanceof ArrayType) {
            return $this->getTypeErrors($assignment, $type->valueType);
        }
        if ($type instanceof IntersectionType || $type instanceof UnionType) {
            $errors = [];
            foreach ($type->types as $subtype) {
                $errors = [
                    ...$errors,
                    ...$this->getTypeErrors($assignment, $subtype),
                ];
            }
            return $errors;
        }
        return [];
    }

    /**
     * @return string[]
     */
    private function determineObjectTypeErrors(TemplateAssignment $assignment, string $className): array
    {
        $disallowedClass = $this->isClassDisallowed($className);
        if (!$disallowedClass) {
            return [];
        }
        return [
            sprintf(
                'Class "%s", part of value which type was deducted as "%s", is not allowed for template variable "%s" in: %s;',
                $disallowedClass,
                $assignment->type->describe(),
                $assignment->templateVariableName,
                $assignment->assignmentCodeSnippet,
            ),
        ];
    }

    private function isClassDisallowed(string $className): ?string
    {
        foreach ($this->exactlyDisallowedClasses as $disallowedClass) {
            if ($disallowedClass === $className) {
                return $disallowedClass;
            }
        }
        $testedObjectType = new \PHPStan\Type\ObjectType($className);
        foreach ($this->disallowedClasses as $disallowedClass) {
            $objectType = new \PHPStan\Type\ObjectType($disallowedClass);
            if ($objectType->isSuperTypeOf($testedObjectType)->yes()) {
                return $disallowedClass;
            }
        }
        return null;
    }
}