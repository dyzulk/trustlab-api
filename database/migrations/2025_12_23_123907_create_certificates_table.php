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
        Schema::create('certificates', function (Blueprint $table) {
            $table->string('uuid', 32)->primary();
            $table->string('user_id', 32)->index();
            $table->string('common_name');
            $table->string('organization')->nullable();
            $table->string('locality')->nullable();
            $table->string('state')->nullable();
            $table->string('country', 2)->nullable();
            $table->text('san')->nullable();
            $table->string('status')->default('ISSUED')->index();
            $table->integer('key_bits')->default(2048);
            $table->string('serial_number')->nullable();
            $table->longText('cert_content')->nullable();
            $table->longText('key_content')->nullable();
            $table->longText('csr_content')->nullable();
            $table->dateTime('valid_from')->nullable();
            $table->dateTime('valid_to')->nullable();
            $table->timestamp('expired_notification_sent_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
