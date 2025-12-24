<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\UuidHelper;

class Certificate extends Model
{
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid', 'user_id', 'common_name', 'organization', 'locality',
        'state', 'country', 'san', 'key_bits', 'serial_number',
        'cert_content', 'key_content', 'csr_content',
        'valid_from', 'valid_to', 'expired_notification_sent_at'
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'expired_notification_sent_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidHelper::generate();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
