<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\TemplateAssignment\Data\Iterator;

use PHPStanConfig\Collector\TemplateAssignment\Data\PluginClass;
use Generator;

final class PluginClassIterator
{
    /**
     * @param array<string, array<PluginClass|array<string, scalar|null>>> $pluginClasses
     * @return Generator<PluginClass>
     */
    public static function iterate(array $pluginClasses): Generator
    {
        foreach ($pluginClasses as $pluginClassesInFile) {
            foreach ($pluginClassesInFile as $pluginClass) {
                if ($pluginClass instanceof PluginClass) {
                    yield $pluginClass;
                    continue;
                }
                if (is_array($pluginClass)) {
                    yield PluginClass::__set_state($pluginClass);
                }
            }
        }
    }
}