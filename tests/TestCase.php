<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * RefreshDatabase wipes the database it is pointed at. Check that it's the test one before any
     * test trait gets the chance to, so a misconfigured environment can never erase real data.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $database = (string) config('database.connections.'.config('database.default').'.database');

        if (! str_ends_with($database, '_test')) {
            throw new \RuntimeException("Refusing to run tests against \"{$database}\": the test database name must end in _test.");
        }
    }

    /**
     * Between two requests in one test, forget the signed-in user and the token the JWT package
     * cached, so the next request authenticates from its own Authorization header, like a real one.
     * (The database is left alone, so RefreshDatabase's transaction keeps working.)
     */
    protected function newRequestCycle(): void
    {
        $this->app['auth']->forgetGuards();

        foreach (['tymon.jwt', 'tymon.jwt.auth'] as $service) {
            $this->app->forgetInstance($service);
        }
    }
}
