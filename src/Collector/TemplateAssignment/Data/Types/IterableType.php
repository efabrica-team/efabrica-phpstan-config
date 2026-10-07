<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\TemplateAssignment\Data\Types;

use PHPStanConfig\Collector\TemplateAssignment\Data\TypeInterface;

final class IterableType implements TypeInterface
{
    /** @implements SetStateTrait<IterableType> */
    use SetStateTrait;

    public function __construct(
        public TypeInterface $keyType,
        public TypeInterface $valueType,
    ) {
        $this->__myClass = self::class;
    }

    public function describe(): string
    {
        if ($this->keyType instanceof MixedType) {
            return sprintf('iterable<%s>', $this->valueType->describe());
        }
        return sprintf('iterable<%s, %s>', $this->keyType->describe(), $this->valueType->describe());
    }
}