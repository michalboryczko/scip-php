<?php

declare(strict_types=1);

namespace TestData;

class CallsService
{
    private CallsRepository $repo;

    public function __construct(CallsRepository $repo)
    {
        $this->repo = $repo;
    }

    public function process(object $order): void
    {
        // Method call with arguments
        $this->repo->save($order, true);

        // Method call with zero arguments
        $items = $this->repo->findAll();

        // Static call
        $newRepo = CallsRepository::create('test');

        // Constructor call (new)
        $anotherRepo = new CallsRepository();
    }

    public function chainedCalls(): void
    {
        // Not truly chained (no fluent interface), but two separate calls
        $this->repo->findAll();
        $this->repo->save(new \stdClass());
    }

    public function namedArgs(): void
    {
        // Named argument (PHP 8)
        $this->repo->save(flush: true, entity: new \stdClass());
    }

    public function nullsafeCall(): void
    {
        // Nullsafe method call (uses $this->repo which has known type)
        $this->repo?->findAll();
    }
}
