<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\NotCMS;

final class ConfigUsage
{
    public function __construct(
        public string $context,
        public ?string $usageClass,
        public ?string $definingClass,
        public string $name,
        public ?string $type,
        public ?string $definingFunction,
        public int $line,
        public string $file,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     * @return ConfigUsage
     */
    public static function __set_state(array $data)
    {
        return new self(...$data);
    }
}
