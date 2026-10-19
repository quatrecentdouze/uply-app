<?php

declare(strict_types=1);

use App\Monitoring\Enums\KeywordMatch;
use App\Monitoring\Settings\MonitoringSettings;
use App\Shared\Database\EnumValues;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitors', function (Blueprint $table): void {
            $table->text('keyword')->nullable();
            $table->string('keyword_match', 16)->nullable();
        });
        DB::table('monitors')->update(['keyword_match' => app(MonitoringSettings::class)->monitorDefaults->keywordMatch->value]);
        Schema::table('monitors', function (Blueprint $table): void {
            $table->string('keyword_match', 16)->nullable(false)->change();
        });

        DB::statement('ALTER TABLE monitors ADD CONSTRAINT monitors_keyword_match_check CHECK (keyword_match IN ('.EnumValues::sqlList(...KeywordMatch::cases()).'))');
        DB::statement("ALTER TABLE monitors ADD CONSTRAINT monitors_keyword_check CHECK (keyword IS NULL OR keyword <> '')");
    }

    public function down(): void
    {
        Schema::table('monitors', function (Blueprint $table): void {
            $table->dropColumn(['keyword', 'keyword_match']);
        });
    }
};
