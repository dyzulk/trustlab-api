<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Ticket extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'ticket_number',
        'subject',
        'category',
        'priority',
        'status',
    ];

    /**
     * Get the user that owns the ticket.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the replies for the ticket.
     */
    public function replies()
    {
        return $this->hasMany(TicketReply::class);
    }

    /**
     * Helper to generate a unique ticket number.
     */
    public static function generateTicketNumber()
    {
        return 'TKT-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
