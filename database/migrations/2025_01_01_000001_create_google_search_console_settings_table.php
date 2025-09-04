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
        Schema::create('google_search_console_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('site_url')->nullable()->comment('Google Search Console property (e.g., sc-domain:example.com)');
            $table->string('frontend_url')->nullable()->comment('Actual website URL for sitemap generation (e.g., https://example.com)');
            $table->text('credentials')->nullable()->comment('Encrypted JSON credentials');
            $table->string('service_account_email')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_search_console_settings');
    }
};