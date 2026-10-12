<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery;
use Tests\Support\Fakes\DBFalsa;
use Tests\Support\Fakes\ModeloFalso;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        ModeloFalso::limpiarTodo();
        DBFalsa::limpiar();
        DBFalsa::instalar();
    }

    protected function tearDown(): void
    {
        if (class_exists(Mockery::class)) {
            Mockery::close();
        }

        ModeloFalso::limpiarTodo();
        DBFalsa::limpiar();

        parent::tearDown();
    }

    /**
     * @return array{0: \Illuminate\Foundation\Application, 1: \Illuminate\Contracts\Console\Kernel}
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        return $app;
    }
}
