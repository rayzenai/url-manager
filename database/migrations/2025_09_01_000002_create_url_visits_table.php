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
        Schema::create('url_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id')->constrained('urls')->onDelete('cascade');
            $table->string('ip_address', 45)->nullable(); // Support IPv4 and IPv6
            $table->string('country_code', 2)->nullable(); // ISO 3166-1 alpha-2 country code
            $table->string('browser', 50)->nullable(); // Parsed browser name
            $table->string('browser_version', 20)->nullable();
            $table->string('device', 20)->nullable(); // Mobile, Desktop, Tablet
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('referer')->nullable();
            $table->jsonb('meta')->nullable(); // Additional metadata
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes for performance
            $table->index('url_id');
            $table->index('user_id');
            $table->index('ip_address');
            $table->index('country_code');
            $table->index('created_at');
            $table->index(['url_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('url_visits');
    }
};