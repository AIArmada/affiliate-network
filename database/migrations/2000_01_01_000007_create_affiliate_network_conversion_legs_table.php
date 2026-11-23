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
        $tableName = config('affiliate-network.database.tables.conversion_legs', $tablePrefix . 'conversion_legs');
        $jsonType = commerce_json_column_type('affiliate-network', 'jsonb');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('link_id')->nullable()->index();
            $table->foreignUuid('offer_id')->index();
            $table->foreignUuid('site_id')->nullable()->index();
            $table->string('affiliate_id')->index();
            $table->string('link_code', 64)->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->foreignUuid('parent_leg_id')->nullable()->index();

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
            $table->string('event_type', 64)->default('purchase');
            $table->string('status', 16)->default('posted')->index();
            $table->string('coupon_code', 64)->nullable()->index();
            $table->string('sub_id')->nullable()->index();
            $table->string('sub_id_2')->nullable();
            $table->string('sub_id_3')->nullable();
            $table->foreignUuid('statement_id')->nullable()->index();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampTz('locks_at')->nullable()->index();
            $table->timestampTz('locked_at')->nullable();
            $table->timestampTz('fulfilled_at')->nullable()->index();
            $table->timestampTz('occurred_at')->index();
            $table->timestampsTz();

            $table->index(['link_id', 'external_reference']);
            $table->index(['affiliate_id', 'status']);
            $table->index(['offer_id', 'status', 'occurred_at']);
            $table->index(['site_id', 'status', 'statement_id']);
        });
    }
};
