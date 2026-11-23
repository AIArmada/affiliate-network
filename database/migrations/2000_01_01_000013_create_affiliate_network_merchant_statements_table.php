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
        $tableName = config('affiliate-network.database.tables.merchant_statements', $tablePrefix . 'merchant_statements');
        $jsonType = commerce_json_column_type('affiliate-network', 'jsonb');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');
            $table->foreignUuid('site_id')->index();
            $table->string('statement_number', 64)->unique();
            $table->string('status', 16)->default('draft')->index();
            $table->string('currency', 3);
            $table->timestampTz('period_start');
            $table->timestampTz('period_end');
            $table->unsignedInteger('leg_count')->default(0);
            $table->bigInteger('gross_revenue_minor')->default(0);
            $table->bigInteger('commission_minor')->default(0);
            $table->bigInteger('fee_minor')->default(0);
            $table->bigInteger('payout_minor')->default(0);
            $table->timestampTz('issued_at')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->string('payment_reference')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();

            $table->index(['owner_type', 'owner_id', 'status']);
        });
    }
};
