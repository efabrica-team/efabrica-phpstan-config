<?php

declare(strict_types=1);

namespace PHPStanConfig\Rule\PluginBridge;

use PHPStanConfig\Collector\PluginBridge\Data\PluginFrontendControlRelationData;
use PHPStanConfig\Collector\PluginBridge\Data\ProvideData;
use PHPStanConfig\Collector\PluginBridge\Data\ReadData;
use PHPStanConfig\Collector\PluginBridge\Data\RegisterOrWillData;
use PHPStanConfig\Collector\PluginBridge\PluginBridgeInsertDataCollector;
use PHPStanConfig\Collector\PluginBridge\PluginBridgePluginsCollector;
use PHPStanConfig\Collector\PluginBridge\PluginBridgeReadDataCollector;
use PHPStanConfig\Collector\PluginBridge\PluginBridgeRegistersReadsCollector;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

final class PluginBridgeRegisteredAndProvidedResourcesRule implements Rule
{
    private const UNKNOWN_CONTROL_CLASS = '---UNKNOWN---';

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

        $pluginRegistrationData = $node->get(PluginBridgeRegistersReadsCollector::class);
        $pluginControlProvideData = $node->get(PluginBridgeInsertDataCollector::class);
        $pluginControlReadData = $node->get(PluginBridgeReadDataCollector::class);
        $pluginControlRelationsData = $node->get(PluginBridgePluginsCollector::class);

        $errors = [];

        $pluginWillProvideData = [];
        $pluginRequireToReadData = [];
        $controlProvideData = [];
        $controlReadData = [];
        $pluginControlRelation = [];
        $resourcesLocations = [];

        if ($pluginRegistrationData !== []) {
            foreach ($pluginRegistrationData as $data) {
                foreach ($data as $registerOrWillData) {
                    if (!$registerOrWillData instanceof RegisterOrWillData) {
                        $registerOrWillData = RegisterOrWillData::fromArray($registerOrWillData);
                        if ($registerOrWillData === null) {
                            continue;
                        }
                    }
                    if ($registerOrWillData->resourceName === null) {
                        continue;
                    }
                    $key = $registerOrWillData->resourceName;
                    switch ($registerOrWillData->calledMethod) {
                        case 'setWill':
                            $pluginWillProvideData[$key][] = $registerOrWillData;
                            $resourcesLocations['plugins']['will'][$key][$registerOrWillData->plugin] = [
                                'file' => $registerOrWillData->file,
                                'line' => $registerOrWillData->line,
                            ];
                            break;
                        default:
                            $pluginRequireToReadData[$key][] = $registerOrWillData;
                            $resourcesLocations['plugins']['require'][$key][$registerOrWillData->plugin] = [
                                'file' => $registerOrWillData->file,
                                'line' => $registerOrWillData->line,
                            ];
                            break;
                    }
                }
            }
        }

        if ($pluginControlProvideData !== []) {
            foreach ($pluginControlProvideData as $data) {
                foreach ($data as $collectedProvideData) {
                    if (!$collectedProvideData instanceof ProvideData) {
                        $collectedProvideData = ProvideData::fromArray($collectedProvideData);
                        if ($collectedProvideData === null) {
                            continue;
                        }
                    }
                    if ($collectedProvideData->resourceName === null) {
                        continue;
                    }
                    $key = $collectedProvideData->resourceName;
                    $controlProvideData[$key][] = $collectedProvideData;
                    $resourcesLocations['controls']['provide'][$key][$collectedProvideData->pluginControl] = [
                        'file' => $collectedProvideData->file,
                        'line' => $collectedProvideData->line,
                    ];
                }
            }
        }

        if ($pluginControlReadData !== []) {
            foreach ($pluginControlReadData as $data) {
                foreach ($data as $collectedReadData) {
                    if (!$collectedReadData instanceof ReadData) {
                        $collectedReadData = ReadData::fromArray($collectedReadData);
                        if ($collectedReadData === null) {
                            continue;
                        }
                    }
                    if ($collectedReadData->resourceName === null) {
                        continue;
                    }
                    $key = $collectedReadData->resourceName;
                    $controlReadData[$key][] = $collectedReadData;
                    $resourcesLocations['controls']['read'][$key][$collectedReadData->calledFromFrontendControlClass] = [
                        'file' => $collectedReadData->file,
                        'line' => $collectedReadData->line,
                    ];
                }
            }
        }

        if ($pluginControlRelationsData !== []) {
            foreach ($pluginControlRelationsData as $data) {
                foreach ($data as $pluginControlRelationData) {
                    if (!$pluginControlRelationData instanceof PluginFrontendControlRelationData) {
                        $pluginControlRelationData = PluginFrontendControlRelationData::fromArray($pluginControlRelationData);
                        if ($pluginControlRelationData === null) {
                            continue;
                        }
                    }
                    $pluginControlRelation[$pluginControlRelationData->frontendControlClass][] = $pluginControlRelationData;
                }
            }
        }

        $combinedPluginsData = [];

        foreach ($pluginControlRelation as $frontendControlClass => $plugins) {
            foreach ($plugins as $plugin) {
                $combinedPluginsData[$frontendControlClass]['plugins'][$plugin->pluginClass] = [];
            }
        }

