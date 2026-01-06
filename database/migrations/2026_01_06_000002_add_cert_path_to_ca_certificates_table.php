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
        Schema::connection('mysql_ca')->table('ca_certificates', function (Blueprint $table) {
            $table->string('cert_path')->nullable()->after('serial_number');
            $table->timestamp('last_synced_at')->nullable()->after('cert_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql_ca')->table('ca_certificates', function (Blueprint $table) {
            $table->dropColumn(['cert_path', 'last_synced_at']);
        });
    }
};
