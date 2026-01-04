<?php

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "--- R2 Connectivity Debug ---\n";

// 1. Check Config
echo "\n[1] Checking Configuration:\n";
$publicBucket = Config::get('filesystems.disks.r2-public.bucket');
$privateBucket = Config::get('filesystems.disks.r2-private.bucket');
$privateKey = Config::get('filesystems.disks.r2-private.key');

echo "R2 Public Bucket: " . ($publicBucket ? "SET ($publicBucket)" : "MISSING") . "\n";
echo "R2 Private Bucket: " . ($privateBucket ? "SET ($privateBucket)" : "MISSING") . "\n";
echo "R2 Key Present: " . ($privateKey ? "YES" : "NO") . "\n";

if (!$privateBucket) {
    echo "ERROR: R2_PRIVATE_BUCKET is not set in config. Did you run 'php artisan config:clear'?\n";
    exit(1);
}

// 2. Test Private Disk
echo "\n[2] Testing 'r2-private' Disk Write/Read:\n";
try {
    $testFile = 'debug-connectivity-' . time() . '.txt';
    echo "Attempting to write $testFile ... ";
    
    $result = Storage::disk('r2-private')->put($testFile, 'Connectivity Test ' . now());
    
    if ($result) {
        echo "SUCCESS\n";
        echo "Attempting to delete ... ";
        Storage::disk('r2-private')->delete($testFile);
        echo "SUCCESS\n";
    } else {
        echo "FAILED (Write returned false)\n";
    }
} catch (\Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
