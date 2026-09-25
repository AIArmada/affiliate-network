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
        $jsonType = commerce_json_column_type('affiliate-network', 'jsonb');

        Schema::create($tablePrefix . 'conversion_legs', function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->uuid('link_id')->index();
            $table->uuid('offer_id')->index();
            $table->uuid('site_id')->nullable()->index();
            $table->string('affiliate_id')->index();
            $table->string('link_code', 64);

            $table->bigInteger('revenue_minor')->default(0);
            $table->string('revenue_currency', 3)->nullable();
            $table->bigInteger('commission_minor')->default(0);
            $table->string('commission_currency', 3);
            $table->bigInteger('fee_minor')->default(0);
            $table->unsignedInteger('fee_bp')->default(0);
            $table->bigInteger('payout_minor')->default(0);

            $table->unsignedInteger('tier_rate_bp')->nullable();
            $table->bigInteger('tier_min_volume_minor')->nullable();

            $table->string('external_reference', 120);
            $table->string('status', 16)->default('posted')->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampTz('occurred_at')->index();
            $table->timestampsTz();

            $table->unique(['link_id', 'external_reference']);
            $table->index(['affiliate_id', 'status']);
        });
    }
};
