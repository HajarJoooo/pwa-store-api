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
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
                $table->string('title', 150);
                $table->text('content');
                $table->string('type', 50)->default('system'); // system, review_approved, review_rejected, developer_verified, app_update
                $table->string('action_url', 500)->nullable();
                $table->boolean('is_read')->default(false)->index();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'is_read']);
                $table->index('created_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

