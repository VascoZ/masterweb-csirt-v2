<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FlushesSiteCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Guidance extends Model
{
    use Auditable, FlushesSiteCache, HasFactory;

    protected $fillable = ['name', 'file_name', 'slug', 'size', 'path'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
