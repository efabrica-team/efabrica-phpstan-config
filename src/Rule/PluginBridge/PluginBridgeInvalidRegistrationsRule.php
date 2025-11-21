<?php

declare(strict_types=1);

namespace PHPStanConfig\Rule\PluginBridge;

use PHPStanConfig\Collector\PluginBridge\Data\RegisterOrWillData;
use PHPStanConfig\Collector\PluginBridge\PluginBridgeRegistersReadsCollector;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

final class PluginBridgeInvalidRegistrationsRule implements Rule
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

        $errors = [];

        $pluginRegistrationData = $node->get(PluginBridgeRegistersReadsCollector::class);

        if ($pluginRegistrationData !== []) {
            foreach ($pluginRegistrationData as $data) {
                foreach ($data as $registerOrWillData) {
                    if (!$registerOrWillData instanceof RegisterOrWillData) {
                        $registerOrWillData = RegisterOrWillData::fromArray($registerOrWillData);
                        if ($registerOrWillData === null) {
                            continue;
                        }
                    }
                    if ($registerOrWillData->resourceName !== null) {
                        continue;
                    }
                    try {
                        $errors[] = RuleErrorBuilder::message('Resource must be declared as string or string constant in ' . $registerOrWillData->calledMethod . '(<resource>)!')
                            ->file($registerOrWillData->file)
                            ->line($registerOrWillData->line)
                            ->build();
                    } catch (ShouldNotHappenException) {}
                }
            }
        }

        return $errors;
    }
}