<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'pgsql' || $app['config']->get('database.connections.pgsql.database') !== 'helpdesk_testing') {
            throw new \RuntimeException('Tests require the dedicated helpdesk_testing PostgreSQL database.');
        }

        return $app;
    }
}
