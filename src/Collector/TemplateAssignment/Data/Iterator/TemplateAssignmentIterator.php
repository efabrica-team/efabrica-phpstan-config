<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\TemplateAssignment\Data\Iterator;

use PHPStanConfig\Collector\TemplateAssignment\Data\TemplateAssignment;
use PHPStanConfig\Collector\TemplateAssignment\Data\TypeInterface;
use PHPStanConfig\Collector\TemplateAssignment\Data\Types\Attributes\ArrayOf;
use Generator;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

final class TemplateAssignmentIterator
{
    /**
     * @param array<string, array<TemplateAssignment|array<string, mixed>>> $assignments
     * @return Generator<TemplateAssignment>
     */
    public static function iterate(array $assignments): Generator
    {
        foreach ($assignments as $assignmentsInFile) {
            foreach ($assignmentsInFile as $assignment) {
                if ($assignment instanceof TemplateAssignment) {
                    yield $assignment;
                    continue;
                }
                if (is_array($assignment)) {
                    try {
                        yield self::reconstruct($assignment);
                    } catch (ReflectionException) {}
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $assignment
     * @throws ReflectionException
     */
    private static function reconstruct(array $assignment): TemplateAssignment
    {
        $assignment['type'] = self::reconstructType($assignment['type']);
        return TemplateAssignment::__set_state($assignment);
    }

    /**
     * @param array<string, mixed> $data
     * @throws ReflectionException
     */
    private static function reconstructType(array $data): TypeInterface
    {
        $className = $data['__myClass'];
        $classReflection = new ReflectionClass($className);
        $constructor = $classReflection->getConstructor();
        $arguments = $constructor?->getParameters() ?? [];
        /** @var ReflectionParameter $argument */
        foreach ($arguments as $argument) {
            /** @var null|ReflectionNamedType|ReflectionUnionType $reflectionType */
            $reflectionType = $argument->getType();
            $type = null;
            if ($reflectionType instanceof ReflectionNamedType) {
                $type = $reflectionType->getName();
            }
            $subtype = null;
            if ($type === 'array') {
                $attributes = $argument->getAttributes(ArrayOf::class);
                if ($attributes === []) {
                    throw new InvalidArgumentException(
                        'Constructor parameter of type array needs to be annotated with ' .
                        ArrayOf::class . ' attribute!',
                    );
                }
                /** @var ArrayOf $instance */
                $instance = $attributes[0]->newInstance();
                $subtype = $instance->type;
            }
            if (array_key_exists($argument->getName(), $data)) {
                if ($type === 'array') {
                    if ($subtype !== null && self::typeExists($subtype)) {
                        $data[$argument->getName()] = array_map(
                            fn (array $subData) => self::reconstructType($subData),
                            $data[$argument->getName()]
                        );
                    }
                } elseif (self::typeExists($type)) {
                    $data[$argument->getName()] = self::reconstructType($data[$argument->getName()]);
                }
            }
        }

        return $className::__set_state($data);
    }

    public static function typeExists(?string $type): bool
    {
        if ($type === null) {
            return false;
        }
        if (class_exists($type)) {
            return true;
        }
        if (interface_exists($type)) {
            return true;
        }
        if (trait_exists($type)) {
            return true;
        }
        if (enum_exists($type)) {
            return true;
        }
        return false;
    }
}