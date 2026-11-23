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
        $tableName = config('affiliate-network.database.tables.offer_coupons', $tablePrefix . 'offer_coupons');
        $jsonType = commerce_json_column_type('affiliate-network', 'jsonb');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('offer_id')->index();
            $table->foreignUuid('site_id');
            $table->string('affiliate_id')->index();
            $table->string('code', 64);
            $table->boolean('is_active')->default(true);
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->unique(['site_id', 'code']);
        });
    }
};
