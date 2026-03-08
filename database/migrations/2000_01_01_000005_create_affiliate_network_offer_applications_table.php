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

        Schema::create($tablePrefix . 'offer_applications', function (Blueprint $table) use ($jsonType): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('offer_id');
            $table->foreignUuid('affiliate_id');

            $table->string('status')->default('pending');
            $table->text('reason')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->{$jsonType}('metadata')->nullable();

            $table->timestamps();

            $table->unique(['offer_id', 'affiliate_id']);
            $table->index(['affiliate_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        $tablePrefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');
        Schema::dropIfExists($tablePrefix . 'offer_applications');
    }
};
