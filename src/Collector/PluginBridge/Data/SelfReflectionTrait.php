<?php

namespace PHPStanConfig\Collector\PluginBridge\Data;

use ReflectionException;

trait SelfReflectionTrait
{
    /**
     * @param class-string $className
     * @return string[]
     * @throws ReflectionException
     */
    private static function getConstructorParameters(string $className): array
    {
        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        $parameters = $constructor?->getParameters() ?? [];
        $parameterNames = [];

        foreach ($parameters as $parameter) {
            $parameterNames[] = $parameter->getName();
        }

        return $parameterNames;
    }
}