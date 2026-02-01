<?php

declare (strict_types=1);

namespace PHPStanConfig\Collector\TemplateAssignment\Data\Types;

use PHPStanConfig\Collector\TemplateAssignment\Data\TypeInterface;

final class ObjectType implements TypeInterface
{
    /** @implements SetStateTrait<ObjectType> */
    use SetStateTrait;

    public function __construct(
        public string $class,
    ) {
        $this->__myClass = self::class;
    }

    public function describe(): string
    {
        return $this->class;
    }
}