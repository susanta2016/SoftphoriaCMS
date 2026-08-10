<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poetry_prose_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poetry_prose_id')->constrained('poetry_prose')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('snapshot_json');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['poetry_prose_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poetry_prose_revisions');
    }
};
