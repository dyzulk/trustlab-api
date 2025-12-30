<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LegalPageRevision extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'legal_page_id', 
        'content', 
        'major', 'minor', 'patch', 
        'status', 'published_at',
        'change_log', 'is_active', 'created_by'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    protected $appends = ['version'];

    public function getVersionAttribute()
    {
        return "{$this->major}.{$this->minor}.{$this->patch}";
    }

    public function legalPage()
    {
        return $this->belongsTo(LegalPage::class);
    }
}
