<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketAttachment extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'ticket_reply_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
    ];

    public function reply()
    {
        return $this->belongsTo(TicketReply::class, 'ticket_reply_id');
    }
}
