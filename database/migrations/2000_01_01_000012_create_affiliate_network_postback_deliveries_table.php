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
        $tableName = config('affiliate-network.database.tables.postback_deliveries', $tablePrefix . 'postback_deliveries');

        Schema::create($tableName, function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('endpoint_id')->index();
            $table->foreignUuid('leg_id')->index();
            $table->string('event_type', 32);
            $table->string('resolved_url', 2048);
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampsTz();

            $table->unique(['endpoint_id', 'leg_id', 'event_type']);
        });
    }
};
