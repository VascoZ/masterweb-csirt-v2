<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengaturan yang dapat diubah superadmin dari dashboard (Pengaturan Sistem).
 * Nilai di sini menimpa default dari .env/config; hanya kunci pada registry
 * App\Support\Settings yang pernah dibaca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('task_runs', function (Blueprint $table) {
            $table->id();
            $table->string('task', 60)->index();
            $table->string('trigger', 20);            // schedule | manual
            $table->string('status', 20);             // success | failed
            $table->boolean('dry_run')->default(false);
            $table->text('output')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_runs');
        Schema::dropIfExists('settings');
    }
};
