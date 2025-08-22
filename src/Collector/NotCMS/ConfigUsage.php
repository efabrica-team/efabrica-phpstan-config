<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\NotCMS;

final class ConfigUsage
{
    private string $context;

    private ?string $usageClass;

    private string $name;

    private string $type;

    /**
     * @param ConfigContext::* $context
     */
    public function __construct(
        string $context,
        ?string $usageClass,
        string $name,
        string $type,
    ) {
        $this->context = $context;
        $this->usageClass = $usageClass;
        $this->name = $name;
        $this->type = $type;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getUsageClass(): ?string
    {
        return $this->usageClass;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public static function __set_state(array $data)
    {
        return new self(...$data);
    }
}
