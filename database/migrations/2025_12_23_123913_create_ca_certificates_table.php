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
        if (!Schema::connection('mysql_ca')->hasTable('ca_certificates')) {
            Schema::connection('mysql_ca')->create('ca_certificates', function (Blueprint $table) {
                $table->string('uuid', 32)->primary();
                $table->string('ca_type'); // root, intermediate_4096, intermediate_2048
                $table->longText('cert_content')->nullable();
                $table->longText('key_content')->nullable();
                $table->string('serial_number')->nullable();
                $table->string('common_name')->nullable();
                $table->string('organization')->nullable();
                $table->dateTime('valid_from')->nullable();
                $table->dateTime('valid_to')->nullable();
                
                // Tracking
                $table->unsignedBigInteger('download_count')->default(0);
                $table->timestamp('last_downloaded_at')->nullable();
                $table->timestamps();
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
