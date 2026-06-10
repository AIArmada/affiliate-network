<?php

declare(strict_types=1);

use AIArmada\AffiliateNetwork\Enums\OfferStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tablePrefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');

        Schema::table($tablePrefix . 'offers', function (Blueprint $table): void {
            $table->string('visibility', 32)->default('public')->after('status');
            $table->timestampTz('published_at')->nullable()->after('ends_at');
            $table->timestampTz('archived_at')->nullable()->after('published_at');
        });

        $tableName = $tablePrefix . 'offers';

        DB::table($tableName)->update([
            'visibility' => DB::raw("CASE WHEN is_public = true THEN 'public' ELSE 'private' END"),
        ]);

        DB::table($tableName)
            ->where('status', 'active')
            ->update([
                'status' => OfferStatus::Published->value,
                'published_at' => DB::raw('updated_at'),
            ]);

        DB::table($tableName)
            ->whereIn('status', ['expired', 'rejected'])
            ->update([
                'status' => OfferStatus::Archived->value,
                'archived_at' => DB::raw('updated_at'),
            ]);

        DB::table($tableName)
            ->where('status', 'pending')
            ->update(['status' => OfferStatus::Draft->value]);

        DB::table($tableName)
            ->where('status', 'paused')
            ->update(['status' => OfferStatus::Published->value]);

        DB::statement('DROP INDEX IF EXISTS "' . $tableName . '_is_public_index"');

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn('is_public');
        });
    }

    public function down(): void
    {
        $tablePrefix = config('affiliate-network.database.table_prefix', 'affiliate_network_');
        $tableName = $tablePrefix . 'offers';

        Schema::table($tableName, function (Blueprint $table): void {
            $table->boolean('is_public')->default(true);
            $table->index('is_public');
        });

        DB::table($tableName)->update([
            'is_public' => DB::raw("CASE WHEN visibility = 'public' THEN 1 ELSE 0 END"),
        ]);

        DB::table($tableName)
            ->where('status', OfferStatus::Published->value)
            ->update(['status' => 'active']);

        DB::table($tableName)
            ->where('status', OfferStatus::Archived->value)
            ->update(['status' => 'expired']);

        Schema::table($tableName, function (Blueprint $table): void {
            $table->dropColumn(['visibility', 'published_at', 'archived_at']);
        });
    }
};
