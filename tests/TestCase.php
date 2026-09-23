<?php

namespace Tests;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Docker/.env sets APP_ENV=local; PHPUnit env vars may not override it,
        // so CSRF is not auto-skipped during console tests.
        $this->withoutMiddleware(PreventRequestForgery::class);
    }
}
