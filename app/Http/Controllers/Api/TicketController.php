<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketReply;
use Illuminate\Http\Request;
use App\Models\User;
use App\Notifications\NewTicketNotification;
use App\Notifications\TicketReplyNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    /**
     * Display a listing of tickets.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Ticket::with(['user:id,first_name,last_name,email,avatar', 'replies.attachments']);

        // Only show all tickets if user is admin AND explicitly asks for all
        if ($user->isAdmin() && $request->has('all')) {
            // No additional where clause needed
        } else {
            // Everyone else (including admins in personal view) only sees their own
            $query->where('user_id', $user->id);
        }

        $tickets = $query->latest()->paginate(10);

        return response()->json($tickets);
    }

    /**
     * Store a newly created ticket.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'category' => 'required|string',
            'priority' => 'required|in:low,medium,high',
            'message' => 'required|string',
            'attachments' => 'array|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,zip,txt|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            return DB::transaction(function () use ($request, $user) {
                $ticket = Ticket::create([
                    'user_id' => $user->id,
                    'ticket_number' => Ticket::generateTicketNumber(),
                    'subject' => $request->subject,
                    'category' => $request->category,
                    'priority' => $request->priority,
                    'status' => 'open',
                ]);

                $reply = TicketReply::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'message' => $request->message,
                ]);

                // Handle Attachments
                if ($request->hasFile('attachments')) {
                    foreach ($request->file('attachments') as $file) {
                        $path = $file->store('ticket-attachments', 'public');
                        TicketAttachment::create([
                            'ticket_reply_id' => $reply->id,
                            'file_name' => $file->getClientOriginalName(),
                            'file_path' => $path,
                            'file_type' => $file->getClientMimeType(),
                            'file_size' => $file->getSize(),
                        ]);
                    }
                }

                // Notify Admins
                $admins = User::where('role', 'admin')->get();
                Notification::send($admins, new NewTicketNotification($ticket->load('user')));

                return response()->json([
                    'message' => 'Ticket created successfully',
                    'ticket' => $ticket->load('replies')
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to create ticket', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified ticket with replies.
     */
    public function show(Request $request, $id)
    {
        $user = $request->user();
        $ticket = Ticket::with(['user:id,first_name,last_name,email,avatar', 'replies.user:id,first_name,last_name,avatar', 'replies.attachments'])->findOrFail($id);

        if (!$user->isAdmin() && $ticket->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($ticket);
    }

    /**
     * Add a reply to the ticket.
     */
    public function reply(Request $request, $id)
    {
        $user = $request->user();
        $ticket = Ticket::findOrFail($id);

        if (!$user->isAdmin() && $ticket->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        if ($ticket->status === 'closed') {
            return response()->json(['message' => 'Cannot reply to a closed ticket'], 422);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'attachments' => 'array|max:5',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx,zip,txt|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $reply = TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $request->message,
        ]);

        // Handle Attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('ticket-attachments', 'public');
                TicketAttachment::create([
                    'ticket_reply_id' => $reply->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_type' => $file->getClientMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        // Update ticket status
        if ($user->isAdmin()) {
            $ticket->update(['status' => 'answered']);
            // Notify Customer
            $ticketUser = $ticket->user;
            $ticketUser->notify(new TicketReplyNotification($ticket, $reply, true));
        } else {
            $ticket->update(['status' => 'open']);
            // Notify Admins
            $admins = User::where('role', 'admin')->get();
            Notification::send($admins, new TicketReplyNotification($ticket, $reply, false));
        }

        return response()->json([
            'message' => 'Reply added successfully',
            'reply' => $reply->load(['user:id,first_name,last_name,avatar', 'attachments'])
        ], 201);
    }

    /**
     * Close the ticket.
     */
    public function close(Request $request, $id)
    {
        $user = $request->user();
        $ticket = Ticket::findOrFail($id);

        if (!$user->isAdmin() && $ticket->user_id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $ticket->update(['status' => 'closed']);

        return response()->json([
            'message' => 'Ticket closed successfully',
            'ticket' => $ticket
        ]);
    }
}
