<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resource_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspirational_resource_id')->nullable()->constrained('inspirational_resources')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email')->index();
            $table->string('subject')->nullable();
            $table->string('category')->index();
            $table->text('message');
            $table->foreignId('related_album_id')->nullable()->constrained('albums')->nullOnDelete();
            $table->foreignId('related_track_id')->nullable()->constrained('tracks')->nullOnDelete();
            $table->string('status')->default('new')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resource_submissions');
    }
};
