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
        Schema::table('monitors', function (Blueprint $table): void {
            $table->timestampTz('flapping_since')->nullable();
            $table->jsonb('state_changes')->default('[]');
        });

        DB::statement("ALTER TABLE monitors ADD CONSTRAINT monitors_state_changes_check CHECK (jsonb_typeof(state_changes) = 'array')");
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table): void {
            $table->dropColumn(['flapping_since', 'state_changes']);
        });
    }
};
