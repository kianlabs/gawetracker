<?php

namespace Tests\Unit;

use App\Providers\AppServiceProvider;
use Tests\TestCase;

class DestructiveCommandGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The guard reads live config, so make every case independent of the
        // connection the suite happens to boot with.
        config([
            'gawetracker.shared_database' => 'gawetracker',
            'gawetracker.allow_destructive' => false,
        ]);
    }

    public function test_mysql_connection_pointing_at_the_shared_database_is_prohibited(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => 'gawetracker',
        ]);

        $this->assertTrue(AppServiceProvider::shouldProhibitDestructiveCommands());
    }

    public function test_mysql_connection_pointing_at_another_database_is_allowed(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => 'gt_isolated_throwaway',
        ]);

        $this->assertFalse(AppServiceProvider::shouldProhibitDestructiveCommands());
    }

    public function test_sqlite_connection_is_never_prohibited(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        $this->assertFalse(AppServiceProvider::shouldProhibitDestructiveCommands());
    }

    public function test_escape_hatch_lifts_the_guard(): void
    {
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => 'gawetracker',
            'gawetracker.allow_destructive' => true,
        ]);

        $this->assertFalse(AppServiceProvider::shouldProhibitDestructiveCommands());
    }
}
