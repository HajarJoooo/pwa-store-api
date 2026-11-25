<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pwas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('developer_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();
            $table->string('name', 150)->index();
            $table->string('slug', 170)->unique();
            $table->string('short_description', 255);
            $table->text('description')->nullable();
            $table->string('manifest_url', 500);
            $table->string('start_url', 500);
            $table->string('icon_url', 500)->nullable();
            $table->json('screenshots')->nullable();
            $table->string('version', 30)->default('1.0.0');

            $table->enum('status', ['draft', 'pending', 'approved', 'rejected'])
                ->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->boolean('is_published')->default(false);

            $table->unsignedInteger('install_count')->default(0);
            $table->decimal('rating_average', 3, 2)->default(0);
            $table->unsignedInteger('review_count')->default(0);

            $table->softDeletes();
            $table->timestamps();

            // فهارس للتصفح العام والترتيب
            $table->index(['status', 'is_published']);
            $table->index(['category_id', 'status', 'is_published']);
            $table->index('install_count');
            $table->index('rating_average');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pwas');
    }
};
