<?php

declare(strict_types=1);

namespace PHPStanConfig\Helper;

use Generator;
use PHPStanConfig\Collector\NotCMS\Config;

final class CollectedConfigsIterator
{
    public static function iterate(array $configs): Generator
    {
        foreach ($configs as $fileConfigs) {
            foreach ($fileConfigs as $fileConfig) {
                /** @var Config $config */
                foreach ($fileConfig as $config) {
                    yield $config;
                }
            }
        }
    }
}
