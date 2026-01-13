<?php declare(strict_types = 1);

namespace PHPStanConfig\Tests\Mocks;

use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class SomeHermesHandler implements HandlerInterface
{
    public function __construct(public SomeRepository $someRepository) {}

    public function handle(MessageInterface $message): bool
    {
        /** @var mixed[] $data */
        $data = [];

        $export = $this->someRepository->ensure(function () use ($data) {
            return $this->someRepository->findBy()->fetch();
        });

        $export2 = $this->someRepository->ensure(fn () => $this->someRepository->findBy()->fetch());
        $export3 = $this->someRepository->findBy()->fetch();

        return true;
    }
}
