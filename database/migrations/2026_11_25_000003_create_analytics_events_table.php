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
        if (! Schema::hasTable('analytics_events')) {
            Schema::create('analytics_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('pwa_id')
                    ->nullable()
                    ->constrained('pwas')
                    ->nullOnDelete();
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->string('event_type', 50); // view, detail_visit, install_clicked, installed_confirmed, external_opened
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent()->index();

                $table->index(['pwa_id', 'event_type']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};

