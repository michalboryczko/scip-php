<?php

declare(strict_types=1);

namespace TestData;

class Coordinates
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {
    }
}
