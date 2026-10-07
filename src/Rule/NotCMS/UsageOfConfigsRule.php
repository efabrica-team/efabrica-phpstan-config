<?php

declare(strict_types=1);

namespace PHPStanConfig\Rule\NotCMS;

use PHPStanConfig\Collector\NotCMS\Config;
use PHPStanConfig\Collector\NotCMS\ConfigContext;
use PHPStanConfig\Collector\NotCMS\ConfigsCollector;
use PHPStanConfig\Collector\NotCMS\ConfigUsage;
use PHPStanConfig\Collector\NotCMS\ConfigUsagesCollector;
use PHPStanConfig\Helper\CollectedConfigsIterator;
use PHPStanConfig\Helper\CollectedUsagesIterator;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

/**
 * @implements Rule<CollectedDataNode>
 */
final class UsageOfConfigsRule implements Rule
{
    /** @var class-string */
    private string $pluginDefinitionInterface;

    /**
     * @param class-string $pluginDefinitionInterface
     */
    public function __construct(
        string $pluginDefinitionInterface,
    ) {
        $this->pluginDefinitionInterface = $pluginDefinitionInterface;
    }


    /**
     * @inheritDoc
     */
    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    /**
     * @inheritDoc
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof CollectedDataNode) {
            return [];
        }

        $errors = [];

        $collectedUsages = $node->get(ConfigUsagesCollector::class);
        $collectedConfigs = $node->get(ConfigsCollector::class);

        $pluginConfigs = [];
        $pageConfigs = [];
        $globalConfigs = [];

        $pluginUsage = [];
        $pageUsage = [];
        $globalUsage = [];

        foreach (CollectedConfigsIterator::iterate($collectedConfigs) as $config) {
            if (!$config instanceof Config) {
                continue;
            }
            if ($config->context === ConfigContext::PLUGIN) {
                $pluginConfigs[$config->pluginControlClass ?? 'unknown'][$config->definitionClass][$config->name][] = $config;
                $pluginConfigs['be-' . ($config->backendPluginControlClass ?? 'unknown')][$config->definitionClass][$config->name][] = $config;
            } elseif ($config->context === ConfigContext::PAGE) {
                $pageConfigs[$config->name][] = $config;
            } elseif ($config->context === ConfigContext::GLOBAL) {
                $globalConfigs[$config->name][] = $config;
            }
        }

        foreach (CollectedUsagesIterator::iterate($collectedUsages) as $config) {
            if (!$config instanceof ConfigUsage) {
                continue;
            }
            if ($config->context === ConfigContext::PLUGIN) {
                $pluginUsage[$config->usageClass][$config->name][] = $config;
            } elseif ($config->context === ConfigContext::PAGE) {
                $pageUsage[$config->name][] = $config;
            } elseif ($config->context === ConfigContext::GLOBAL) {
                $globalUsage[$config->name][] = $config;
            }
        }

        $globalConfigNames = array_keys($globalConfigs);
        foreach ($globalUsage as $name => $usages) {
            if (in_array($name, $globalConfigNames, true)) {
                continue;
            }
            foreach ($usages as $usage) {
                try {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Accessing global config named "%s" that is not defined in any plugin.',
                        $usage->name,
                    ))->file($usage->file)->line($usage->line)->build();
                } catch (ShouldNotHappenException) {
                }
            }
        }

        $pageConfigNames = array_keys($pageConfigs);
        foreach ($pageUsage as $name => $usages) {
            if (in_array($name, $pageConfigNames, true)) {
                continue;
            }
            foreach ($usages as $usage) {
                try {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Accessing page config named "%s" that is not defined in any plugin.',
                        $usage->name,
                    ))->file($usage->file)->line($usage->line)->build();
                } catch (ShouldNotHappenException) {
                }
            }
        }

        foreach ($pluginUsage as $pluginControl => $names) {
            foreach ($names as $name => $usages) {
                foreach ($usages as $usage) {
                    if (!isset($pluginConfigs[$pluginControl]) && !isset($pluginConfigs['be-' . $pluginControl])) {
                        try {
                            if ($usage->definingClass === $usage->usageClass) {
                                $message = sprintf(
                                    'Plugin control "%s" is using plugin config named "%s", but plugin control is not connected to any plugin.',
                                    $pluginControl,
                                    $name,
                                );
                            } else {
                                /** @var ?string $definingClass */
                                $definingClass = $usage->definingClass;
                                if ($definingClass !== null
                                    && is_subclass_of($definingClass, $this->pluginDefinitionInterface)
                                    && $usage->definingFunction === 'innerPluginsDefinition'
                                    && $this->isNameDefinedInPlugin($name, $definingClass, $pluginConfigs)
                                ) {
                                    // This case is OK
                                    continue;
                                }
                                $message = sprintf(
                                    'Class "%s" is using plugin config named "%s" as plugin control "%s", but the control is not connected to any plugin.',
                                    $definingClass ?? 'unknown',
                                    $name,
                                    $pluginControl,
                                );
                            }
                            $errors[] = RuleErrorBuilder::message($message)->file($usage->file)->line($usage->line)->build();
                        } catch (ShouldNotHappenException) {
                        }
                        continue;
                    }
                    $plugins = [
                        ...$pluginConfigs[$pluginControl] ?? [],
                        ...$pluginConfigs['be-' . $pluginControl] ?? [],
                    ];
                    foreach ($plugins as $plugin => $declaredNames) {
                        $declaredNameKeys = array_keys($declaredNames);
                        if (!in_array($name, $declaredNameKeys, true)) {
                            try {
                                $errors[] = RuleErrorBuilder::message(sprintf(
                                    'Plugin control "%s" is using plugin config named "%s", but the definition of such config is missing in connected plugin "%s".',
                                    $pluginControl,
                                    $name,
                                    $plugin,
                                ))->file($usage->file)->line($usage->line)->build();
                            } catch (ShouldNotHappenException) {
                            }
                        }
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, array<string, array<string, Config[]>>> $pluginConfigs
     */
    private function isNameDefinedInPlugin(string $name, string $pluginClass, array $pluginConfigs): bool
    {
        foreach ($pluginConfigs as $arrayOfPlugins) {
            foreach ($arrayOfPlugins as $plugin => $declaredNames) {
                if ($plugin !== $pluginClass) {
                    continue;
                }
                if (array_key_exists($name, $declaredNames)) {
                    return true;
                }
            }
        }

        return false;
    }
}
