<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\TemplateAssignment\Data;

final class PluginClass
{
    public function __construct(
        public ?string $pluginClassName = null,
        public ?string $frontendPluginControlClassName = null,
        public ?string $pluginIdentifier = null,
        public ?string $pluginMapperClassName = null,
    ) {
    }

    /**
     * @param array<string, mixed> $properties
     * @return PluginClass
     */
    public static function __set_state(array $properties): self
    {
        return new self(...$properties);
    }
}