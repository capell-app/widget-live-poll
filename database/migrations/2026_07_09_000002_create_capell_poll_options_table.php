<?php

declare(strict_types=1);
use Capell\Core\Support\TableName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (TableName::existsAndIsOwned('capell_poll_options', basename(__FILE__, '.php'))) {
            return;
        }Schema::create(TableName::resolve('capell_poll_options'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('poll_id')->constrained(TableName::resolve('capell_polls'))->cascadeOnDelete();
            $table->uuid('stable_id');
            $table->string('label', 200);
            $table->unsignedSmallInteger('position')->default(0);
            $table->unsignedBigInteger('votes_count')->default(0);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->unique(['poll_id', 'stable_id']);
            $table->index(['poll_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TableName::resolve('capell_poll_options'));
    }
};
