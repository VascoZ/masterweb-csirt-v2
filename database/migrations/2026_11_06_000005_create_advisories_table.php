<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advisories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('tlp', 20)->default('CLEAR')->index();
            $table->string('severity', 20)->index();
            $table->string('cve')->nullable();
            $table->text('affected_products')->nullable();
            $table->text('summary');
            $table->longText('body');
            $table->longText('recommendation')->nullable();
            $table->text('references')->nullable();
            $table->boolean('published')->default(false)->index();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advisories');
    }
};
