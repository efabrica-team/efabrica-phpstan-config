<?php

declare(strict_types=1);

namespace PHPStanConfig\Helper;

use PHPStanConfig\Collector\NotCMS\Config;
use Generator;

final class CollectedConfigsIterator
{
    /**
     * @param array<string, array<int, array<Config|array<string, scalar>>>> $configs
     * @return Generator<Config>
     */
    public static function iterate(array $configs): Generator
    {
        foreach ($configs as $fileConfigs) {
            foreach ($fileConfigs as $fileConfig) {
                /** @var Config|array<string, scalar> $config */
                foreach ($fileConfig as $config) {
                    if (is_array($config)) {
                        /** @var Config $newConfig */
                        $newConfig = Config::__set_state($config);
                        yield $newConfig;
                        continue;
                    }
                    yield $config;
                }
            }
        }
    }
}
