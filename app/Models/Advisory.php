<?php

namespace App\Models;

use App\Enums\Severity;
use App\Enums\Tlp;
use App\Models\Concerns\Auditable;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Advisory extends Model
{
    use Auditable, HasFactory, Sluggable;

    protected $fillable = [
        'title', 'slug', 'tlp', 'severity', 'cve', 'affected_products', 'summary',
        'body', 'recommendation', 'references', 'published', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'tlp' => Tlp::class,
            'severity' => Severity::class,
            'published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Advisory $advisory) {
            $advisory->code ??= static::nextCode();
        });

        static::saving(function (Advisory $advisory) {
            if ($advisory->published && ! $advisory->published_at) {
                $advisory->published_at = now();
            }
        });
    }

    /**
     * Kode berurutan per tahun, mis. ADV-2026-0001.
     */
    public static function nextCode(): string
    {
        $prefix = sprintf('%s-%s-', config('csirt.advisory_prefix'), now()->format('Y'));
        $last = static::where('code', 'like', $prefix . '%')->orderByDesc('code')->value('code');
        $sequence = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Hanya advisory terbit dengan TLP:CLEAR yang boleh tampil ke publik.
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('published', true)->where('tlp', Tlp::Clear->value);
    }

    public function isPubliclyVisible(): bool
    {
        return $this->published && $this->tlp->isPublic();
    }

    public function cveList(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,;]+/', (string) $this->cve))));
    }

    public function referenceList(): array
    {
        return array_values(array_filter(
            array_map('trim', preg_split('/\R/', (string) $this->references)),
            fn ($url) => preg_match('#^https?://#i', $url)
        ));
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault(['name' => '-']);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function sluggable(): array
    {
        return ['slug' => ['source' => 'title']];
    }

    public function auditLabel(): string
    {
        return $this->code . ' ' . $this->title;
    }
}
