<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pwas', function (Blueprint $table): void {
            $table->index(['status', 'is_published', 'created_at'], 'pwas_published_created_at_idx');
            $table->index(['status', 'is_published', 'install_count'], 'pwas_published_install_count_idx');
            $table->index(['status', 'is_published', 'rating_average', 'review_count'], 'pwas_published_rating_idx');
        });

        Schema::table('reviews', function (Blueprint $table): void {
            $table->index(['pwa_id', 'is_approved', 'helpful_count'], 'reviews_pwa_approved_helpful_idx');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropIndex('reviews_pwa_approved_helpful_idx');
        });

        Schema::table('pwas', function (Blueprint $table): void {
            $table->dropIndex('pwas_published_created_at_idx');
            $table->dropIndex('pwas_published_install_count_idx');
            $table->dropIndex('pwas_published_rating_idx');
        });
    }
};
