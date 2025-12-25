<?php

use App\Models\User;
use App\Models\Ticket;
use App\Notifications\NewTicketNotification;
use Illuminate\Support\Facades\Notification;

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = User::where('role', 'admin')->first();
$ticket = Ticket::first();

if (!$user) {
    echo "No admin user found.\n";
    exit(1);
}

if (!$ticket) {
    echo "No ticket found. Creating a dummy ticket...\n";
    // Create a dummy ticket if needed, or just exit
     $ticket = new Ticket();
     $ticket->id = 1;
     $ticket->ticket_number = 'TEST-123';
     $ticket->subject = 'Test Subject';
     $ticket->user = $user; // Mock relation
}

echo "Sending notification to: " . $user->email . "\n";

try {
    $user->notify(new NewTicketNotification($ticket));
    echo "Notification sent successfully via notify()\n";
} catch (\Exception $e) {
    echo "Error sending notification: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

// Check database
$latestNotification = $user->notifications()->latest()->first();
if ($latestNotification) {
    echo "Latest Notification in DB: " . $latestNotification->type . " - " . ($latestNotification->data['message'] ?? 'No message') . "\n";
} else {
    echo "No notifications found in DB for this user.\n";
}
