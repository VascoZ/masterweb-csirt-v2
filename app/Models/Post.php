<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FlushesSiteCache;
use Cviebrock\EloquentSluggable\Sluggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Post extends Model
{
    use Auditable, FlushesSiteCache, HasFactory, Sluggable;

    protected $fillable = ['title', 'slug', 'excerpt', 'body', 'category_id', 'image', 'published', 'published_at'];

    protected $with = ['category', 'author'];

    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('published', true);
    }

    public function scopeFilter(Builder $query, array $filters): void
    {
        // Dikelompokkan agar orWhere tidak "membocorkan" post yang belum dipublikasi.
        $query->when($filters['search'] ?? false, function (Builder $query, string $search) {
            $query->where(function (Builder $query) use ($search) {
                $query->where('title', 'like', '%' . $search . '%')
                    ->orWhere('body', 'like', '%' . $search . '%');
            });
        });

        $query->when($filters['category'] ?? false, function (Builder $query, string $category) {
            $query->whereHas('category', fn (Builder $query) => $query->where('slug', $category));
        });

        $query->when($filters['author'] ?? false, function (Builder $query, string $author) {
            $query->whereHas('author', fn (Builder $query) => $query->where('username', $author));
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withDefault(['name' => 'Tanpa Kategori', 'slug' => '']);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withDefault(['name' => 'Anonim', 'username' => '']);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function sluggable(): array
    {
        return ['slug' => ['source' => 'title']];
    }
}
