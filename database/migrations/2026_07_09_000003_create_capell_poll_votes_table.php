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
        if (TableName::existsAndIsOwned('capell_poll_votes', basename(__FILE__, '.php'))) {
            return;
        }Schema::create(TableName::resolve('capell_poll_votes'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('poll_id')->constrained(TableName::resolve('capell_polls'))->cascadeOnDelete();
            $table->foreignId('poll_option_id')->constrained(TableName::resolve('capell_poll_options'))->cascadeOnDelete();
            $table->char('voter_hash', 64);
            $table->dateTime('created_at');
            $table->unique(['poll_id', 'voter_hash']);
            $table->index(['poll_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TableName::resolve('capell_poll_votes'));
    }
};
