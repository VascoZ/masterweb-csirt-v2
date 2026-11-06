<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\FlushesSiteCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Footer extends Model
{
    use Auditable, FlushesSiteCache, HasFactory;

    protected $fillable = ['name', 'slug', 'address', 'telephone', 'email', 'maps'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
