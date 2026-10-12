<?php

namespace Tests\Unit;

use Tests\TestCase;

class HumoTest extends TestCase
{
    public function test_la_aplicacion_arranca(): void
    {
        $this->assertTrue($this->app->isBooted());
    }
}
