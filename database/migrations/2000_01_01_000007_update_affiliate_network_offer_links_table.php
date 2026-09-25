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

        Schema::table($tablePrefix . 'offer_links', function (Blueprint $table): void {
            $table->foreignUuid('link_id')->nullable()->after('id');
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'target_url', 'custom_parameters', 'expires_at']);

            $table->index('link_id');
        });
    }
};
