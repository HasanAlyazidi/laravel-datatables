<?php

namespace HasanAlyazidi\DataTables\Tests;

use HasanAlyazidi\DataTables\DataTable;
use HasanAlyazidi\DataTables\DataTablesServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        DataTable::localizeSearchUsing(null);

        $this->app['view']->addLocation(__DIR__.'/Fixtures/views');
    }

    protected function tearDown(): void
    {
        DataTable::localizeSearchUsing(null);

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [DataTablesServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Short table names in tests resolve against the fixtures.
        $app['config']->set('datatables.namespace', 'HasanAlyazidi\\DataTables\\Tests\\Fixtures');
    }
}
