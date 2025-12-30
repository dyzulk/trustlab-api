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
        Schema::create('users', function (Blueprint $table) {
            $table->string('id', 32)->primary();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            
            // 2FA columns
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            $table->string('role')->default('customer');
            $table->string('avatar')->nullable();

            // Profile & Contact
            $table->string('phone')->nullable();
            $table->text('bio')->nullable();
            $table->string('job_title')->nullable();
            $table->string('location')->nullable();
            $table->string('country')->nullable();
            $table->string('city_state')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('tax_id')->nullable();

            // Notification Settings
            $table->boolean('settings_email_alerts')->default(true);
            $table->boolean('settings_certificate_renewal')->default(true);

            // Preferences
            $table->string('default_landing_page')->default('/dashboard');
            $table->string('theme')->default('system');
            $table->string('language')->default('en');

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('user_id', 32)->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
