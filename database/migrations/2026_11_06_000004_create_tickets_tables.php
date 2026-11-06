<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number', 40)->unique();
            $table->string('reporter_name');
            $table->string('reporter_email');
            $table->string('reporter_phone', 30)->nullable();
            $table->string('reporter_organization')->nullable();
            $table->string('category', 40)->index();
            $table->timestamp('incident_at')->nullable();
            $table->string('affected_asset', 500)->nullable();
            $table->text('description');
            $table->string('tlp', 20)->default('AMBER');
            $table->string('status', 20)->default('new')->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_ip', 45)->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->timestamps();
        });

        Schema::create('ticket_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_updates');
        Schema::dropIfExists('ticket_attachments');
        Schema::dropIfExists('tickets');
    }
};
