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

final class PluginBridgeFrontendControlExistenceRule implements Rule
{
    /** @var class-string */
    private string $frontendPluginControlClass;

    /**
     * @param class-string $frontendPluginControlClass
     */
    public function __construct(
        string $frontendPluginControlClass,
    ) {
        $this->frontendPluginControlClass = $frontendPluginControlClass;
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

        $pluginRegistrationData = $node->get(PluginBridgeRegistersReadsCollector::class);

        /** @var RegisterOrWillData[] $pluginsWithoutControlClasses */
        $pluginsWithoutControlClasses = [];
        /** @var RegisterOrWillData[] $pluginsWithNonExistingControlClasses */
        $pluginsWithNonExistingControlClasses = [];
        /** @var RegisterOrWillData[] $pluginsWithWrongControlClasses */
        $pluginsWithWrongControlClasses = [];

        if ($pluginRegistrationData !== []) {
            foreach ($pluginRegistrationData as $data) {
                foreach ($data as $registerOrWillData) {
                    if (!$registerOrWillData instanceof RegisterOrWillData) {
                        $registerOrWillData = RegisterOrWillData::fromArray($registerOrWillData);
                        if ($registerOrWillData === null) {
                            continue;
                        }
                    }
                    $this->sortBrokenControl(
                        $registerOrWillData,
                        $pluginsWithoutControlClasses,
                        $pluginsWithNonExistingControlClasses,
                        $pluginsWithWrongControlClasses,
                    );
                }
            }
        }

        foreach ($pluginsWithoutControlClasses as $data) {
            try {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'The $frontendControlClass is not defined for Plugin "%s"!',
                    $data->plugin,
                ))->file($data->file)->build();
            } catch (ShouldNotHappenException) {}
        }

        foreach ($pluginsWithNonExistingControlClasses as $data) {
            try {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'The $frontendControlClass in Plugin "%s" defines non-existing PluginControl class "%s"!',
                    $data->plugin,
                    $data->frontendControlClassName,
                ))->file($data->file)->build();
            } catch (ShouldNotHappenException) {}
        }

        foreach ($pluginsWithWrongControlClasses as $data) {
            try {
                $errors[] = RuleErrorBuilder::message(sprintf(
                    'The $frontendControlClass in Plugin "%s" is not instance of "%s"!',
                    $data->plugin,
                    $this->frontendPluginControlClass,
                ))->file($data->file)->build();
            } catch (ShouldNotHappenException) {}
        }

        return $errors;
    }

    /**
     * @param RegisterOrWillData[] $without
     * @param RegisterOrWillData[] $nonExisting
     * @param RegisterOrWillData[] $wrong
     */
    private function sortBrokenControl(RegisterOrWillData $call, array &$without, array &$nonExisting, array &$wrong): void
    {
        if ($call->frontendControlClassName === null) {
            $without[$call->plugin] = $call;
            return;
        }
        if (!class_exists($call->frontendControlClassName)) {
            $nonExisting[$call->plugin] = $call;
            return;
        }
        $parentClasses = class_parents($call->frontendControlClassName);
        if (!in_array($this->frontendPluginControlClass, $parentClasses, true)) {
            $wrong[$call->plugin] = $call;
        }
    }
}