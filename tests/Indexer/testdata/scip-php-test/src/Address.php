<?php

declare(strict_types=1);

namespace TestData;

class Address
{
    public function __construct(
        public ?string $street = null,
        public ?Coordinates $coordinates = null,
    ) {
    }
}
