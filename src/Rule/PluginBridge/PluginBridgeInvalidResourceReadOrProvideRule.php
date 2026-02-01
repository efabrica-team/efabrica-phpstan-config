<?php

declare(strict_types=1);

namespace PHPStanConfig\Rule\PluginBridge;

use PHPStanConfig\Collector\PluginBridge\Data\ProvideData;
use PHPStanConfig\Collector\PluginBridge\Data\ReadData;
use PHPStanConfig\Collector\PluginBridge\PluginBridgeInsertDataCollector;
use PHPStanConfig\Collector\PluginBridge\PluginBridgeReadDataCollector;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

final class PluginBridgeInvalidResourceReadOrProvideRule implements Rule
{

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

        $pluginControlProvideData = $node->get(PluginBridgeInsertDataCollector::class);
        $pluginControlReadData = $node->get(PluginBridgeReadDataCollector::class);

        $errors = [];

        /** @var ProvideData[] $controlProvideData */
        $controlProvideData = [];
        /** @var ReadData[] $controlReadData */
        $controlReadData = [];

        if ($pluginControlProvideData !== []) {
            foreach ($pluginControlProvideData as $data) {
                foreach ($data as $collectedProvideData) {
                    if (!$collectedProvideData instanceof ProvideData) {
                        $collectedProvideData = ProvideData::fromArray($collectedProvideData);
                        if ($collectedProvideData === null) {
                            continue;
                        }
                    }
                    if ($collectedProvideData->resourceName !== null) {
                        continue;
                    }
                    $controlProvideData[] = $collectedProvideData;
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
                    if ($collectedReadData->resourceName !== null) {
                        continue;
                    }
                    $controlReadData[] = $collectedReadData;
                }
            }
        }

        foreach ($controlProvideData as $resource) {
            try {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'Provided resource must be declared as string or string constant in PluginControl "%s"!',
                    $resource->pluginControl,
                ))->file($resource->file)->line($resource->line)->build();
            } catch (ShouldNotHappenException) {}
        }

        foreach ($controlReadData as $resource) {
            try {
                if ($resource->resourceIsFetched === false && $resource->calledMethod === 'getBridgeData') {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Using getBridgeData() must be followed by resource selection from methods returned array in PluginControl "%s" [%s]!',
                        $resource->calledFromFrontendControlClass,
                        'as $value = $this->getBridgeData()[<resource>];',
                    ))->file($resource->file)->line($resource->line)->build();
                } else {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Read resource must be declared as string or string constant in PluginControl "%s"!',
                        $resource->calledFromFrontendControlClass,
                    ))->file($resource->file)->line($resource->line)->build();
                }
            } catch (ShouldNotHappenException) {}
        }

        return $errors;
    }
}