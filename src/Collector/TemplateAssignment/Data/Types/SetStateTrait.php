<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\TemplateAssignment\Data\Types;

use PHPStanConfig\Collector\TemplateAssignment\Data\TypeInterface;

/**
 * @template T of TypeInterface
 */
trait SetStateTrait
{
    public string $__myClass;

    /**
     * @param array<string, mixed> $data
     * @return TypeInterface
     */
    public static function __set_state(array $data): object
    {
        if (isset($data['__myClass'])) {
            unset($data['__myClass']);
        }
        return new self(...$data);
    }
}