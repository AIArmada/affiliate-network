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

        commerce_schema_create_if_missing($tablePrefix . 'offers', function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('site_id');
            $table->foreignUuid('category_id')->nullable();

            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('terms')->nullable();
            $table->string('status')->default('pending');
            $table->string('visibility', 32)->default('public');

            $table->unsignedInteger('rate_base_bp')->nullable();
            $table->unsignedInteger('rate_fixed_minor')->nullable();
            $table->string('currency', 3)->nullable();
            $table->unsignedSmallInteger('cookie_days')->nullable();
            $table->{$jsonType}('volume_tiers')->nullable();
            $table->{$jsonType}('active_promotions')->nullable();

            $table->boolean('is_featured')->default(false);
            $table->boolean('requires_approval')->default(true);

            $table->string('landing_url')->nullable();
            $table->{$jsonType}('restrictions')->nullable();
            $table->{$jsonType}('metadata')->nullable();

            $table->string('external_program_id')->nullable()->index();
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_key')->nullable();
            $table->string('source_url')->nullable();
            $table->string('source_checksum', 64)->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            // synced: importer owns the rate block. manual: an operator
            // overrode rates; sync holds rates back (counts as locked).
            $table->string('rate_source', 16)->default('synced')->index();

            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->unique(['site_id', 'slug']);
            $table->index('status');
            $table->index('is_featured');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        $tablePrefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');
        Schema::dropIfExists($tablePrefix . 'offers');
    }
};
