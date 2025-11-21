<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\PluginBridge\Data;

final class RegisterOrWillData
{
    use SelfReflectionTrait;

    public function __construct(
        public string $file,
        public string $plugin,
        public string $pluginMethod,
        public string $calledMethod,
        public ?string $resourceName,
        public bool $resourceIsString,
        public int $line,
        public ?string $frontendControlClassName,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?RegisterOrWillData
    {
        try {
            $parameterNames = self::getConstructorParameters(self::class);
        } catch (\ReflectionException) {
            return null;
        }

        $dataKeys = array_keys($data);
        sort($dataKeys);
        sort($parameterNames);

        if ($dataKeys !== $parameterNames) {
            return null;
        }

        return self::__set_state($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function __set_state(array $data): RegisterOrWillData
    {
        return new self(...$data);
    }
}