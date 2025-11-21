<?php

declare(strict_types=1);

namespace PHPStanConfig\Helper;

use PHPStanConfig\Collector\NotCMS\ConfigContext;
use PHPStanConfig\Collector\NotCMS\ConfigUsage;
use Generator;

final class CollectedUsagesIterator
{
    /**
     * @param array<string, array<ConfigUsage|array<string, scalar|null|ConfigContext::*>>> $configs
     * @return Generator<ConfigUsage>
     */
    public static function iterate(array $configs): Generator
    {
        foreach ($configs as $fileConfigs) {
            /** @var ConfigUsage|array<string, scalar> $config */
            foreach ($fileConfigs as $config) {
                if (is_array($config)) {
                    /** @var ConfigUsage $newConfig */
                    $newConfig = ConfigUsage::__set_state($config);
                    yield $newConfig;
                    continue;
                }
                yield $config;
            }
        }
    }
}
