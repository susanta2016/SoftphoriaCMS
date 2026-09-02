<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw storage for the public Contact Us form (App\Http\Controllers\
 * ContactController) — always a private administrative record, never
 * rendered at a public URL. `phone` is the only optional field (the form
 * requires name/email/message). No status/workflow column: unlike
 * ResourceSubmission this is a plain inbox, not a review queue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone')->nullable();
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_submissions');
    }
};
