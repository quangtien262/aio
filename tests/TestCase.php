<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Concerns\InteractsWithStorefrontRoutes;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use InteractsWithStorefrontRoutes;
}
