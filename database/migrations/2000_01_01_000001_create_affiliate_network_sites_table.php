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

        Schema::create($tablePrefix . 'sites', function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->nullableMorphs('owner');

            $table->string('name');
            $table->string('domain')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->string('verification_method')->nullable();
            $table->string('verification_token')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->{$jsonType}('settings')->nullable();
            $table->{$jsonType}('metadata')->nullable();

            $table->timestamps();

            $table->index(['owner_type', 'owner_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        $tablePrefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');
        Schema::dropIfExists($tablePrefix . 'sites');
    }
};
