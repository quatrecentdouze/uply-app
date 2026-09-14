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
        Schema::create('invitations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->index()->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role');
            $table->char('token_hash', 64)->unique();
            $table->foreignUlid('invited_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE invitations ADD CONSTRAINT invitations_role_check CHECK (role IN ('.EnumValues::sqlList(...Role::cases()).'))');
        DB::statement('CREATE UNIQUE INDEX invitations_pending_email_unique ON invitations (organization_id, lower(email)) WHERE accepted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
