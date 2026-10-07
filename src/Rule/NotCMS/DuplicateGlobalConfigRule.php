<?php

declare(strict_types=1);

namespace PHPStanConfig\Rule\NotCMS;

use PHPStanConfig\Collector\NotCMS\Config;
use PHPStanConfig\Collector\NotCMS\ConfigContext;
use PHPStanConfig\Collector\NotCMS\ConfigsCollector;
use PHPStanConfig\Helper\CollectedConfigsIterator;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

/**
 * @implements Rule<CollectedDataNode>
 */
final class DuplicateGlobalConfigRule implements Rule
{
    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof CollectedDataNode) {
            return [];
        }

        $collectedConfigs = $node->get(ConfigsCollector::class);

        $globalConfigs = [];
        foreach (CollectedConfigsIterator::iterate($collectedConfigs) as $config) {
            if (!$config instanceof Config) {
                continue;
            }
            if ($config->context !== ConfigContext::GLOBAL) {
                continue;
            }
            if (!isset($globalConfigs[$config->name])) {
                $globalConfigs[$config->name] = [];
            }
            $globalConfigs[$config->name][] = $config;
        }

        $multipliedGlobalConfigs = array_filter($globalConfigs, function ($globalConfig) {
            return count($globalConfig) > 1;
        });

        $errors = [];
        foreach ($multipliedGlobalConfigs as $name => $configs) {
            foreach ($configs as $config) {
                try {
                    $errors[] = RuleErrorBuilder::message('Global config with name "' . $name . '" is declared multiple times.')
                        ->file($config->file)
                        ->line($config->line)
                        ->build();
                } catch (ShouldNotHappenException) {
                }
            }
        }

        return $errors;
    }
}
