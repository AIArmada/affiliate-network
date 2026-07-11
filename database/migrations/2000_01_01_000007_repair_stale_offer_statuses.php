<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $prefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');
        $tableName = config('affiliate-network.database.tables.offers', $prefix . 'offers');

        DB::table($tableName)
            ->where('status', 'pending')
            ->update(['status' => 'draft']);
    }
};
