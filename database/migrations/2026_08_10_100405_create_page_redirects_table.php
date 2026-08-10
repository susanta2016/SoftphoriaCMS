<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('old_path')->unique();
            $table->foreignId('page_id')->constrained()->cascadeOnDelete();
            $table->string('redirect_type')->default('301');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_redirects');
    }
};
