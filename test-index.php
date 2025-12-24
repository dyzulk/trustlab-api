<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';

use App\Models\User;
use App\Models\Certificate;
use App\Models\CaCertificate;
use Illuminate\Support\Facades\Auth;

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $user = User::where('email', 'admin@trustlab.com')->first();
    if (!$user) die("User not found\n");

    Auth::login($user);
    echo "Logged in as: " . Auth::id() . " (Role: " . Auth::user()->role . ")\n";

    $perPage = 10;
    
    // Mimic the index logic
    $certificates = Certificate::where('user_id', Auth::id())->latest()->paginate($perPage);
    
    $caReady = CaCertificate::where('ca_type', 'root')->exists() && 
               CaCertificate::where('ca_type', 'intermediate_2048')->exists() &&
               CaCertificate::where('ca_type', 'intermediate_4096')->exists();

    echo "Certs Count: " . $certificates->total() . "\n";
    echo "CA Ready: " . ($caReady ? 'YES' : 'NO') . "\n";
    echo "SUCCESS\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
