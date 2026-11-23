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
        $tableName = config('affiliate-network.database.tables.creator_postbacks', $tablePrefix . 'creator_postbacks');
        $jsonType = commerce_json_column_type('affiliate-network', 'jsonb');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->string('affiliate_id')->index();
            $table->foreignUuid('offer_id')->nullable()->index();
            $table->string('url_template', 2048);
            $table->string('http_method', 8)->default('GET');
            $table->{$jsonType}('events');
            $table->text('signing_secret_encrypted')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
        });
    }
};
