<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\TemplateAssignment\Data\Types;

use PHPStanConfig\Collector\TemplateAssignment\Data\TypeInterface;

final class NullType implements TypeInterface
{
    /** @implements SetStateTrait<NullType> */
    use SetStateTrait;

    public function __construct()
    {
        $this->__myClass = self::class;
    }

    public function describe(): string
    {
        return 'null';
    }
}