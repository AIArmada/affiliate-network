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

        Schema::create($tablePrefix . 'conversion_ledger', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('order_id')->nullable();
            $table->foreignUuid('link_id');
            $table->foreignUuid('offer_id');
            $table->foreignUuid('affiliate_id')->nullable();
            $table->foreignUuid('site_id')->nullable();
            $table->nullableMorphs('owner');
            $table->string('link_owner_type')->nullable();
            $table->string('link_owner_id')->nullable();

            $table->bigInteger('revenue')->default(0);
            $table->string('currency', 3)->default('USD');

            $table->string('reference')->unique();
            $table->timestampTz('converted_at');

            $table->timestampsTz();

            $table->index(['order_id', 'link_id']);
            $table->index('affiliate_id');
            $table->index('currency');
        });
    }
};
