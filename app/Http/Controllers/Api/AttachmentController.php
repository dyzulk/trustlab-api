<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AttachmentController extends Controller
{
    /**
     * Download a private attachment.
     */
    public function download(Request $request, TicketAttachment $attachment)
    {
        try {
            $user = $request->user();

            // 1. Authorization Logic
            $attachment->load(['reply.ticket']);
            $ticket = $attachment->reply->ticket;

            if ($ticket->user_id !== $user->id && !$user->isAdminOrOwner()) {
                abort(403, 'Unauthorized access to this attachment.');
            }

            // 2. Fetch File
            $path = $attachment->file_path;
            
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                 return redirect($path);
            }

            $disk = 'r2-private';

            if (!Storage::disk($disk)->exists($path)) {
                \Log::error("Attachment 404: Path [$path] not found on disk [$disk]");
                abort(404, 'File not found on secure storage.');
            }

            return Storage::disk($disk)->download($path, $attachment->file_name);

        } catch (\Exception $e) {
            \Log::error("Attachment Download Error: " . $e->getMessage(), [
                'attachment_id' => $attachment->id,
                'path' => $attachment->file_path ?? 'unknown',
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Server Error', 
                'message' => $e->getMessage(),
                'file_path' => $attachment->file_path ?? 'unknown'
            ], 500);
        }
    }
}
