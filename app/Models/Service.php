<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FlushesSiteCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use Auditable, FlushesSiteCache, HasFactory;

    protected $fillable = ['name', 'slug', 'content'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
