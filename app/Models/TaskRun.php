<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat eksekusi tugas terjadwal / manual (Pengaturan Sistem).
 */
class TaskRun extends Model
{
    public $timestamps = false;

    protected $fillable = ['task', 'trigger', 'status', 'dry_run', 'output', 'user_id', 'started_at', 'finished_at'];

    protected function casts(): array
    {
        return [
            'dry_run' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault(['name' => 'Sistem']);
    }

    public function succeeded(): bool
    {
        return $this->status === 'success';
    }
}
