<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // De testbrowser vraagt standaard om Engels; de tests gaan uit van de Nederlandse teksten
        $this->withHeader('Accept-Language', 'nl');
    }
}
