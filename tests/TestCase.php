<?php

namespace RayzenAI\UrlManager\Tests;

use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase as Orchestra;
use RayzenAI\UrlManager\UrlManagerServiceProvider;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            UrlManagerServiceProvider::class,
        ];
    }

    protected function setUpDatabase(): void
    {
        // Run the urls table migration
        $this->app['db']->connection()->getSchemaBuilder()->create('urls', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique()->index();
            $table->morphs('urable');
            $table->string('type')->default('page')->index();
            $table->string('status')->default('active')->index();
            $table->string('redirect_to')->nullable();
            $table->integer('redirect_code')->default(301);
            $table->jsonb('meta')->nullable();
            $table->unsignedBigInteger('visits')->default(0);
            $table->timestamp('last_visited_at')->nullable();
            $table->timestamp('last_modified_at')->nullable();
            $table->timestamps();
            
            $table->index(['status', 'type']);
            $table->index(['urable_type', 'urable_id']);
        });
    }
}