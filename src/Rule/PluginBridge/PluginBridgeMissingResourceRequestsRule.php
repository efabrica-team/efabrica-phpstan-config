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

final class PluginBridgeMissingResourceRequestsRule implements Rule
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

        /** @var array<string, RegisterOrWillData> $pluginWillProvideData */
        $pluginWillProvideData = [
        ];
        /** @var array<string, RegisterOrWillData> $pluginRequireToReadData */
        $pluginRequireToReadData = [
        ];

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
                            break;
                        default:
                            $pluginRequireToReadData[$key][] = $registerOrWillData;
                            break;
                    }
                }
            }
        }

        foreach ($pluginRequireToReadData as $resource => $requestCall) {
            if (isset($pluginWillProvideData[$resource])) {
                foreach ($requestCall as $call) {
                    $sameClass = true;
                    foreach ($pluginWillProvideData[$resource] as $willCall) {
                        if ($call->file !== $willCall->file) {
                            $sameClass = false;
                            break;
                        }
                    }
                    if ($sameClass) {
                        try {
                            $errors[] = RuleErrorBuilder::message(sprintf(
                                'Resource "%s" is requested and provided by the same Plugin only!',
                                $call->resourceName
                            ))->file($call->file)->line($call->line)->build();
                        } catch (ShouldNotHappenException) {}
                    }
                }
                continue;
            }
            foreach ($requestCall as $call) {
                try {
                    $errors[] = RuleErrorBuilder::message(sprintf(
                        'Resource "%s" is not provided by any Plugin!',
                        $call->resourceName,
                    ))->file($call->file)->line($call->line)->build();
                } catch (ShouldNotHappenException) {}
            }
        }

        return $errors;
    }
}