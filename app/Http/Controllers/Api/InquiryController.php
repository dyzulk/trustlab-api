<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Inquiry;
use App\Models\User;
use App\Notifications\NewInquiryNotification;
use App\Mail\InquiryReplyMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Notification;

class InquiryController extends Controller
{
    /**
     * Store a new inquiry (Public).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'category' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $inquiry = Inquiry::create($request->all());

        try {
            // Notify all admins
            $admins = User::where('role', 'admin')->get();
            Notification::send($admins, new NewInquiryNotification($inquiry));
        } catch (\Exception $e) {
            // Log the error but fail silently to the user, as the inquiry was saved.
            \Illuminate\Support\Facades\Log::error('Failed to send NewInquiryNotification: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'Your message has been sent successfully. We will get back to you soon!',
            'inquiry' => $inquiry
        ], 201);
    }

    /**
     * List all inquiries (Admin).
     */
    public function index(Request $request)
    {
        $search = $request->query('search');
        $status = $request->query('status');

        $query = Inquiry::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $query->where('status', $status);
        }

        $inquiries = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json($inquiries);
    }

    /**
     * Show a specific inquiry (Admin).
     */
    public function show(Inquiry $inquiry)
    {
        return response()->json($inquiry);
    }

    /**
     * Delete an inquiry (Admin).
     */
    public function destroy(Inquiry $inquiry)
    {
        $inquiry->delete();
        return response()->json(['message' => 'Inquiry deleted successfully.']);
    }

    /**
     * Reply to an inquiry (Admin).
     */
    public function reply(Request $request, Inquiry $inquiry)
    {
        $request->validate([
            'message' => 'required|string|max:5000',
        ]);

        try {
            // Send email using the support mailer
            Mail::mailer('support')->to($inquiry->email)->send(new \App\Mail\InquiryReplyMail($inquiry, $request->message));

            $inquiry->update([
                'status' => 'replied',
                'replied_at' => now(),
            ]);

            return response()->json(['message' => 'Reply sent successfully.']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send reply: ' . $e->getMessage()], 500);
        }
    }
}
