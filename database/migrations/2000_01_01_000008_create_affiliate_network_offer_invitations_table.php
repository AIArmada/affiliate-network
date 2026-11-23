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
        $tableName = config('affiliate-network.database.tables.offer_invitations', $tablePrefix . 'offer_invitations');
        $jsonType = commerce_json_column_type('affiliate-network', 'jsonb');

        Schema::create($tableName, function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('offer_id')->index();
            $table->string('affiliate_id')->nullable()->index();
            $table->string('token', 64)->unique();
            $table->string('invited_by')->nullable();
            $table->boolean('auto_approve')->default(true);
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();
            $table->timestampsTz();
        });
    }
};
