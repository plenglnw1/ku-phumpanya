<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('search_history_id')->constrained()->cascadeOnDelete();

            // A module is addressed the same way the frontend links to it:
            // /topic?h={search_history_id}&p={phase_index}&c={card_index}.
            $table->unsignedSmallInteger('phase_index');
            $table->unsignedSmallInteger('card_index');

            // Kept as a timestamp rather than a boolean: un-completing deletes the
            // row, so a present row always means done, and the time it happened is
            // a behavioural signal the recommender can read later.
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->unique(['user_id', 'search_history_id', 'phase_index', 'card_index'], 'learning_progress_module_unique');
            $table->index(['user_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_progress');
    }
};
