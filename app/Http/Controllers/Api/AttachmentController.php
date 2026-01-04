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
            // Paranoid Auth Check
            $user = $request->user();
            if (!$user) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }

            if (!$attachment->reply) {
                return response()->json(['error' => 'Orphaned Attachment (No Reply)'], 404);
            }
            if (!$attachment->reply->ticket) {
                return response()->json(['error' => 'Orphaned Attachment (No Ticket)'], 404);
            }

            $ticket = $attachment->reply->ticket;

            if ($ticket->user_id !== $user->id && !$user->isAdminOrOwner()) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $path = $attachment->file_path;
            
            // Legacy URL handling
            if (filter_var($path, FILTER_VALIDATE_URL)) {
                 return redirect($path);
            }

            $disk = 'r2-private';
            
            // Use manual file retrieval to avoid header issues with Storage::download
            if (!Storage::disk($disk)->exists($path)) {
                return response()->json(['error' => 'File not found on storage'], 404);
            }

            $mimeType = Storage::disk($disk)->mimeType($path) ?? 'application/octet-stream';
            $size = Storage::disk($disk)->size($path);
            $fileName = $attachment->file_name ?? basename($path);

            return response()->stream(function() use ($disk, $path) {
                $stream = Storage::disk($disk)->readStream($path);
                fpassthru($stream);
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }, 200, [
                'Content-Type' => $mimeType,
                'Content-Length' => $size,
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]);

        } catch (\Exception $e) {
            \Log::error("Stream Download Error: " . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => 'Server Error', 'message' => $e->getMessage()], 500);
        }
    }
    }
}
