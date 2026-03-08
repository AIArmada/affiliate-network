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

        Schema::create($tablePrefix . 'offers', function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id');
            $table->foreignUuid('category_id')->nullable();

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('terms')->nullable();
            $table->string('status')->default('pending');

            $table->string('commission_type')->default('percentage');
            $table->unsignedInteger('commission_rate')->default(1000);
            $table->string('currency', 3)->nullable();
            $table->unsignedSmallInteger('cookie_days')->nullable();

            $table->boolean('is_featured')->default(false);
            $table->boolean('is_public')->default(true);
            $table->boolean('requires_approval')->default(true);

            $table->string('landing_url')->nullable();
            $table->{$jsonType}('restrictions')->nullable();
            $table->{$jsonType}('metadata')->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['site_id', 'slug']);
            $table->index('status');
            $table->index('is_featured');
            $table->index('is_public');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        $tablePrefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');
        Schema::dropIfExists($tablePrefix . 'offers');
    }
};
