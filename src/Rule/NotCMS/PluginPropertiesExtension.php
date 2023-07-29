<?php

declare(strict_types = 1);

namespace PHPStanConfig\Rule\NotCMS;

use PHPStan\Reflection\PropertyReflection;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;
use function strpos;

class PluginPropertiesExtension implements ReadWritePropertiesExtension
{
    public function isAlwaysRead(PropertyReflection $property, string $propertyName): bool
    {
        return false;
    }

    public function isAlwaysWritten(PropertyReflection $property, string $propertyName): bool
    {
        return $this->isInitialized($property, $propertyName);
    }

    public function isInitialized(PropertyReflection $property, string $propertyName): bool
    {
        return !$property->isPublic() && strpos($property->getDocComment() ?? '', '@plugin-init ') !== false;
    }
}