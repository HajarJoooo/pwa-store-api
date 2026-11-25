<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pwas', function (Blueprint $table): void {
            if (! Schema::hasColumn('pwas', 'pricing_type')) {
                $table->string('pricing_type', 30)->default('free')->after('version');
            }
            if (! Schema::hasColumn('pwas', 'price')) {
                $table->decimal('price', 8, 2)->default(0)->after('pricing_type');
            }
            if (! Schema::hasColumn('pwas', 'privacy_policy_url')) {
                $table->string('privacy_policy_url', 500)->nullable()->after('price');
            }
            if (! Schema::hasColumn('pwas', 'is_verified')) {
                $table->boolean('is_verified')->default(false)->after('privacy_policy_url');
            }
            if (! Schema::hasColumn('pwas', 'compatibility')) {
                $table->json('compatibility')->nullable()->after('is_verified');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pwas', function (Blueprint $table): void {
            $columns = [];
            foreach (['pricing_type', 'price', 'privacy_policy_url', 'is_verified', 'compatibility'] as $col) {
                if (Schema::hasColumn('pwas', $col)) {
                    $columns[] = $col;
                }
            }
            if (! empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};

