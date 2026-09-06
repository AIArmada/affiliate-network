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
            if (! Schema::hasColumn($table, 'rate_source')) {
                // synced: importer owns the rate block. manual: an operator
                // overrode rates; sync holds rates back (counts as locked).
                $blueprint->string('rate_source', 16)->default('synced')->index();
            }
        });
    }
};
