<?php

declare(strict_types=1);

namespace TestData;

class Message
{
    public function __construct(
        public ?Address $address = null,
    ) {
    }
}
