<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\TemplateAssignment\Data;

final class TemplateAssignment
{
    public function __construct(
        public string $file,
        public int $line,
        public string $templateVariableName,
        public string $assignmentCodeSnippet,
        public TypeInterface $type,
        public ?string $insideClassName = null,
    ) {
    }

    /**
     * @param array<string, mixed> $properties
     * @return TemplateAssignment
     */
    public static function __set_state(array $properties): self
    {
        return new self(...$properties);
    }
}