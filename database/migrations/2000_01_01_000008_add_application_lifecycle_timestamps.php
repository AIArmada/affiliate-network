<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tablePrefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        Schema::table($tablePrefix . 'offer_applications', function (Blueprint $table): void {
            $table->timestampTz('approved_at')->nullable()->after('reviewed_at');
            $table->timestampTz('rejected_at')->nullable()->after('approved_at');
            $table->timestampTz('revoked_at')->nullable()->after('rejected_at');
        });
    }

    public function down(): void
    {
        $tablePrefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        Schema::table($tablePrefix . 'offer_applications', function (Blueprint $table): void {
            $table->dropColumn(['approved_at', 'rejected_at', 'revoked_at']);
        });
    }
};
