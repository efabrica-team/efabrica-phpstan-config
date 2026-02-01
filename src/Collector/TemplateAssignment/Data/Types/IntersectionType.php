<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\TemplateAssignment\Data\Types;

use PHPStanConfig\Collector\TemplateAssignment\Data\TypeInterface;
use PHPStanConfig\Collector\TemplateAssignment\Data\Types\Attributes\ArrayOf;

final class IntersectionType implements TypeInterface
{
    /** @implements SetStateTrait<IntersectionType> */
    use SetStateTrait;

    /**
     * @param array<TypeInterface> $types
     */
    public function __construct(
        #[ArrayOf(TypeInterface::class)]
        public array $types
    ) {
        $this->__myClass = self::class;
    }

    public function describe(): string
    {
        $describedTypes = array_reduce(
            $this->types,
            function (array $carry, TypeInterface $type): array {
                if ($type instanceof UnionType) {
                    $carry[] = '(' . $this->describe() . ')';
                } else {
                    $carry[] = $type->describe();
                }
                return $carry;
            },
            [],
        );

        return implode('&', $describedTypes);
    }
}