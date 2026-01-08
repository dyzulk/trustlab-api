<?php

namespace App\Services;

use App\Models\CaCertificate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CaInstallerService
{
    /**
     * Generate Windows Installer (.bat) with Proxmox-style Aesthetics
     * Note: We use a hybrid approach or advanced echo technics for colors where possible,
     * but standard .bat is limited. We will use a PowerShell wrapper inside the bat for best effect.
     */
    public function generateWindowsInstaller(CaCertificate $cert, bool $isArchive = false): string
    {
        $slug = Str::slug($cert->common_name);
        if ($isArchive) {
            $cdnUrl = Storage::disk('r2-public')->url("ca/archives/{$cert->uuid}/{$slug}.crt");
        } else {
            $cdnUrl = Storage::disk('r2-public')->url("ca/{$slug}.crt");
        }

        $typeLabel = $cert->ca_type === 'root' ? 'Root' : 'Intermediate';
        $store = $cert->ca_type === 'root' ? 'Root' : 'CA';
        $cleanName = $cert->common_name;

        // Hybrid Batch + PowerShell script for rich UI
        return "@echo off\r\n" .
               "setlocal\r\n" .
               "title TrustLab CA Installer\r\n" .
               "call :printHeader\r\n" .
               "\r\n" .
               "echo.\r\n" .
               "call :printInfo \"Initiating installation for: {$cleanName} ({$typeLabel})\"\r\n" .
               "\r\n" .
               "set \"TEMP_CERT=%TEMP%\\trustlab-ca-{$cert->uuid}.crt\"\r\n" .
               "\r\n" .
               "call :printAction \"Downloading CA certificate...\"\r\n" .
               "powershell -Command \"Invoke-WebRequest -Uri '{$cdnUrl}' -OutFile '%TEMP_CERT%'\"\r\n" .
               "if %ERRORLEVEL% NEQ 0 (\r\n" .
               "    call :printError \"Failed to download certificate.\"\r\n" .
               "    pause\r\n" .
               "    exit /b 1\r\n" .
               ")\r\n" .
               "call :printSuccess \"Download complete.\"\r\n" .
               "\r\n" .
               "call :printAction \"Installing to Windows Certificate Store ({$store})...\"\r\n" .
               "certutil -addstore -f \"{$store}\" \"%TEMP_CERT%\" >nul 2>&1\r\n" .
               "if %ERRORLEVEL% NEQ 0 (\r\n" .
               "    call :printError \"Failed to install certificate to store.\"\r\n" .
               "    del \"%TEMP_CERT%\"\r\n" .
               "    pause\r\n" .
               "    exit /b 1\r\n" .
               ")\r\n" .
               "call :printSuccess \"Certificate installed successfully!\"\r\n" .
               "\r\n" .
               "powershell -Command \"Invoke-WebRequest -Uri '" . config('app.url') . "/api/public/ca-certificates/{$cert->serial_number}/track' -Method POST -ErrorAction SilentlyContinue\" >nul 2>&1\r\n" .
               "\r\n" .
               "del \"%TEMP_CERT%\"\r\n" .
               "echo.\r\n" .
               "call :printInfo \"Press any key to close...\"\r\n" .
               "pause >nul\r\n" .
               "exit /b\r\n" .
               "\r\n" .
               ":printHeader\r\n" .
               "cls\r\n" .
               "powershell -Command \"Write-Host '  _______  _____  _    _  _____  _______  _        _______  ______ ' -ForegroundColor Cyan\"\r\n" .
               "powershell -Command \"Write-Host ' |__   __||  __ \| |  | |/ ____||__   __|| |      |__   __||  _   |' -ForegroundColor Cyan\"\r\n" .
               "powershell -Command \"Write-Host '    | |   | |__) || |  | || (___     | |   | |         | |   | |_)  |' -ForegroundColor Cyan\"\r\n" .
               "powershell -Command \"Write-Host '    | |   |  _  / | |  | | \___ \    | |   | |         | |   |  _  < ' -ForegroundColor Cyan\"\r\n" .
               "powershell -Command \"Write-Host '    | |   | | \ \ | |__| | ____) |   | |   | |____     | |   | |_)  |' -ForegroundColor Cyan\"\r\n" .
               "powershell -Command \"Write-Host '    |_|   |_|  \_\| \____/ |_____/    |_|   |______|    |_|   |______|' -ForegroundColor Cyan\"\r\n" .
               "powershell -Command \"Write-Host '                                                                     '\"\r\n" .
               "exit /b\r\n" .
               "\r\n" .
               ":printInfo\r\n" .
               "powershell -Command \"Write-Host ' [ INFO ] %~1' -ForegroundColor Cyan\"\r\n" .
               "exit /b\r\n" .
               "\r\n" .
               ":printAction\r\n" .
               "powershell -Command \"Write-Host ' [ .... ] %~1' -ForegroundColor Yellow\"\r\n" .
               "exit /b\r\n" .
               "\r\n" .
               ":printSuccess\r\n" .
               "powershell -Command \"Write-Host ' [  OK  ] %~1' -ForegroundColor Green\"\r\n" .
               "exit /b\r\n" .
               "\r\n" .
               ":printError\r\n" .
               "powershell -Command \"Write-Host ' [ FAIL ] %~1' -ForegroundColor Red\"\r\n" .
               "exit /b\r\n";
    }

    /**
     * Generate macOS Configuration Profile (.mobileconfig)
     */
    public function generateMacInstaller(CaCertificate $cert): string
    {
        $certBase64 = base64_encode($cert->cert_content);
        $payloadId = "com.trustlab.ca." . Str::slug($cert->common_name);
        $uuid1 = Str::uuid()->toString();
        $uuid2 = Str::uuid()->toString();
        
        // Root CAs use 'com.apple.security.root', Intermediate CAs use 'com.apple.security.pkcs1' (intermediate)
        $payloadType = $cert->ca_type === 'root' ? 'com.apple.security.root' : 'com.apple.security.pkcs1';

        return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
               "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n" .
               "<plist version=\"1.0\">\n" .
               "<dict>\n" .
               "    <key>PayloadContent</key>\n" .
               "    <array>\n" .
               "        <dict>\n" .
               "            <key>PayloadCertificateFileName</key>\n" .
               "            <string>{$cert->common_name}.crt</string>\n" .
               "            <key>PayloadContent</key>\n" .
               "            <data>{$certBase64}</data>\n" .
               "            <key>PayloadDescription</key>\n" .
               "            <string>TrustLab CA Certificate</string>\n" .
               "            <key>PayloadDisplayName</key>\n" .
               "            <string>{$cert->common_name}</string>\n" .
               "            <key>PayloadIdentifier</key>\n" .
               "            <string>{$payloadId}.cert</string>\n" .
               "            <key>PayloadType</key>\n" .
               "            <string>{$payloadType}</string>\n" .
               "            <key>PayloadUUID</key>\n" .
               "            <string>{$uuid2}</string>\n" .
               "            <key>PayloadVersion</key>\n" .
               "            <integer>1</integer>\n" .
               "        </dict>\n" .
               "    </array>\n" .
               "    <key>PayloadDescription</key>\n" .
               "    <string>TrustLab CA Installation</string>\n" .
               "    <key>PayloadDisplayName</key>\n" .
               "    <string>TrustLab CA: {$cert->common_name}</string>\n" .
               "    <key>PayloadIdentifier</key>\n" .
               "    <string>{$payloadId}</string>\n" .
               "    <key>PayloadRemovalDisallowed</key>\n" .
               "    <false/>\n" .
               "    <key>PayloadType</key>\n" .
               "    <string>Configuration</string>\n" .
               "    <key>PayloadUUID</key>\n" .
               "    <string>{$uuid1}</string>\n" .
               "    <key>PayloadVersion</key>\n" .
               "    <integer>1</integer>\n" .
               "</dict>\n" .
               "</plist>";
    }

    /**
     * Generate Linux Installer (.sh) with Proxmox-style Aesthetics
     */
    public function generateLinuxInstaller(CaCertificate $cert, bool $isArchive = false): string
    {
        $slug = Str::slug($cert->common_name);
        if ($isArchive) {
            $cdnUrl = Storage::disk('r2-public')->url("ca/archives/{$cert->uuid}/{$slug}.crt");
        } else {
            $cdnUrl = Storage::disk('r2-public')->url("ca/{$slug}.crt");
        }

        $filename = "trustlab-" . $slug . ".crt";

        return $this->getLinuxHeader() .
               "header_info \"Installing CA: {$cert->common_name}\"\n" .
               "\n" .
               "check_root\n" .
               "\n" .
               "TEMP_CERT=\"/tmp/trustlab-{$cert->uuid}.crt\"\n" .
               "\n" .
               "msg_info \"Downloading certificate...\"\n" .
               "if curl -sL \"{$cdnUrl}\" -o \"\$TEMP_CERT\"; then\n" .
               "    msg_ok \"Certificate downloaded.\"\n" .
               "else\n" .
               "    msg_err \"Failed to download certificate from CDN.\"\n" .
               "    exit 1\n" .
               "fi\n" .
               "\n" .
               "msg_info \"Detecting OS and checking ca-certificates package...\"\n" .
               "if [ -f /etc/debian_version ]; then\n" .
               "    apt-get update -qq >/dev/null 2>&1 && apt-get install -y -qq ca-certificates >/dev/null 2>&1\n" .
               "    mkdir -p /usr/local/share/ca-certificates\n" .
               "    TARGET_DIR=\"/usr/local/share/ca-certificates\"\n" .
               "    UPDATE_CMD=\"update-ca-certificates\"\n" .
               "elif [ -f /etc/redhat-release ]; then\n" .
               "    yum install -y -q ca-certificates >/dev/null 2>&1 || dnf install -y -q ca-certificates >/dev/null 2>&1\n" .
               "    mkdir -p /etc/pki/ca-trust/source/anchors\n" .
               "    TARGET_DIR=\"/etc/pki/ca-trust/source/anchors\"\n" .
               "    UPDATE_CMD=\"update-ca-trust extract\"\n" .
               "elif [ -f /etc/arch-release ]; then\n" .
               "    pacman -Sy --noconfirm -q ca-certificates >/dev/null 2>&1\n" .
               "    mkdir -p /etc/ca-certificates/trust-source/anchors\n" .
               "    TARGET_DIR=\"/etc/ca-certificates/trust-source/anchors\"\n" .
               "    UPDATE_CMD=\"trust extract-compat\"\n" .
               "else\n" .
               "    msg_err \"Unsupported Linux distribution.\"\n" .
               "    exit 1\n" .
               "fi\n" .
               "\n" .
               "msg_info \"Installing certificate to \$TARGET_DIR...\"\n" .
               "cp \"\$TEMP_CERT\" \"\$TARGET_DIR/{$filename}\"\n" .
               "\n" .
               "msg_info \"Updating certificate store...\"\n" .
               "if \$UPDATE_CMD >/dev/null 2>&1; then\n" .
               "    msg_ok \"Store updated successfully.\"\n" .
               "    curl -X POST -s \"" . config('app.url') . "/api/public/ca-certificates/{$cert->serial_number}/track\" >/dev/null 2>&1\n" .
               "else\n" .
               "    msg_err \"Failed to update certificate store.\"\n" .
               "    exit 1\n" .
               "fi\n" .
               "\n" .
               "rm \"\$TEMP_CERT\"\n" .
               "echo -e \"\n\${GN}  Installation Complete! \${CL}\"\n" .
               "echo -e \"\${BL}  Verify with: \${CL}ls \$TARGET_DIR/trustlab-*\"\n";
    }

    /**
     * Common Linux Bash Header with Colors & Helpers
     */
    private function getLinuxHeader(): string
    {
        return "#!/bin/bash\n" .
               "# TrustLab CA Installer\n" .
               "# Generated via CaInstallerService\n" .
               "\n" .
               "set -e\n" .
               "\n" .
               "YW=$(echo \"\\033[33m\")\n" .
               "BL=$(echo \"\\033[36m\")\n" .
               "RD=$(echo \"\\033[01;31m\")\n" .
               "BGN=$(echo \"\\033[4;32m\")\n" .
               "GN=$(echo \"\\033[1;92m\")\n" .
               "DGN=$(echo \"\\033[32m\")\n" .
               "CL=$(echo \"\\033[m\")\n" .
               "CM=\"\${GN}✓\${CL}\"\n" .
               "CROSS=\"\${RD}✗\${CL}\"\n" .
               "BFR=\"\\\\r\\\\033[K\"\n" .
               "HOLD=\"-\"\n" .
               "\n" .
               "header_info() {\n" .
               "  clear\n" .
               "  cat << \"EOF\"\n" .
               "\${BL}\n" .
               "  _______  _____  _    _  _____  _______  _        _______  ______ \n" .
               " |__   __||  __ \| |  | |/ ____||__   __|| |      |__   __||  _   |\n" .
               "    | |   | |__) || |  | || (___     | |   | |         | |   | |_)  |\n" .
               "    | |   |  _  / | |  | | \___ \    | |   | |         | |   |  _  < \n" .
               "    | |   | | \ \ | |__| | ____) |   | |   | |____     | |   | |_)  |\n" .
               "    |_|   |_|  \_\| \____/ |_____/    |_|   |______|    |_|   |______|\${CL}\n" .
               "\n" .
               "EOF\n" .
               "}\n" .
               "\n" .
               "msg_info() {\n" .
               "    local msg=\"$1\"\n" .
               "    echo -ne \" \${BL}[ INFO ]\${CL} \${msg}...\"\n" .
               "}\n" .
               "\n" .
               "msg_ok() {\n" .
               "    local msg=\"$1\"\n" .
               "    echo -e \"\${BFR} \${GN}[  OK  ]\${CL} \${msg}\"\n" .
               "}\n" .
               "\n" .
               "msg_err() {\n" .
               "    local msg=\"$1\"\n" .
               "    echo -e \"\${BFR} \${RD}[ FAIL ]\${CL} \${msg}\"\n" .
               "}\n" .
               "\n" .
               "check_root() {\n" .
               "    if [ \"$(id -u)\" -ne 0 ]; then\n" .
               "        msg_err \"Please run as root (sudo).\"\n" .
               "        exit 1\n" .
               "    fi\n" .
               "}\n" .
               "\n";
    }

    /**
     * Upload individual installers (SH, BAT, MAC) to CDN.
     */
    public function uploadIndividualInstallersOnly(CaCertificate $cert, string $mode = 'both')
    {
        $slug = Str::slug($cert->common_name);
        $cacheControl = 'no-cache, no-store, must-revalidate';
        
        $syncs = [];
        if ($mode === 'archive' || $mode === 'both') {
            $syncs[] = ['base' => "ca/archives/{$cert->uuid}/installers/trustlab-{$slug}", 'isArchive' => true];
        }
        if ($mode === 'latest' || $mode === 'both') {
            $syncs[] = ['base' => "ca/installers/trustlab-{$slug}", 'isArchive' => false];
        }

        foreach ($syncs as $sync) {
            $batPath = $sync['base'] . '.bat';
            $macPath = $sync['base'] . '.mobileconfig';
            $linuxPath = $sync['base'] . '.sh';

            // 3. Generate and Upload Windows Installer (.bat)
            $batContent = $this->generateWindowsInstaller($cert, $sync['isArchive']);
            Storage::disk('r2-public')->put($batPath, $batContent, [
                'visibility' => 'public',
                'ContentType' => 'text/plain',
                'CacheControl' => $cacheControl
            ]);

            // 4. Generate and Upload macOS Profile (.mobileconfig)
            $macContent = $this->generateMacInstaller($cert); // macOS profiles are self-contained
            Storage::disk('r2-public')->put($macPath, $macContent, [
                'visibility' => 'public',
                'ContentType' => 'application/x-apple-aspen-config',
                'CacheControl' => $cacheControl
            ]);

            // 5. Generate and Upload Linux Script (.sh)
            $linuxContent = $this->generateLinuxInstaller($cert, $sync['isArchive']);
            Storage::disk('r2-public')->put($linuxPath, $linuxContent, [
                'visibility' => 'public',
                'ContentType' => 'text/plain',
                'CacheControl' => $cacheControl
            ]);
        }

        $cert->update([
            'bat_path' => "ca/installers/trustlab-{$slug}.bat",
            'mac_path' => "ca/installers/trustlab-{$slug}.mobileconfig",
            'linux_path' => "ca/installers/trustlab-{$slug}.sh",
            'last_synced_at' => now()
        ]);

        return true;
    }

    /**
     * Generate Global Bundles (Installer Sapujagat)
     */
    public function syncAllBundles()
    {
        $certificates = CaCertificate::all();
        if ($certificates->isEmpty()) return false;

        $cacheControl = 'no-cache, no-store, must-revalidate';

        // 1. Linux Bundle (.sh)
        // Note: Using the same Proxmox-style header
        $now = now()->format('Y-m-d H:i:s');
        
        $shContent = $this->getLinuxHeader() .
                     "header_info \"Bundle Installer (All CAs)\"\n" .
                     "\n" .
                     "check_root\n" .
                     "\n" .
                     "msg_info \"Detecting OS and checking ca-certificates package...\"\n" .
                     "if [ -f /etc/debian_version ]; then\n" .
                     "    apt-get update -qq >/dev/null 2>&1 && apt-get install -y -qq ca-certificates >/dev/null 2>&1\n" .
                     "    mkdir -p /usr/local/share/ca-certificates\n" .
                     "    TARGET_DIR=\"/usr/local/share/ca-certificates\"\n" .
                     "    UPDATE_CMD=\"update-ca-certificates\"\n" .
                     "elif [ -f /etc/redhat-release ]; then\n" .
                     "    yum install -y -q ca-certificates >/dev/null 2>&1 || dnf install -y -q ca-certificates >/dev/null 2>&1\n" .
                     "    mkdir -p /etc/pki/ca-trust/source/anchors\n" .
                     "    TARGET_DIR=\"/etc/pki/ca-trust/source/anchors\"\n" .
                     "    UPDATE_CMD=\"update-ca-trust extract\"\n" .
                     "elif [ -f /etc/arch-release ]; then\n" .
                     "    pacman -Sy --noconfirm -q ca-certificates >/dev/null 2>&1\n" .
                     "    mkdir -p /etc/ca-certificates/trust-source/anchors\n" .
                     "    TARGET_DIR=\"/etc/ca-certificates/trust-source/anchors\"\n" .
                     "    UPDATE_CMD=\"trust extract-compat\"\n" .
                     "else\n" .
                     "    msg_err \"Unsupported Linux distribution.\"\n" .
                     "    exit 1\n" .
                     "fi\n" .
                     "\n";

        // Loop add certificate downloads to bundle
        foreach ($certificates as $cert) {
             $slug = Str::slug($cert->common_name);
             // Use public URL for public accessibility
             $cdnUrl = Storage::disk('r2-public')->url("ca/{$slug}.crt");
             $filename = "trustlab-" . $slug . ".crt";
             
             $shContent .= "msg_info \"Processing: {$cert->common_name}\"\n";
             $shContent .= "curl -sL \"{$cdnUrl}\" -o \"\$TARGET_DIR/{$filename}\"\n";
             // Telemetry Ping (Silent)
             $shContent .= "curl -X POST -s \"" . config('app.url') . "/api/public/ca-certificates/{$cert->serial_number}/track\" >/dev/null 2>&1\n";
        }
                     
        $shContent .= "\nmsg_info \"Updating certificate store...\"\n" .
                      "if \$UPDATE_CMD >/dev/null 2>&1; then\n" .
                      "    msg_ok \"All certificates installed & store updated.\"\n" .
                      "else\n" .
                      "    msg_err \"Failed to update certificate store.\"\n" .
                      "    exit 1\n" .
                      "fi\n" .
                      "\n" .
                      "echo -e \"\n\${GN}  Complete! Installed all trustlab certs.\${CL}\"\n";


        Storage::disk('r2-public')->put('ca/bundles/trustlab-all.sh', $shContent, [
            'visibility' => 'public',
            'ContentType' => 'text/plain',
            'CacheControl' => $cacheControl
        ]);

        // 2. Windows Bundle (.bat)
        // Hybrid Batch + PowerShell script for rich UI
        $batContent = "@echo off\r\n" .
               "setlocal\r\n" .
               "title TrustLab All-in-One Installer\r\n" .
               "call :printHeader\r\n" .
               "\r\n" .
               "echo.\r\n" .
               "call :printInfo \"Starting Bundle Installation...\"\r\n" .
               "\r\n";

        foreach ($certificates as $cert) {
             $slug = Str::slug($cert->common_name);
             $cdnUrl = Storage::disk('r2-public')->url("ca/{$slug}.crt");
             $store = $cert->ca_type === 'root' ? 'Root' : 'CA';
             
             $batContent .= "set \"TEMP_CERT=%TEMP%\\trustlab-{$slug}.crt\"\r\n" .
                            "call :printAction \"Installing {$cert->common_name}...\"\r\n" .
                            "powershell -Command \"Invoke-WebRequest -Uri '{$cdnUrl}' -OutFile '%TEMP_CERT%'\"\r\n" .
                            "certutil -addstore -f \"{$store}\" \"%TEMP_CERT%\" >nul 2>&1\r\n" .
                             "powershell -Command \"Invoke-WebRequest -Uri '" . config('app.url') . "/api/public/ca-certificates/{$cert->serial_number}/track' -Method POST -ErrorAction SilentlyContinue\" >nul 2>&1\r\n" .
                            "del \"%TEMP_CERT%\"\r\n";
        }

        $batContent .= "\r\n" .
               "call :printSuccess \"All certificates processed.\"\r\n" .
               "echo.\r\n" .
               "call :printInfo \"Press any key to close...\"\r\n" .
               "pause >nul\r\n" .
               "exit /b\r\n" .
               "\r\n" .
               ":printHeader\r\n" .
               "cls\r\n" .
               "powershell -Command \"Write-Host '  _______  _____  _    _  _____  _______  _        _______  ______ ' -ForegroundColor Cyan\"\r\n" .
               "powershell -Command \"Write-Host ' |__   __||  __ \| |  | |/ ____||__   __|| |      |__   __||  _   |' -ForegroundColor Cyan\"\r\n" .
               "powershell -Command \"Write-Host '    | |   | |__) || |  | || (___     | |   | |         | |   | |_)  |' -ForegroundColor Cyan\"\r\n" .
               "powershell -Command \"Write-Host '    | |   |  _  / | |  | | \___ \    | |   | |         | |   |  _  < ' -ForegroundColor Cyan\"\r\n" .
               "powershell -Command \"Write-Host '    | |   | | \ \ | |__| | ____) |   | |   | |____     | |   | |_)  |' -ForegroundColor Cyan\"\r\n" .
               "powershell -Command \"Write-Host '    |_|   |_|  \_\| \____/ |_____/    |_|   |______|    |_|   |______|' -ForegroundColor Cyan\"\r\n" .
               "powershell -Command \"Write-Host '                                                                     '\"\r\n" .
               "exit /b\r\n" .
               "\r\n" .
               ":printInfo\r\n" .
               "powershell -Command \"Write-Host ' [ INFO ] %~1' -ForegroundColor Cyan\"\r\n" .
               "exit /b\r\n" .
               "\r\n" .
               ":printAction\r\n" .
               "powershell -Command \"Write-Host ' [ .... ] %~1' -ForegroundColor Yellow\"\r\n" .
               "exit /b\r\n" .
               "\r\n" .
               ":printSuccess\r\n" .
               "powershell -Command \"Write-Host ' [  OK  ] %~1' -ForegroundColor Green\"\r\n" .
               "exit /b\r\n" .
               "\r\n" .
               ":printError\r\n" .
               "powershell -Command \"Write-Host ' [ FAIL ] %~1' -ForegroundColor Red\"\r\n" .
               "exit /b\r\n";

        Storage::disk('r2-public')->put('ca/bundles/trustlab-all.bat', $batContent, [
            'visibility' => 'public',
            'ContentType' => 'text/plain',
            'CacheControl' => $cacheControl
        ]);
        
        // 3. MacOS Bundle (Config Profile - logic kept as is)
        $uuid1 = Str::uuid()->toString();
        $payloadContent = "";
        
        foreach ($certificates as $cert) {
            $certBase64 = base64_encode($cert->cert_content);
            $uuidSub = Str::uuid()->toString();
            $payloadType = $cert->ca_type === 'root' ? 'com.apple.security.root' : 'com.apple.security.pkcs1';
            
            $payloadContent .= "        <dict>\n" .
                               "            <key>PayloadCertificateFileName</key>\n" .
                               "            <string>{$cert->common_name}.crt</string>\n" .
                               "            <key>PayloadContent</key>\n" .
                               "            <data>{$certBase64}</data>\n" .
                               "            <key>PayloadDescription</key>\n" .
                               "            <string>TrustLab CA Certificate</string>\n" .
                               "            <key>PayloadDisplayName</key>\n" .
                               "            <string>{$cert->common_name}</string>\n" .
                               "            <key>PayloadIdentifier</key>\n" .
                               "            <string>com.trustlab.bundle.{$cert->uuid}</string>\n" .
                               "            <key>PayloadType</key>\n" .
                               "            <string>{$payloadType}</string>\n" .
                               "            <key>PayloadUUID</key>\n" .
                               "            <string>{$uuidSub}</string>\n" .
                               "            <key>PayloadVersion</key>\n" .
                               "            <integer>1</integer>\n" .
                               "        </dict>\n";
        }

        $macContent = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
                      "<!DOCTYPE plist PUBLIC \"-//Apple//DTD PLIST 1.0//EN\" \"http://www.apple.com/DTDs/PropertyList-1.0.dtd\">\n" .
                      "<plist version=\"1.0\">\n" .
                      "<dict>\n" .
                      "    <key>PayloadContent</key>\n" .
                      "    <array>\n" . $payloadContent . "    </array>\n" .
                      "    <key>PayloadDescription</key>\n" .
                      "    <string>TrustLab All-in-One CA Bundle</string>\n" .
                      "    <key>PayloadDisplayName</key>\n" .
                      "    <string>TrustLab CA Bundle</string>\n" .
                      "    <key>PayloadIdentifier</key>\n" .
                      "    <string>com.trustlab.ca.bundle</string>\n" .
                      "    <key>PayloadRemovalDisallowed</key>\n" .
                      "    <false/>\n" .
                      "    <key>PayloadType</key>\n" .
                      "    <string>Configuration</string>\n" .
                      "    <key>PayloadUUID</key>\n" .
                      "    <string>{$uuid1}</string>\n" .
                      "    <key>PayloadVersion</key>\n" .
                      "    <integer>1</integer>\n" .
                      "</dict>\n" .
                      "</plist>";

        Storage::disk('r2-public')->delete('ca/bundles/trustlab-all.mobileconfig');
        Storage::disk('r2-public')->put('ca/bundles/trustlab-all.mobileconfig', $macContent, [
            'visibility' => 'public',
            'ContentType' => 'application/x-apple-aspen-config',
            'CacheControl' => $cacheControl
        ]);

        return true;
    }
}
