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
        Schema::create('legal_pages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('legal_page_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('legal_page_id')->constrained('legal_pages')->onDelete('cascade');
            $table->longText('content');
            
            // Hierarchical Versioning
            $table->integer('major')->default(0);
            $table->integer('minor')->default(0);
            $table->integer('patch')->default(0);
            
            // Publishing Status
            $table->string('status')->default('draft'); // draft, published, archived
            $table->timestamp('published_at')->nullable();

            $table->text('change_log')->nullable();
            $table->boolean('is_active')->default(true); // Internal Soft delete/archive
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_page_revisions');
        Schema::dropIfExists('legal_pages');
    }
};
