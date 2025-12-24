<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LegalPage extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = ['title', 'slug', 'is_active'];

    public $incrementing = false;
    protected $keyType = 'string';

    public function revisions()
    {
        return $this->hasMany(LegalPageRevision::class);
    }

    public function latestRevision()
    {
        return $this->hasOne(LegalPageRevision::class)->latestOfMany('created_at');
    }
    
    public function currentRevision() 
    {
         return $this->hasOne(LegalPageRevision::class)->where('is_active', true)->latest();
    }
}