        foreach ($pluginWillProvideData as $willDataCalls) {
            foreach ($willDataCalls as $willDataCall) {
                $controlClassName = $willDataCall->frontendControlClassName ?? self::UNKNOWN_CONTROL_CLASS;
                $combinedPluginsData[$controlClassName]['plugins'][$willDataCall->plugin]['willProvide'][] = $willDataCall->resourceName;
            }
        }
        foreach ($pluginRequireToReadData as $requireDataCalls) {
            foreach ($requireDataCalls as $requireDataCall) {
                $controlClassName = $requireDataCall->frontendControlClassName ?? self::UNKNOWN_CONTROL_CLASS;
                $combinedPluginsData[$controlClassName]['plugins'][$requireDataCall->plugin]['willRead'][] = $requireDataCall->resourceName;
            }
        }

        foreach ($controlProvideData as $provideDataCalls) {
            foreach ($provideDataCalls as $provideDataCall) {
                $control = $provideDataCall->pluginControl ?? self::UNKNOWN_CONTROL_CLASS;
                $combinedPluginsData[$control]['provide'][] = $provideDataCall->resourceName;
            }
        }

        foreach ($controlReadData as $readDataCalls) {
            foreach ($readDataCalls as $readDataCall) {
                $control = $readDataCall->calledFromFrontendControlClass ?? self::UNKNOWN_CONTROL_CLASS;
                $combinedPluginsData[$control]['read'][] = $readDataCall->resourceName;
            }
        }

        foreach ($combinedPluginsData as $pluginControl => $controlData) {
            $plugins = $controlData['plugins'];
            $read = array_unique($controlData['read'] ?? []);
            $provide = array_unique($controlData['provide'] ?? []);
            sort($read);
            sort($provide);
            foreach ($plugins as $plugin => $pluginData) {
                $willRead = array_unique($pluginData['willRead'] ?? []);
                $willProvide = array_unique($pluginData['willProvide'] ?? []);
                if ($read === [] && $provide === [] && $willRead === [] && $willProvide === []) {
                    continue; // There is nothing co compare
                }
                sort($willRead);
                sort($willProvide);
                $diffWillReadToRead = $this->resourceDiffRight($read, $willRead);
                $diffProvideToWillProvide = $this->resourceDiffLeft($provide, $willProvide);
                $diffWillProvideToProvide = $this->resourceDiffRight($provide, $willProvide);
                if ($diffWillReadToRead === [] && $diffProvideToWillProvide === [] && $diffWillProvideToProvide === []) {
                    continue; // There are no differences
                }
                foreach ($diffWillReadToRead as $resource) {
                    try {
                        $file = $resourcesLocations['plugins']['require'][$resource][$plugin]['file'] ?? null;
                        $line = $resourcesLocations['plugins']['require'][$resource][$plugin]['line'] ?? null;
                        $errorBuilder = RuleErrorBuilder::message(sprintf(
                            'Plugin "%s" declares reading of resource "%s" but PluginControl "%s" is not reading it!',
                            $plugin,
                            $resource,
                            $pluginControl,
                        ));
                        if ($file !== null) {
                            $errorBuilder->file($file);
                        }
                        if ($line !== null) {
                            $errorBuilder->line($line);
                        }
                        $errors[] = $errorBuilder->build();
                    } catch (ShouldNotHappenException) {}
                }
                foreach ($diffProvideToWillProvide as $resource) {
                    try {
                        $file = $resourcesLocations['controls']['provide'][$resource][$pluginControl]['file'] ?? null;
                        $line = $resourcesLocations['controls']['provide'][$resource][$pluginControl]['line'] ?? null;
                        $errorBuilder = RuleErrorBuilder::message(sprintf(
                            'PluginControl "%s" is providing resource "%s" without declaration in Plugin "%s"!',
                            $pluginControl,
                            $resource,
                            $plugin,
                        ));
                        if ($file !== null) {
                            $errorBuilder->file($file);
                        }
                        if ($line !== null) {
                            $errorBuilder->line($line);
                        }
                        $errors[] = $errorBuilder->build();
                    } catch (ShouldNotHappenException) {}
                }
                foreach ($diffWillProvideToProvide as $resource) {
                    try {
                        $file = $resourcesLocations['plugins']['will'][$resource][$plugin]['file'] ?? null;
                        $line = $resourcesLocations['plugins']['will'][$resource][$plugin]['line'] ?? null;
                        $errorBuilder = RuleErrorBuilder::message(sprintf(
                            'Plugin "%s" declares provision of resource "%s" but PluginControl "%s" is not providing it!',
                            $plugin,
                            $resource,
                            $pluginControl,
                        ));
                        if ($file !== null) {
                            $errorBuilder->file($file);
                        }
                        if ($line !== null) {
                            $errorBuilder->line($line);
                        }
                        $errors[] = $errorBuilder->build();
                    } catch (ShouldNotHappenException) {}
                }
            }
        }

        return $errors;
    }

    /**
     * @param string[]|null[] $a
     * @param string[]|null[] $b
     * @return string[]|null[]
     */
    private function resourceDiffLeft(array $a, array $b): array
    {
        $c = array_unique(array_diff($a, $b));
        sort($c);
        return $c;
    }

    /**
     * @param string[]|null[] $a
     * @param string[]|null[] $b
     * @return string[]|null[]
     */
    private function resourceDiffRight(array $a, array $b): array
    {
        return $this->resourceDiffLeft($b, $a);
    }
}