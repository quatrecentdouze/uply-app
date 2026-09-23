<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('status_pages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->index()->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestampsTz();
        });

        DB::statement("ALTER TABLE status_pages ADD CONSTRAINT status_pages_slug_check CHECK (slug ~ '^[a-z0-9-]+$')");
    }

    public function down(): void
    {
        Schema::dropIfExists('status_pages');
    }
};
