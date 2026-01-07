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

        $store = $cert->ca_type === 'root' ? 'Root' : 'CA';
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $cert->common_name);
        
        // Convert CRLF to ensure batch file works
        $certContent = str_replace("\n", "\r\n", str_replace("\r\n", "\n", $cert->cert_content));

        $script = "@echo off\r\n";
        $script .= "echo Installing " . $cert->common_name . "...\r\n";
        $script .= "echo Please allow the security prompt to trust this certificate.\r\n";
        $script .= "set \"CERT_FILE=%TEMP%\\" . $filename . ".crt\"\r\n";
        $script .= "((\r\n";
        foreach(explode("\r\n", $certContent) as $line) {
            if(!empty($line)) $script .= "echo " . $line . "\r\n";
        }
        $script .= ")) > \"%CERT_FILE%\"\r\n";
        $script .= "certutil -addstore -f \"" . $store . "\" \"%CERT_FILE%\"\r\n";
        $script .= "del \"%CERT_FILE%\"\r\n";
        $script .= "pause\r\n";

        return response($script)
            ->header('Content-Type', 'application/x-bat')
            ->header('Content-Disposition', 'attachment; filename="install-' . $filename . '.bat"');
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
        
        // Extract Base64 payload
        $pem = $cert->cert_content;
        $lines = explode("\n", trim($pem));
        $payload = '';
        foreach ($lines as $line) {
            if (!str_starts_with($line, '-----')) {
                $payload .= trim($line);
            }
        }

        $uuid = \Illuminate\Support\Str::uuid();
        $identifier = 'com.trustlab.cert.' . $serial;
        $name = $cert->common_name;
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>PayloadContent</key>
    <array>
        <dict>
            <key>PayloadCertificateFileName</key>
            <string>' . $name . '.cer</string>
            <key>PayloadContent</key>
            <data>
            ' . $payload . '
            </data>
            <key>PayloadDescription</key>
            <string>Adds ' . $name . ' to Trusted Root Store</string>
            <key>PayloadDisplayName</key>
            <string>' . $name . '</string>
            <key>PayloadIdentifier</key>
            <string>' . $identifier . '.cert</string>
            <key>PayloadType</key>
            <string>com.apple.security.pkcs1</string>
            <key>PayloadUUID</key>
            <string>' . \Illuminate\Support\Str::uuid() . '</string>
            <key>PayloadVersion</key>
            <integer>1</integer>
        </dict>
    </array>
    <key>PayloadDisplayName</key>
    <string>' . $name . ' Installer</string>
    <key>PayloadIdentifier</key>
    <string>' . $identifier . '</string>
    <key>PayloadRemovalDisallowed</key>
    <false/>
    <key>PayloadType</key>
    <string>Configuration</string>
    <key>PayloadUUID</key>
    <string>' . $uuid . '</string>
    <key>PayloadVersion</key>
    <integer>1</integer>
</dict>
</plist>';

        return response($xml)
            ->header('Content-Type', 'application/x-apple-aspen-config')
            ->header('Content-Disposition', 'attachment; filename="' . $name . '.mobileconfig"');
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

        // Fallback or dynamic generation if needed (already in Service)
        $sslService = app(\App\Services\OpenSslService::class);
        $script = $sslService->generateLinuxInstaller($cert);

        return response($script)
            ->header('Content-Type', 'application/x-sh')
            ->header('Content-Disposition', 'attachment; filename="install-' . Str::slug($cert->common_name) . '.sh"');
    }
}
