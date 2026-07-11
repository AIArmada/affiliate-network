<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');
        $tableName = config('affiliate-network.database.tables.sites', $prefix . 'sites');
        $jsonType = config('affiliate-network.database.json_column_type', commerce_json_column_type('affiliate-network', 'jsonb'));

        Schema::table($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->{$jsonType}('allowed_redirect_hosts')->nullable();
        });
    }
};
