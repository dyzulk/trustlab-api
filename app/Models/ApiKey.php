<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = \App\Helpers\UuidHelper::generate();
            }
        });
    }

    protected $fillable = [
        'user_id',
        'name',
        'key',
        'last_used_at',
        'is_active',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a unique API Key.
     */
    public static function generate()
    {
        do {
            $key = 'dvp_' . Str::random(56); // Prefix (4) + Random (56) = 60 chars
        } while (static::where('key', $key)->exists());

        return $key;
    }
}
