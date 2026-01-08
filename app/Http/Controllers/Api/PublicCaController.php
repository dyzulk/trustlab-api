<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaCertificate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicCaController extends Controller
{
    /**
     * Display a listing of public CA certificates.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $caTypes = ['root', 'intermediate_2048', 'intermediate_4096'];
        
        $certificates = CaCertificate::whereIn('ca_type', $caTypes)
            ->where('is_latest', true)
            ->get(['common_name', 'ca_type', 'serial_number', 'valid_to', 'cert_content', 'cert_path', 'der_path', 'bat_path', 'mac_path', 'linux_path', 'last_synced_at', 'family_id'])
            ->map(function ($cert) {
                return [
                    'name' => $cert->common_name,
                    'type' => $cert->ca_type,
                    'serial' => $cert->serial_number,
                    'family_id' => $cert->family_id,
                    'expires_at' => $cert->valid_to->toIso8601String(),
                    'last_synced_at' => $cert->last_synced_at ? $cert->last_synced_at->toIso8601String() : null,
                    'cdn_url' => $cert->cert_path ? Storage::disk('r2-public')->url($cert->cert_path) : null,
                    'der_cdn_url' => $cert->der_path ? Storage::disk('r2-public')->url($cert->der_path) : null,
                    'bat_cdn_url' => $cert->bat_path ? Storage::disk('r2-public')->url($cert->bat_path) : null,
                    'mac_cdn_url' => $cert->mac_path ? Storage::disk('r2-public')->url($cert->mac_path) : null,
                    'linux_cdn_url' => $cert->linux_path ? Storage::disk('r2-public')->url($cert->linux_path) : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $certificates,
            'bundle_urls' => [
                'linux' => Storage::disk('r2-public')->url('ca/bundles/trustlab-all.sh'),
                'windows' => Storage::disk('r2-public')->url('ca/bundles/trustlab-all.bat'),
                'macos' => Storage::disk('r2-public')->url('ca/bundles/trustlab-all.mobileconfig'),
            ]
        ]);
    }

    /**
     * Download certificate in various formats.
     */
    public function download(Request $request, $serial)
    {
        $cert = CaCertificate::where('serial_number', $serial)->firstOrFail();
        $cert->increment('download_count');
        $cert->update(['last_downloaded_at' => now()]);
        $format = $request->query('format', 'pem');
        
        // Redirect to CDN if path exists and format is PEM
        if ($format === 'pem' && $cert->cert_path) {
            return redirect()->away(Storage::disk('r2-public')->url($cert->cert_path));
        }

        if ($format === 'der') {
            // Redirect to CDN if path exists and format is DER
            if ($cert->der_path) {
                return redirect()->away(Storage::disk('r2-public')->url($cert->der_path));
            }

            // Convert PEM to DER (Base64 decode the body)
            $pem = $cert->cert_content;
            $lines = explode("\n", trim($pem));
            $payload = '';
            foreach ($lines as $line) {
                if (!str_starts_with($line, '-----')) {
                    $payload .= trim($line);
                }
            }
            $der = base64_decode($payload);
            
            return response($der)
                ->header('Content-Type', 'application/x-x509-ca-cert')
                ->header('Content-Disposition', 'attachment; filename="' . $cert->common_name . '.der"');
        }

        // Default PEM
        return response($cert->cert_content)
            ->header('Content-Type', 'application/x-pem-file')
            ->header('Content-Disposition', 'attachment; filename="' . $cert->common_name . '.crt"');
    }

    /**
     * Download Windows One-Click Installer (.bat)
     */
    public function downloadWindows($serial)
    {
        $cert = CaCertificate::where('serial_number', $serial)->firstOrFail();
        $cert->increment('download_count');
        $cert->update(['last_downloaded_at' => now()]);

        if ($cert->bat_path) {
            return redirect()->away(Storage::disk('r2-public')->url($cert->bat_path));
        }

        // Fallback: Generate via CaInstallerService
        $installerService = app(\App\Services\CaInstallerService::class);
        $content = $installerService->generateWindowsInstaller($cert, false);
        $filename = 'install-trustlab-' . \Illuminate\Support\Str::slug($cert->common_name) . '.bat';

        return response($content, 200, [
            'Content-Type' => 'application/x-bat',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Download macOS Configuration Profile (.mobileconfig)
     */
    public function downloadMac($serial)
    {
        $cert = CaCertificate::where('serial_number', $serial)->firstOrFail();
        $cert->increment('download_count');
        $cert->update(['last_downloaded_at' => now()]);

        if ($cert->mac_path) {
            return redirect()->away(Storage::disk('r2-public')->url($cert->mac_path));
        }

        // Fallback: Generate via CaInstallerService
        $installerService = app(\App\Services\CaInstallerService::class);
        $content = $installerService->generateMacInstaller($cert);
        $filename = 'trustlab-' . \Illuminate\Support\Str::slug($cert->common_name) . '.mobileconfig';

        return response($content, 200, [
            'Content-Type' => 'application/x-apple-aspen-config',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Download Linux Installer (.sh)
     */
    public function downloadLinux($serial)
    {
        $cert = CaCertificate::where('serial_number', $serial)->firstOrFail();
        $cert->increment('download_count');
        $cert->update(['last_downloaded_at' => now()]);

        if ($cert->linux_path) {
            return redirect()->away(Storage::disk('r2-public')->url($cert->linux_path));
        }

        // Fallback: Generate via CaInstallerService
        $installerService = app(\App\Services\CaInstallerService::class);
        $content = $installerService->generateLinuxInstaller($cert, false);
        $filename = 'install-trustlab-' . \Illuminate\Support\Str::slug($cert->common_name) . '.sh';

        return response($content, 200, [
            'Content-Type' => 'application/x-sh',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
