<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\UuidHelper;

class ActivityLog extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'action',
        'description',
        'ip_address',
        'user_agent'
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = UuidHelper::generate();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
