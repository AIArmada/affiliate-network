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

        Schema::create($tablePrefix . 'offer_creatives', function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('offer_id');

            $table->string('type')->default('banner');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('url')->nullable();
            $table->string('file_path')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->string('alt_text')->nullable();
            $table->text('html_code')->nullable();

            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->{$jsonType}('metadata')->nullable();

            $table->timestamps();

            $table->index(['offer_id', 'is_active']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        $tablePrefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');
        Schema::dropIfExists($tablePrefix . 'offer_creatives');
    }
};
