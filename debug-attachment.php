<?php

use App\Models\TicketAttachment;
use Illuminate\Support\Facades\Storage;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// ID from your error message
$id = '019b8920-93ee-7052-9282-b6f6b320b741';

echo "--- Attachment Debugger ---\n";
echo "Target ID: $id\n\n";

// 1. Check Database
echo "[1] Checking Database Record...\n";
$attachment = TicketAttachment::find($id);

if (!$attachment) {
    echo "ERROR: Record not found in database.\n";
    exit(1);
}

echo "Found Record!\n";
echo "- ID: " . $attachment->id . "\n";
echo "- File Name: " . $attachment->file_name . "\n";
echo "- Stored Path: " . $attachment->file_path . "\n";
echo "- Created At: " . $attachment->created_at . "\n";

// 2. Check Disk
$path = $attachment->file_path;
$disk = 'r2-private';

echo "\n[2] Checking R2 Private Storage...\n";
echo "Disk: $disk\n";
echo "Path: $path\n";

try {
    if (filter_var($path, FILTER_VALIDATE_URL)) {
        echo "WARNING: Path is a full URL ($path). This logic is legacy/public.\n";
        exit;
    }

    $exists = Storage::disk($disk)->exists($path);
    
    if ($exists) {
        echo "STATUS: FILE EXISTS ✅\n";
        
        // Try reading first 100 bytes
        $content = Storage::disk($disk)->get($path);
        echo "Read Test: Success (" . strlen($content) . " bytes read)\n";
    } else {
        echo "STATUS: FILE NOT FOUND ❌\n";
        echo "Diagnosis: Upload failed or path mismatch.\n";
    }

} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}
