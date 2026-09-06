<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('affiliate-network.database.tables.sites', 'affiliate_network_sites');

        if (! Schema::hasTable($table)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table): void {
            if (! Schema::hasColumn($table, 'catalog_url')) {
                $blueprint->string('catalog_url')->nullable();
            }

            if (! Schema::hasColumn($table, 'catalog_token_encrypted')) {
                $blueprint->text('catalog_token_encrypted')->nullable();
            }

            if (! Schema::hasColumn($table, 'sync_status')) {
                $blueprint->string('sync_status', 32)->default('never');
            }

            if (! Schema::hasColumn($table, 'last_synced_at')) {
                $blueprint->timestampTz('last_synced_at')->nullable();
            }
        });
    }
};
