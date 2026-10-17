<?php

declare(strict_types=1);

use App\Monitoring\Enums\HttpMethod;
use App\Shared\Database\EnumValues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE monitors DROP CONSTRAINT monitors_method_check');
        DB::statement('ALTER TABLE monitors ADD CONSTRAINT monitors_method_check CHECK (method IN ('.EnumValues::sqlList(...HttpMethod::cases()).'))');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE monitors DROP CONSTRAINT monitors_method_check');
        DB::statement('ALTER TABLE monitors ADD CONSTRAINT monitors_method_check CHECK (method IN ('.EnumValues::sqlList(HttpMethod::Get, HttpMethod::Head).'))');
    }
};
