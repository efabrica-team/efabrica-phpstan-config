<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\NotCMS;

final class Config
{
    /**
     * @param ConfigContext::* $context
     */
    public function __construct(
        public string $context,
        public string $definitionClass,
        public string $name,
        public string $type,
        public string $file,
        public int $line,
        public ?string $pluginControlClass = null,
        public ?string $backendPluginControlClass = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return Config
     */
    public static function __set_state(array $data)
    {
        return new self(...$data);
    }
}
