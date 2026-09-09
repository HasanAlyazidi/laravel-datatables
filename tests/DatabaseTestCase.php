<?php

namespace HasanAlyazidi\DataTables\Tests;

use HasanAlyazidi\DataTables\Tests\Fixtures\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Three predictable users: Alpha (active), Beta (blocked, no email),
     * Gamma (active).
     */
    protected function seedUsers(): void
    {
        TestUser::create(['name' => 'Alpha One', 'email' => 'alpha@example.com', 'status' => 1]);
        TestUser::create(['name' => 'Beta Two', 'email' => null, 'status' => 0]);
        TestUser::create(['name' => 'Gamma Three', 'email' => 'gamma@example.com', 'status' => 1]);
    }

    protected function seedManyUsers(int $count): void
    {
        $rows = [];
        $now = date('Y-m-d H:i:s');

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'name' => 'User '.$i,
                'email' => 'user'.$i.'@example.com',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        TestUser::insert($rows);
    }
}
