<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\NotCMS;

final class Config
{
    private string $context;

    private string $definitionClass;

    private string $name;

    private string $type;

    private string $file;

    private int $line;

    private ?string $pluginControlClass;

    /**
     * @param ConfigContext::* $context
     */
    public function __construct(
        string $context,
        string $definitionClass,
        string $name,
        string $type,
        string $file,
        int $line,
        ?string $pluginControlClass = null,
    ) {
        $this->context = $context;
        $this->definitionClass = $definitionClass;
        $this->name = $name;
        $this->type = $type;
        $this->file = $file;
        $this->line = $line;
        $this->pluginControlClass = $pluginControlClass;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getDefinitionClass(): string
    {
        return $this->definitionClass;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getFile(): string
    {
        return $this->file;
    }

    public function getLine(): int
    {
        return $this->line;
    }

    public function getPluginControlClass(): ?string
    {
        return $this->pluginControlClass;
    }

    public static function __set_state(array $data)
    {
        return new self(...$data);
    }
}
