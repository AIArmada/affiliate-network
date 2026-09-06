<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('affiliate-network.database.tables.offers', 'affiliate_network_offers');

        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (! Schema::hasColumn($table, 'external_program_id')) {
                $blueprint->string('external_program_id')->nullable()->index();
            }

            if (! Schema::hasColumn($table, 'subject_type')) {
                $blueprint->string('subject_type', 64)->nullable();
            }

            if (! Schema::hasColumn($table, 'subject_key')) {
                $blueprint->string('subject_key')->nullable();
            }

            if (! Schema::hasColumn($table, 'source_url')) {
                $blueprint->string('source_url')->nullable();
            }

            if (! Schema::hasColumn($table, 'source_checksum')) {
                $blueprint->string('source_checksum', 64)->nullable();
            }

            if (! Schema::hasColumn($table, 'last_synced_at')) {
                $blueprint->timestampTz('last_synced_at')->nullable();
            }
        });
    }
};
