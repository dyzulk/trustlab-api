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
        // 1. Create table if not exists (Clean Slate for fresh install)
        if (!Schema::connection('mysql_ca')->hasTable('ca_certificates')) {
            Schema::connection('mysql_ca')->create('ca_certificates', function (Blueprint $table) {
                $table->string('uuid', 32)->primary();
                $table->string('ca_type'); 
                $table->longText('cert_content')->nullable();
                $table->longText('key_content')->nullable();
                $table->string('serial_number')->nullable();
                
                // CDN Integration Columns
                $table->string('cert_path')->nullable();
                $table->string('der_path')->nullable();
                $table->timestamp('last_synced_at')->nullable();
                
                $table->string('common_name')->nullable();
                $table->string('organization')->nullable();
                $table->dateTime('valid_from')->nullable();
                $table->dateTime('valid_to')->nullable();
                
                $table->unsignedBigInteger('download_count')->default(0);
                $table->timestamp('last_downloaded_at')->nullable();
                $table->timestamps();
            });
        } else {
            // 2. Self-Healing: Add missing columns if table already exists (Production Sync)
            Schema::connection('mysql_ca')->table('ca_certificates', function (Blueprint $table) {
                if (!Schema::connection('mysql_ca')->hasColumn('ca_certificates', 'cert_path')) {
                    $table->string('cert_path')->nullable()->after('serial_number');
                }
                if (!Schema::connection('mysql_ca')->hasColumn('ca_certificates', 'der_path')) {
                    $table->string('der_path')->nullable()->after('cert_path');
                }
                if (!Schema::connection('mysql_ca')->hasColumn('ca_certificates', 'last_synced_at')) {
                    $table->timestamp('last_synced_at')->nullable()->after('der_path');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql_ca')->dropIfExists('ca_certificates');
    }
};
