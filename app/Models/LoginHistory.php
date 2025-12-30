<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LoginHistory extends Model
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
        'ip_address',
        'user_agent',
        'device_type',
        'os',
        'browser',
        'city',
        'country',
        'country_code',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
