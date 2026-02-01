<?php

declare(strict_types=1);

namespace PHPStanConfig\Collector\PluginBridge;

use PHPStanConfig\Collector\PluginBridge\Data\PluginFrontendControlRelationData;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

class PluginBridgePluginsCollector implements Collector
{
    use CommonPhpParserAnalysisTrait;

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
        return Node\Stmt\Class_::class;
    }

    /**
     * @inheritDoc
     */
    public function processNode(Node $node, Scope $scope): ?PluginFrontendControlRelationData
    {
        if (!$node instanceof Node\Stmt\Class_) {
            return null;
        }

        try {
            /** @var class-string|null $className */
            $className = $node->namespacedName?->__toString();
            if ($className === null) {
                return null;
            }
            $classReflection = new \ReflectionClass($className);
            if (!$classReflection->implementsInterface($this->pluginDefinitionInterface)) {
                return null;
            }

            $frontendControlClass = $this->findFrontendControlClassName($node);

            if (!$frontendControlClass) {
                return null;
            }

            return new PluginFrontendControlRelationData(
                $scope->getFile(),
                $className,
                $frontendControlClass,
            );
        } catch (\ReflectionException) {
            return null;
        }
    }
}