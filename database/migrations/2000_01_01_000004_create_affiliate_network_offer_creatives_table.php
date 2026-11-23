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
        $tableName = config('affiliate-network.database.tables.offer_creatives', $tablePrefix . 'offer_creatives');
        $jsonType = commerce_json_column_type('affiliate-network', 'jsonb');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('offer_id');

            $table->string('type')->default('banner');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('external_creative_id')->nullable();
            $table->string('source_asset_url', 2048)->nullable();
            $table->string('destination_url', 2048)->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->text('html_code')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->{$jsonType}('metadata')->nullable();

            $table->timestampsTz();

            $table->index(['offer_id', 'is_active']);
            $table->index('type');
            $table->index(['offer_id', 'external_creative_id']);
        });
    }
};
