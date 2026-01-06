<?php

namespace App\Services;

use App\Models\CaCertificate;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OpenSslService
{
    /**
     * Generate Root and Intermediate CA certificates.
     */
    public function setupCa()
    {
        if (CaCertificate::count() > 0) {
            return false;
        }

        $rootConfig = Config::get('openssl.ca_root');
        $int4096Config = Config::get('openssl.ca_4096');
        $int2048Config = Config::get('openssl.ca_2048');

        // Create a basic temporary openssl config for CA extensions
        $configContent = "[req]\ndistinguished_name = req\n[v3_ca]\nsubjectKeyIdentifier = hash\nauthorityKeyIdentifier = keyid:always,issuer\nbasicConstraints = critical, CA:true\nkeyUsage = critical, digitalSignature, cRLSign, keyCertSign";
        $configFile = tempnam(sys_get_temp_dir(), 'ca_conf_');
        file_put_contents($configFile, $configContent);

        try {
            // Root CA (4096-bit)
            $rootKey = openssl_pkey_new([
                'private_key_bits' => 4096,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'config' => $configFile
            ]);
            if (!$rootKey) throw new \Exception('Failed to generate Root Key: ' . openssl_error_string());

            $rootCsr = openssl_csr_new($rootConfig, $rootKey, ['digest_alg' => 'sha256', 'config' => $configFile]);
            if (!$rootCsr) throw new \Exception('Failed to generate Root CSR: ' . openssl_error_string());

            // Generate a random serial
            $serial = $this->generateSerialNumber();

            $rootCert = openssl_csr_sign($rootCsr, null, $rootKey, 10950, [
                'digest_alg' => 'sha256',
                'x509_extensions' => 'v3_ca',
                'config' => $configFile,
            ], $serial);
            if (!$rootCert) throw new \Exception('Failed to sign Root Cert: ' . openssl_error_string());
            
            if (!openssl_x509_export($rootCert, $rootCertPem)) throw new \Exception('Failed to export Root Cert');
            if (!openssl_pkey_export($rootKey, $rootKeyPem, null, ['config' => $configFile])) throw new \Exception('Failed to export Root Key');

            $rootDetails = openssl_x509_parse($rootCertPem);
            
            // Prefer serialNumberHex if available (PHP 8.0+)
            $serialHex = isset($rootDetails['serialNumberHex']) 
                ? $this->formatHex($rootDetails['serialNumberHex'])
                : $this->formatSerialToHex($rootDetails['serialNumber']);

            $ca = CaCertificate::create([
                'ca_type' => 'root',
                'cert_content' => $rootCertPem,
                'key_content' => $rootKeyPem,
                'serial_number' => $serialHex,
                'common_name' => $rootDetails['subject']['CN'] ?? 'Root CA',
                'organization' => $rootDetails['subject']['O'] ?? null,
                'valid_from' => date('Y-m-d H:i:s', $rootDetails['validFrom_time_t']),
                'valid_to' => date('Y-m-d H:i:s', $rootDetails['validTo_time_t']),
            ]);

            $this->uploadToCdn($ca);

            // Intermediate CA 4096-bit
            $int4096Key = openssl_pkey_new([
                'private_key_bits' => 4096,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'config' => $configFile
            ]);
            if (!$int4096Key) throw new \Exception('Failed to generate Int-4096 Key: ' . openssl_error_string());

            $int4096Csr = openssl_csr_new($int4096Config, $int4096Key, ['digest_alg' => 'sha256', 'config' => $configFile]);
            if (!$int4096Csr) throw new \Exception('Failed to generate Int-4096 CSR: ' . openssl_error_string());

            $int4096Cert = openssl_csr_sign($int4096Csr, $rootCert, $rootKey, 10950, [
                'digest_alg' => 'sha256',
                'x509_extensions' => 'v3_ca',
                'config' => $configFile,
            ], $this->generateSerialNumber());
            if (!$int4096Cert) throw new \Exception('Failed to sign Int-4096 Cert: ' . openssl_error_string());

            if (!openssl_x509_export($int4096Cert, $int4096CertPem)) throw new \Exception('Failed to export Int-4096 Cert');
            if (!openssl_pkey_export($int4096Key, $int4096KeyPem, null, ['config' => $configFile])) throw new \Exception('Failed to export Int-4096 Key');

            $int4096Details = openssl_x509_parse($int4096CertPem);
            $serialHex4096 = isset($int4096Details['serialNumberHex']) 
                ? $this->formatHex($int4096Details['serialNumberHex'])
                : $this->formatSerialToHex($int4096Details['serialNumber']);

            $ca4096 = CaCertificate::create([
                'ca_type' => 'intermediate_4096', 
                'cert_content' => $int4096CertPem, 
                'key_content' => $int4096KeyPem,
                'serial_number' => $serialHex4096,
                'common_name' => $int4096Details['subject']['CN'] ?? 'Intermediate CA 4096',
                'organization' => $int4096Details['subject']['O'] ?? null,
                'valid_from' => date('Y-m-d H:i:s', $int4096Details['validFrom_time_t']),
                'valid_to' => date('Y-m-d H:i:s', $int4096Details['validTo_time_t']),
            ]);

            $this->uploadToCdn($ca4096);

            // Intermediate CA 2048-bit
            $int2048Key = openssl_pkey_new([
                'private_key_bits' => 2048,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'config' => $configFile
            ]);
            if (!$int2048Key) throw new \Exception('Failed to generate Int-2048 Key: ' . openssl_error_string());

            $int2048Csr = openssl_csr_new($int2048Config, $int2048Key, ['digest_alg' => 'sha256', 'config' => $configFile]);
            if (!$int2048Csr) throw new \Exception('Failed to generate Int-2048 CSR: ' . openssl_error_string());

            $int2048Cert = openssl_csr_sign($int2048Csr, $rootCert, $rootKey, 10950, [
                'digest_alg' => 'sha256',
                'x509_extensions' => 'v3_ca',
                'config' => $configFile,
            ], $this->generateSerialNumber());
            if (!$int2048Cert) throw new \Exception('Failed to sign Int-2048 Cert: ' . openssl_error_string());

            if (!openssl_x509_export($int2048Cert, $int2048CertPem)) throw new \Exception('Failed to export Int-2048 Cert');
            if (!openssl_pkey_export($int2048Key, $int2048KeyPem, null, ['config' => $configFile])) throw new \Exception('Failed to export Int-2048 Key');

            $int2048Details = openssl_x509_parse($int2048CertPem);
            $serialHex2048 = isset($int2048Details['serialNumberHex']) 
                ? $this->formatHex($int2048Details['serialNumberHex'])
                : $this->formatSerialToHex($int2048Details['serialNumber']);

            $ca2048 = CaCertificate::create([
                'ca_type' => 'intermediate_2048', 
                'cert_content' => $int2048CertPem, 
                'key_content' => $int2048KeyPem,
                'serial_number' => $serialHex2048,
                'common_name' => $int2048Details['subject']['CN'] ?? 'Intermediate CA 2048',
                'organization' => $int2048Details['subject']['O'] ?? null,
                'valid_from' => date('Y-m-d H:i:s', $int2048Details['validFrom_time_t']),
                'valid_to' => date('Y-m-d H:i:s', $int2048Details['validTo_time_t']),
            ]);

            $this->uploadToCdn($ca2048);

            return true;
        } finally {
            if (file_exists($configFile)) unlink($configFile);
        }
    }

    /**
     * Generate a domain certificate (Leaf).
     */
    public function generateLeaf($data)
    {
        $keyBits = $data['key_bits'] ?? 2048;
        $issuerType = (int)$keyBits === 4096 ? 'intermediate_4096' : 'intermediate_2048';
        
        $intermediate = CaCertificate::where('ca_type', $issuerType)->first();
        if (!$intermediate) {
            throw new \Exception("Intermediate CA ({$issuerType}) not found. Please setup CA first.");
        }

        $dn = [
            "countryName" => $data['country'],
            "stateOrProvinceName" => $data['state'],
            "localityName" => $data['locality'],
            "organizationName" => $data['organization'],
            "commonName" => $data['common_name']
        ];

        $cn = $data['common_name'];
        $userSan = $data['san'] ?? '';
        
        // Parse user input: split by comma, trim, filter empty
        $entries = array_filter(array_map('trim', explode(',', $userSan)));
        
        // Always include CN as the first DNS entry
        array_unshift($entries, $cn);
        
        $sanArray = array_unique(array_map(function($entry) {
            if (str_starts_with($entry, 'IP:') || str_starts_with($entry, 'DNS:')) {
                return $entry;
            }
            return filter_var($entry, FILTER_VALIDATE_IP) ? "IP:$entry" : "DNS:$entry";
        }, $entries));
        
        $sanString = implode(', ', $sanArray);

        $configFile = null;
        try {
            $configContent = "[req]\ndistinguished_name = req\nreq_extensions = v3_req\nprompt = no\n[req_distinguished_name]\nCN = $cn\n[v3_req]\nsubjectAltName = $sanString";
            $configFile = tempnam(sys_get_temp_dir(), 'openssl_');
            file_put_contents($configFile, $configContent);

            $keyBits = $data['key_bits'] ?? 2048;
            $privKey = openssl_pkey_new([
                'private_key_bits' => (int)$keyBits,
                'private_key_type' => OPENSSL_KEYTYPE_RSA,
                'config' => $configFile
            ]);
            if (!$privKey) throw new \Exception('Failed to generate Private Key: ' . openssl_error_string());

            \Log::debug("Generating Leaf with SAN: " . $sanString);

            $csr = openssl_csr_new($dn, $privKey, [
                'digest_alg' => 'sha256',
                'req_extensions' => 'v3_req',
                'config' => $configFile
            ]);
            if (!$csr) {
                $err = openssl_error_string();
                \Log::error("CSR Creation Failed: " . $err);
                throw new \Exception('Failed to generate CSR: ' . $err);
            }

            $serial = $this->generateSerialNumber();
            \Log::debug("Signing CSR with serial: " . $serial);
            
            $days = 365;
            if (!empty($data['is_test_short_lived'])) {
                $days = 1; // Minimum allowed by OpenSSL, will override DB record later
            }

            $cert = openssl_csr_sign($csr, $intermediate->cert_content, $intermediate->key_content, $days, [
                'digest_alg' => 'sha256',
                'x509_extensions' => 'v3_req',
                'config' => $configFile,
            ], $serial);
            
            if (!$cert) {
                $err = openssl_error_string();
                \Log::error("Certificate Signing Failed: " . $err);
                throw new \Exception('Failed to sign Certificate: ' . $err);
            }

            // Verification: check if serial was actually applied
            $certInfo = openssl_x509_parse($cert);
            $actualSerialHex = isset($certInfo['serialNumberHex']) 
                ? $this->formatHex($certInfo['serialNumberHex'])
                : $this->formatSerialToHex($certInfo['serialNumber']);

            \Log::debug("Certificate signed. Embedded Serial: " . $actualSerialHex);

            if (!openssl_x509_export($cert, $certPem)) throw new \Exception('Failed to export Certificate');
            if (!openssl_pkey_export($privKey, $keyPem, null, ['config' => $configFile])) throw new \Exception('Failed to export Private Key');
            if (!openssl_csr_export($csr, $csrPem)) throw new \Exception('Failed to export CSR');

            $validTo = isset($certInfo['validTo_time_t']) ? date('Y-m-d H:i:s', $certInfo['validTo_time_t']) : null;
            
            // Override for testing: 30 seconds expiration
            if (!empty($data['is_test_short_lived'])) {
                $validTo = date('Y-m-d H:i:s', time() + 30);
            }

            return [
                'cert' => $certPem,
                'key' => $keyPem,
                'csr' => $csrPem,
                'serial' => $actualSerialHex,
                'valid_from' => isset($certInfo['validFrom_time_t']) ? date('Y-m-d H:i:s', $certInfo['validFrom_time_t']) : null,
                'valid_to' => $validTo,
            ];
        } finally {
            if ($configFile && file_exists($configFile)) {
                unlink($configFile);
            }
        }
    }

    /**
     * Generate a unique serial number.
     */
    protected function generateSerialNumber(): int
    {
        try {
            return random_int(1, PHP_INT_MAX);
        } catch (\Exception $e) {
            return time();
        }
    }
    
    /**
     * Format a hex string (from serialNumberHex).
     */
    public function formatHex($hex)
    {
        $hex = strtoupper($hex);
        return implode(':', str_split($hex, 2));
    }

    /**
     * Fallback format a decimal serial number to hex string.
     */
    public function formatSerialToHex($decimal)
    {
        $cleaned = preg_replace('/[^0-9]/', '', (string)$decimal);
        if ($cleaned === '') $cleaned = '0';

        if (function_exists('bcdiv')) {
            $hex = '';
            $value = $cleaned;
            if (!preg_match('/^\d+$/', $value)) $value = '0';

            while (bccomp($value, '0') > 0) {
                $mod = bcmod($value, '16');
                $hex = dechex((int)$mod) . $hex;
                $value = bcdiv($value, '16', 0);
            }
            $hex = $hex ?: '0';
        } else {
            $hex = dechex((int)$cleaned);
        }

        if (strlen($hex) % 2 !== 0) $hex = '0' . $hex;
        return strtoupper(implode(':', str_split($hex, 2)));
    }

    /**
     * Renew (re-sign) an existing CA certificate using its existing private key.
     */
    public function renewCaCertificate(CaCertificate $cert, int $days)
    {
        $configFile = null;
        try {
            // 1. Prepare Config
            $configContent = "[req]\ndistinguished_name = req\n[v3_ca]\nsubjectKeyIdentifier = hash\nauthorityKeyIdentifier = keyid:always,issuer\nbasicConstraints = critical, CA:true\nkeyUsage = critical, digitalSignature, cRLSign, keyCertSign";
            $configFile = tempnam(sys_get_temp_dir(), 'renew_ca_');
            file_put_contents($configFile, $configContent);

            // 2. Get Private Key
            $privKey = openssl_pkey_get_private($cert->key_content);
            if (!$privKey) throw new \Exception('Failed to load Private Key');

            // 3. Get Subject DN from existing Cert
            $certInfo = openssl_x509_parse($cert->cert_content);
            $dn = $certInfo['subject'];
            
            $dnMap = [
                'CN' => 'commonName',
                'O' => 'organizationName',
                'OU' => 'organizationalUnitName',
                'C' => 'countryName',
                'ST' => 'stateOrProvinceName',
                'L' => 'localityName',
                'emailAddress' => 'emailAddress'
            ];
            
            $newDn = [];
            foreach ($dn as $key => $value) {
                if (isset($dnMap[$key])) {
                    $newDn[$dnMap[$key]] = $value;
                }
            }

            // 4. Generate New CSR
            $csr = openssl_csr_new($newDn, $privKey, ['digest_alg' => 'sha256', 'config' => $configFile]);
            if (!$csr) throw new \Exception('Failed to generate Renewal CSR: ' . openssl_error_string());

            // 5. Determine Signer (Issuer)
            $issuerCert = null;
            $issuerKey = null;

            if ($cert->ca_type === 'root') {
                // Root signs itself
                $issuerCert = null; 
                $issuerKey = $privKey;
            } else {
                // Intermediate is signed by Root
                $root = CaCertificate::where('ca_type', 'root')->first();
                if (!$root) throw new \Exception('Root CA not found for signing intermediate renewal.');
                $issuerCert = $root->cert_content;
                $issuerKey = openssl_pkey_get_private($root->key_content);
            }

            // 6. Sign CSR
            $serial = $this->generateSerialNumber();
            $newCert = openssl_csr_sign($csr, $issuerCert, $issuerKey, $days, [
                'digest_alg' => 'sha256',
                'x509_extensions' => 'v3_ca',
                'config' => $configFile,
            ], $serial);

            if (!$newCert) throw new \Exception('Failed to sign Renewal Cert: ' . openssl_error_string());

            // 7. Export
            if (!openssl_x509_export($newCert, $newCertPem)) throw new \Exception('Failed to export Renewal Cert');
            
            // 8. Parse new details
            $newInfo = openssl_x509_parse($newCertPem);
            $newSerialHex = isset($newInfo['serialNumberHex']) 
                ? $this->formatHex($newInfo['serialNumberHex'])
                : $this->formatSerialToHex($newInfo['serialNumber']);

            return [
                'cert_content' => $newCertPem,
                'serial_number' => $newSerialHex,
                'valid_from' => date('Y-m-d H:i:s', $newInfo['validFrom_time_t']),
                'valid_to' => date('Y-m-d H:i:s', $newInfo['validTo_time_t']),
            ];

        } finally {
            if ($configFile && file_exists($configFile)) unlink($configFile);
        }
    }
    /**
     * Generate Windows Installer (.bat)
     */
    public function generateWindowsInstaller(CaCertificate $cert): string
    {
        $cdnUrl = $cert->cert_path ? Storage::disk('r2-public')->url($cert->cert_path) : url("/api/public/ca/{$cert->uuid}/download/pem");
        $typeLabel = $cert->ca_type === 'root' ? 'Root' : 'Intermediate';
        $store = $cert->ca_type === 'root' ? 'Root' : 'CA';

        return "@echo off\n" .
               "echo TrustLab - Installing {$typeLabel} CA Certificate: {$cert->common_name}\n" .
               "set \"TEMP_CERT=%TEMP%\\trustlab-ca-{$cert->uuid}.crt\"\n" .
               "echo Downloading certificate...\n" .
               "curl -L --progress-bar \"{$cdnUrl}\" -o \"%TEMP_CERT%\"\n" .
               "if %ERRORLEVEL% NEQ 0 (\n" .
               "    echo Error: Failed to download certificate.\n" .
               "    pause\n" .
               "    exit /b 1\n" .
               ")\n" .
               "echo Installing to {$store} store...\n" .
               "certutil -addstore -f \"{$store}\" \"%TEMP_CERT%\"\n" .
               "del \"%TEMP_CERT%\"\n" .
               "echo Installation Complete.\n" .
               "pause";
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
     * Generate Linux Installer (.sh)
     */
    public function generateLinuxInstaller(CaCertificate $cert): string
    {
        $cdnUrl = $cert->cert_path ? Storage::disk('r2-public')->url($cert->cert_path) : url("/api/public/ca/{$cert->uuid}/download/pem");
        $filename = "trustlab-" . Str::slug($cert->common_name) . ".crt";

        return "#!/bin/bash\n" .
               "echo \"TrustLab - Installing CA Certificate: {$cert->common_name}\"\n" .
               "if [ \"\$EUID\" -ne 0 ]; then echo \"Please run as root (sudo)\"; exit 1; fi\n" .
               "TEMP_CERT=\"/tmp/trustlab-{$cert->uuid}.crt\"\n" .
               "echo \"Downloading certificate...\"\n" .
               "curl -L --progress-bar \"{$cdnUrl}\" -o \"\$TEMP_CERT\"\n" .
               "if [ ! -f \"\$TEMP_CERT\" ]; then echo \"Failed to download cert\"; exit 1; fi\n\n" .
                "echo \"Checking and installing ca-certificates package...\"\n" .
                "if [ -d /etc/debian_version ]; then\n" .
                "  apt-get update -q && apt-get install -y -q ca-certificates\n" .
                "  mkdir -p /usr/local/share/ca-certificates\n" .
                "elif [ -f /etc/redhat-release ]; then\n" .
                "  yum install -y -q ca-certificates || dnf install -y -q ca-certificates\n" .
                "  mkdir -p /etc/pki/ca-trust/source/anchors\n" .
                "elif [ -f /etc/arch-release ]; then\n" .
                "  pacman -Sy --noconfirm -q ca-certificates\n" .
                "  mkdir -p /etc/ca-certificates/trust-source/anchors\n" .
                "fi\n\n" .
                "# Detection based on directories\n" .
                "if [ -d /usr/local/share/ca-certificates ]; then\n" .
                "  cp \"\$TEMP_CERT\" \"/usr/local/share/ca-certificates/{$filename}\"\n" .
                "  update-ca-certificates\n" .
                "# RHEL/CentOS/Fedora\n" .
                "elif [ -d /etc/pki/ca-trust/source/anchors ]; then\n" .
                "  cp \"\$TEMP_CERT\" \"/etc/pki/ca-trust/source/anchors/{$filename}\"\n" .
                "  update-ca-trust extract\n" .
                "# Arch Linux\n" .
                "elif [ -d /etc/ca-certificates/trust-source/anchors ]; then\n" .
                "  cp \"\$TEMP_CERT\" \"/etc/ca-certificates/trust-source/anchors/{$filename}\"\n" .
                "  trust extract-compat\n" .
                "else\n" .
                "  echo \"Unsupported Linux distribution for automatic install after package check.\"\n" .
                "  echo \"Please manually install \$TEMP_CERT\"\n" .
                "  exit 1\n" .
                "fi\n" .
               "rm \"\$TEMP_CERT\"\n" .
               "echo \"Installation Complete.\"\n" .
               "echo \"To verify, you can check: ls /usr/local/share/ca-certificates/trustlab-*\"\n";
    }

    /**
     * Upload only PEM/DER (The CRT files) to CDN.
     */
    public function uploadPublicCertsOnly(CaCertificate $cert)
    {
        $baseFilename = 'ca/' . Str::slug($cert->common_name) . '-' . $cert->uuid;
        $pemFilename = $baseFilename . '.crt';
        $derFilename = $baseFilename . '.der';

        // 1. Upload PEM (.crt)
        Storage::disk('r2-public')->put($pemFilename, $cert->cert_content, [
            'visibility' => 'public',
            'ContentType' => 'application/x-x509-ca-cert'
        ]);

        // 2. Convert to DER and Upload (.der)
        $lines = explode("\n", trim($cert->cert_content));
        $payload = '';
        foreach ($lines as $line) {
            if (!str_starts_with($line, '-----')) {
                $payload .= trim($line);
            }
        }
        $derContent = base64_decode($payload);
        
        Storage::disk('r2-public')->put($derFilename, $derContent, [
            'visibility' => 'public',
            'ContentType' => 'application/x-x509-ca-cert'
        ]);

        $cert->update([
            'cert_path' => $pemFilename,
            'der_path' => $derFilename,
            'last_synced_at' => now()
        ]);

        return true;
    }

    /**
     * Upload individual installers (SH, BAT, MAC) to CDN.
     */
    public function uploadIndividualInstallersOnly(CaCertificate $cert)
    {
        $baseFilename = 'ca/' . Str::slug($cert->common_name) . '-' . $cert->uuid;
        $batFilename = $baseFilename . '.bat';
        $macFilename = $baseFilename . '.mobileconfig';
        $linuxFilename = $baseFilename . '.sh';
        
        $cacheControl = 'no-cache, no-store, must-revalidate';

        // 3. Generate and Upload Windows Installer (.bat)
        $batContent = $this->generateWindowsInstaller($cert);
        Storage::disk('r2-public')->delete($batFilename);
        Storage::disk('r2-public')->put($batFilename, $batContent, [
            'visibility' => 'public',
            'ContentType' => 'text/plain',
            'CacheControl' => $cacheControl
        ]);

        // 4. Generate and Upload macOS Profile (.mobileconfig)
        $macContent = $this->generateMacInstaller($cert);
        Storage::disk('r2-public')->delete($macFilename);
        Storage::disk('r2-public')->put($macFilename, $macContent, [
            'visibility' => 'public',
            'ContentType' => 'application/x-apple-aspen-config',
            'CacheControl' => $cacheControl
        ]);

        // 5. Generate and Upload Linux Script (.sh)
        $linuxContent = $this->generateLinuxInstaller($cert);
        Storage::disk('r2-public')->delete($linuxFilename);
        Storage::disk('r2-public')->put($linuxFilename, $linuxContent, [
            'visibility' => 'public',
            'ContentType' => 'text/plain',
            'CacheControl' => $cacheControl
        ]);

        $cert->update([
            'bat_path' => $batFilename,
            'mac_path' => $macFilename,
            'linux_path' => $linuxFilename,
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
        $now = now()->format('Y-m-d H:i:s');
        $shContent = "#!/bin/bash\n" .
                     "# Generated at: {$now}\n" .
                     "echo \"TrustLab - Installing all CA Certificates...\"\n" .
                     "if [ \"\$EUID\" -ne 0 ]; then echo \"Please run as root (sudo)\"; exit 1; fi\n\n" .
                     "echo \"Checking and installing ca-certificates package... (Please wait)\"\n" .
                     "if [ -d /etc/debian_version ]; then\n" .
                     "  apt-get update -q && apt-get install -y -q ca-certificates\n" .
                     "  mkdir -p /usr/local/share/ca-certificates\n" .
                     "elif [ -f /etc/redhat-release ]; then\n" .
                     "  yum install -y -q ca-certificates || dnf install -y -q ca-certificates\n" .
                     "  mkdir -p /etc/pki/ca-trust/source/anchors\n" .
                     "elif [ -f /etc/arch-release ]; then\n" .
                     "  pacman -Sy --noconfirm -q ca-certificates\n" .
                     "  mkdir -p /etc/ca-certificates/trust-source/anchors\n" .
                     "fi\n\n" .
                     "# OS Detection after package check\n" .
                     "TARGET_DIR=\"\"\n" .
                     "UPDATE_CMD=\"\"\n\n" .
                     "if [ -d /usr/local/share/ca-certificates ]; then\n" .
                     "  TARGET_DIR=\"/usr/local/share/ca-certificates\"\n" .
                     "  UPDATE_CMD=\"update-ca-certificates\"\n" .
                     "elif [ -d /etc/pki/ca-trust/source/anchors ]; then\n" .
                     "  TARGET_DIR=\"/etc/pki/ca-trust/source/anchors\"\n" .
                     "  UPDATE_CMD=\"update-ca-trust extract\"\n" .
                     "elif [ -d /etc/ca-certificates/trust-source/anchors ]; then\n" .
                     "  TARGET_DIR=\"/etc/ca-certificates/trust-source/anchors\"\n" .
                     "  UPDATE_CMD=\"trust extract-compat\"\n" .
                     "else\n" .
                     "  echo \"Unsupported Linux distribution after package check.\"\n" .
                     "  exit 1\n" .
                     "fi\n\n" .
                     "echo \"Cleaning up old TrustLab certificates...\"\n" .
                     "rm -f \"\$TARGET_DIR/trustlab-*.crt\"\n\n";
        
        foreach ($certificates as $cert) {
            $cdnUrl = $cert->cert_path ? Storage::disk('r2-public')->url($cert->cert_path) : null;
            if (!$cdnUrl) continue;
            
            $filename = "trustlab-" . Str::slug($cert->common_name) . ".crt";
            $shContent .= "echo \"Downloading and deploying {$cert->common_name}...\"\n" .
                          "curl -L --progress-bar \"{$cdnUrl}\" -o \"\$TARGET_DIR/{$filename}\"\n";
        }
        
        $shContent .= "\necho \"Finalizing installation with: \$UPDATE_CMD\"\n" .
                      "\$UPDATE_CMD\n" .
                      "echo \"All certificates installed successfully.\"\n" .
                      "echo \"To verify, you can check: ls \$TARGET_DIR/trustlab-*\"\n";

        Storage::disk('r2-public')->delete('ca/bundles/trustlab-all.sh');
        Storage::disk('r2-public')->put('ca/bundles/trustlab-all.sh', $shContent, [
            'visibility' => 'public',
            'ContentType' => 'text/plain',
            'CacheControl' => $cacheControl
        ]);

        // 2. Windows Bundle (.bat)
        $batContent = "@echo off\n" .
                      "rem Generated at: {$now}\n" .
                      "echo TrustLab - Installing all CA Certificates...\n";
        
        foreach ($certificates as $cert) {
            $cdnUrl = $cert->cert_path ? Storage::disk('r2-public')->url($cert->cert_path) : null;
            if (!$cdnUrl) continue;
            
            $store = $cert->ca_type === 'root' ? 'Root' : 'CA';
            $batContent .= "echo Installing {$cert->common_name} to {$store} store...\n" .
                           "curl -L --progress-bar \"{$cdnUrl}\" -o \"%TEMP%\\trustlab-{$cert->uuid}.crt\"\n" .
                           "certutil -addstore -f \"{$store}\" \"%TEMP%\\trustlab-{$cert->uuid}.crt\"\n" .
                           "del \"%TEMP%\\trustlab-{$cert->uuid}.crt\"\n";
        }
        $batContent .= "echo Installation Complete.\npause";

        Storage::disk('r2-public')->delete('ca/bundles/trustlab-all.bat');
        Storage::disk('r2-public')->put('ca/bundles/trustlab-all.bat', $batContent, [
            'visibility' => 'public',
            'ContentType' => 'text/plain',
            'CacheControl' => $cacheControl
        ]);

        // 3. macOS Bundle (.mobileconfig)
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

    /**
     * Legacy/Full Upload (Uploads everything)
     */
    public function uploadToCdn(CaCertificate $cert)
    {
        try {
            $this->uploadPublicCertsOnly($cert);
            $this->uploadIndividualInstallersOnly($cert);
            $this->syncAllBundles();
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to upload CA to R2: " . $e->getMessage());
            return false;
        }
    }
}
