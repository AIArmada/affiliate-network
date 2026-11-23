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
        $tableName = config('affiliate-network.database.tables.offer_applications', $tablePrefix . 'offer_applications');
        $jsonType = commerce_json_column_type('affiliate-network', 'jsonb');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('offer_id');
            $table->string('affiliate_id');
            $table->foreignUuid('invitation_id')->nullable()->index();

            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampTz('rejected_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();

            $table->timestampsTz();

            $table->unique(['offer_id', 'affiliate_id']);
            $table->index(['affiliate_id', 'status']);
            $table->index('status');
        });
    }
};
