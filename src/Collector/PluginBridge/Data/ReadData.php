<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\PluginBridge\Data;

final class ReadData
{
    use SelfReflectionTrait;

    public function __construct(
        public string $file,
        public string $calledMethod,
        public string $calledFromMethod,
        public ?string $calledFromFrontendControlClass,
        public bool $resourceIsFetched,
        public bool $resourceIsString,
        public ?string $resourceName,
        public int $line,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?ReadData
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
    public static function __set_state(array $data): ReadData
    {
        return new self(...$data);
    }
}