<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Poetry/Prose (Light Posts) "Submit Your Writing" form's own inbox —
 * deliberately separate from resource_submissions (Inspirational
 * Resources), which the client confirmed never relates to Poetry/Prose.
 * See App\Modules\PoetryProse\Models\PoetryProseSubmission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poetry_prose_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('subject')->nullable();
            $table->string('category');
            $table->string('theme')->nullable();
            $table->text('message');
            $table->string('reference_url', 2048)->nullable();
            $table->string('status')->default('new');
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poetry_prose_submissions');
    }
};
