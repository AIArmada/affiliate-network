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
        $jsonType = config('affiliate-network.database.json_column_type', 'json');

        Schema::create($tablePrefix . 'offer_links', function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('offer_id');
            $table->foreignUuid('affiliate_id');
            $table->foreignUuid('site_id')->nullable();

            $table->string('code', 32)->unique();
            $table->string('target_url');
            $table->string('custom_parameters')->nullable();
            $table->string('sub_id')->nullable();
            $table->string('sub_id_2')->nullable();
            $table->string('sub_id_3')->nullable();

            $table->unsignedBigInteger('clicks')->default(0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->unsignedBigInteger('revenue')->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();

            $table->timestamps();

            $table->index(['affiliate_id', 'is_active']);
            $table->index(['offer_id', 'affiliate_id']);
            $table->index('site_id');
        });
    }

    public function down(): void
    {
        $tablePrefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');
        Schema::dropIfExists($tablePrefix . 'offer_links');
    }
};
