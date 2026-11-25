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
        Schema::create('review_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255);
            $table->text('details')->nullable();
            $table->string('status', 30)->default('pending')->index();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::table('pwas', function (Blueprint $table): void {
            $table->unsignedBigInteger('views_count')->default(0)->after('install_count');
            $table->unsignedBigInteger('clicks_count')->default(0)->after('views_count');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pwas', function (Blueprint $table): void {
            $table->dropColumn(['views_count', 'clicks_count']);
        });

        Schema::dropIfExists('review_reports');
    }
};

