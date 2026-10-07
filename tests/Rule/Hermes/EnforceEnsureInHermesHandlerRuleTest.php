<?php declare(strict_types=1);

namespace PHPStanConfig\Tests\Rules\Hermes;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use PHPStanConfig\Rule\Hermes\EnforceEnsureInHermesHandlerRule;

final class EnforceEnsureInHermesHandlerRuleTest extends RuleTestCase
{
    public static function getAdditionalConfigFiles(): array
    {
        return [
            __DIR__ . '/../../test.phpstan.neon',
        ];
    }

    protected function getRule(): Rule
    {
        return self::getContainer()->getByType(EnforceEnsureInHermesHandlerRule::class);
    }

    public function testGenericHandler(): void
    {
        $this->analyse([__DIR__ . '/../../Mocks/SomeGenericHandler.php'], []);
    }

    public function testHermesHandler(): void
    {
        $this->analyse([__DIR__ . '/../../Mocks/SomeHermesHandler.php'], [
            [
                'Repository call \'findBy\' on \'$this->someRepository\' must be wrapped in ensure() in Hermes handlers.',
                22,
            ],
        ]);
    }
}
