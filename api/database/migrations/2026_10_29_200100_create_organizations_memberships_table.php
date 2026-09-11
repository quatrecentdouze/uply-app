<?php

declare(strict_types=1);

use App\Organizations\Contracts\Role;
use App\Shared\Database\EnumValues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('memberships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->index()->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestampsTz();
            $table->unique(['organization_id', 'user_id']);
        });

        DB::statement('ALTER TABLE memberships ADD CONSTRAINT memberships_role_check CHECK (role IN ('.EnumValues::sqlList(...Role::cases()).'))');
    }

    public function down(): void
    {
        Schema::dropIfExists('memberships');
    }
};
