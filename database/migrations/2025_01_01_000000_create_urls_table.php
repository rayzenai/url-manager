<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableName = config('url-manager.table_name', 'urls');
        
        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique()->index();
            $table->morphs('urable');
            $table->string('type')->default('page')->index();
            $table->string('status')->default('active')->index();
            $table->string('redirect_to')->nullable();
            $table->integer('redirect_code')->default(301);
            $table->json('meta')->nullable();
            $table->unsignedBigInteger('visits')->default(0);
            $table->timestamp('last_visited_at')->nullable();
            $table->timestamp('last_modified_at')->nullable();
            $table->timestamps();
            
            // Composite indexes for performance
            $table->index(['status', 'type']);
            $table->index(['urable_type', 'urable_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = config('url-manager.table_name', 'urls');
        Schema::dropIfExists($tableName);
    }
};