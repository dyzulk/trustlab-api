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

    protected $hidden = [
        'expired_notification_sent_at',
    ];

    protected $appends = [
        'ssl_status',
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

    public function getSslStatusAttribute()
    {
        if ($this->valid_to && $this->valid_to->isPast()) {
            return 'EXPIRED';
        }
        return 'ACTIVE';
    }

    public function toArray()
    {
        $array = parent::toArray();
        $ordered = [];
        
        // Define the preferred order of keys
        $paramOrder = [
            'uuid', 'user_id', 'common_name', 'organization', 'locality', 
            'state', 'country', 'san', 'status', 'key_bits', 'serial_number',
            'ssl_status', // <--- Inserted here (before content)
            'cert_content', 'key_content', 'csr_content',
            'valid_from', 'valid_to', 'created_at', 'updated_at'
        ];

        // Reconstruct query based on paramOrder
        foreach ($paramOrder as $key) {
            if (array_key_exists($key, $array)) {
                $ordered[$key] = $array[$key];
                unset($array[$key]);
            }
        }

        // Append any remaining keys (that weren't in our explicit list)
        return array_merge($ordered, $array);
    }
}
