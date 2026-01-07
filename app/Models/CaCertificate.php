<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Helpers\UuidHelper;

class CaCertificate extends Model
{
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $connection = 'mysql_ca';

    protected $fillable = [
        'uuid', 
        'ca_type', 
        'cert_content', 
        'key_content',
        'serial_number',
        'common_name',
        'organization',
        'valid_from',
        'valid_to',
        'is_latest',
        'issuer_name',
        'issuer_serial',
        'family_id',
        'cert_path',
        'der_path',
        'bat_path',
        'mac_path',
        'linux_path',
        'last_synced_at',
        'download_count',
        'last_downloaded_at'
    ];

    protected $casts = [
        'valid_from' => 'datetime',
        'valid_to' => 'datetime',
        'is_latest' => 'boolean',
        'last_synced_at' => 'datetime',
        'last_downloaded_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidHelper::generate();
            }
        });
    }
}
