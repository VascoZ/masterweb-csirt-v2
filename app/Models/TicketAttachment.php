<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TicketAttachment extends Model
{
    protected $fillable = ['original_name', 'path', 'mime_type', 'size', 'sha256'];

    protected static function booted(): void
    {
        // Bukti disimpan di disk privat (bukan public) dan ikut terhapus bersama tiket.
        static::deleted(fn (TicketAttachment $attachment) => Storage::disk('local')->delete($attachment->path));
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
}
