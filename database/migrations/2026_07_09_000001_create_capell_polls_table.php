<?php

declare(strict_types=1);
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('capell_polls')) {
            return;
        }Schema::create('capell_polls', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('site_id')->index();
            $table->string('pageable_type');
            $table->unsignedBigInteger('pageable_id');
            $table->unsignedBigInteger('language_id')->index();
            $table->uuid('widget_instance_id');
            $table->string('question', 500);
            $table->string('results_visibility', 20)->default('after_vote');
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['site_id', 'pageable_type', 'pageable_id', 'language_id', 'widget_instance_id'], 'capell_polls_scope_unique');
            $table->index(['pageable_type', 'pageable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capell_polls');
    }
};
