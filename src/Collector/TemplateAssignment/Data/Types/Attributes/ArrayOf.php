<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\TemplateAssignment\Data\Types\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class ArrayOf
{
    /**
     * @param class-string $type
     */
    public function __construct(
        public string $type
    ) {}
}