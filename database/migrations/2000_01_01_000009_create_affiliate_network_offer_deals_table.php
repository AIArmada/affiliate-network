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
        $tableName = config('affiliate-network.database.tables.offer_deals', $tablePrefix . 'offer_deals');
        $jsonType = commerce_json_column_type('affiliate-network', 'jsonb');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('offer_id');
            $table->string('affiliate_id');
            $table->unsignedInteger('rate_base_bp')->nullable();
            $table->unsignedInteger('rate_fixed_minor')->nullable();
            $table->unsignedInteger('network_fee_bp')->nullable();
            $table->string('currency', 3)->nullable();
            $table->{$jsonType}('volume_tiers')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestampsTz();

            $table->index(['offer_id', 'affiliate_id', 'is_active']);
        });
    }
};
