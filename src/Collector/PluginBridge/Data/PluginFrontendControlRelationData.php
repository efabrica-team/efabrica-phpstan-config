<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\PluginBridge\Data;

final class PluginFrontendControlRelationData
{
    use SelfReflectionTrait;

    public function __construct(
        public string $file,
        public string $pluginClass,
        public string $frontendControlClass,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): ?PluginFrontendControlRelationData
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
    public static function __set_state(array $data): PluginFrontendControlRelationData
    {
        return new self(...$data);
    }
}