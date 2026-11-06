<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FlushesSiteCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImageProperty extends Model
{
    use Auditable, FlushesSiteCache, HasFactory;

    protected $fillable = ['property', 'name', 'slug', 'image'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
