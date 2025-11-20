<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\TemplateAssignment\Data\Types;

use PHPStanConfig\Collector\TemplateAssignment\Data\TypeInterface;

final class CallableType implements TypeInterface
{
    /** @implements SetStateTrait<CallableType> */
    use SetStateTrait;

    public function __construct()
    {
        $this->__myClass = self::class;
    }

    public function describe(): string
    {
        return 'callable';
    }
}