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

            $rootDays = Config::get('openssl.durations.root', 7300);
            $rootCert = openssl_csr_sign($rootCsr, null, $rootKey, $rootDays, [
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

            $familyId = (string) Str::uuid();
            $ca = CaCertificate::create([
                'ca_type' => 'root',
                'cert_content' => $rootCertPem,
                'key_content' => $rootKeyPem,
                'serial_number' => $serialHex,
                'common_name' => $rootDetails['subject']['CN'] ?? 'Root CA',
                'issuer_name' => $rootDetails['subject']['CN'] ?? 'Root CA',
                'issuer_serial' => $serialHex,
                'family_id' => $familyId,
                'organization' => $rootDetails['subject']['O'] ?? null,
                'valid_from' => date('Y-m-d H:i:s', $rootDetails['validFrom_time_t']),
                'valid_to' => date('Y-m-d H:i:s', $rootDetails['validTo_time_t']),
                'is_latest' => true,
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

            $intDays = Config::get('openssl.durations.intermediate', 3650);
            $int4096Cert = openssl_csr_sign($int4096Csr, $rootCert, $rootKey, $intDays, [
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
                'issuer_name' => $ca->common_name,
                'issuer_serial' => $ca->serial_number,
                'family_id' => $familyId,
                'organization' => $int4096Details['subject']['O'] ?? null,
                'valid_from' => date('Y-m-d H:i:s', $int4096Details['validFrom_time_t']),
                'valid_to' => date('Y-m-d H:i:s', $int4096Details['validTo_time_t']),
                'is_latest' => true,
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

            $int2048Cert = openssl_csr_sign($int2048Csr, $rootCert, $rootKey, $intDays, [
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
                'issuer_name' => $ca->common_name,
                'issuer_serial' => $ca->serial_number,
                'family_id' => $familyId,
                'organization' => $int2048Details['subject']['O'] ?? null,
                'valid_from' => date('Y-m-d H:i:s', $int2048Details['validFrom_time_t']),
                'valid_to' => date('Y-m-d H:i:s', $int2048Details['validTo_time_t']),
                'is_latest' => true,
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
        
        // Rule: Always use the LATEST active intermediate version
        $intermediate = CaCertificate::where('ca_type', $issuerType)
            ->where('is_latest', true)
            ->first();

        if (!$intermediate) {
            throw new \Exception("Active Intermediate CA ({$issuerType}) not found. Please setup or check CA status.");
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
            
            $days = Config::get('openssl.durations.leaf', 365);
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
                // Intermediate is signed by the LATEST Root
                $root = CaCertificate::where('ca_type', 'root')
                    ->where('is_latest', true)
                    ->first();
                
                // Fallback if no is_latest yet (during initial setuptransition)
                if (!$root) {
                    $root = CaCertificate::where('ca_type', 'root')->latest()->first();
                }

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
                'issuer_name' => $cert->ca_type === 'root' ? $cert->common_name : ($root ? $root->common_name : 'Unknown Root'),
                'issuer_serial' => $cert->ca_type === 'root' ? $newSerialHex : ($root ? $root->serial_number : null),
                'family_id' => $cert->ca_type === 'root' ? (string) \Illuminate\Support\Str::uuid() : ($root ? $root->family_id : $cert->family_id),
            ];

        } finally {
            if ($configFile && file_exists($configFile)) unlink($configFile);
        }
    }

    /**
     * Perform a coordinated renewal of the entire CA chain.
     * Order: Root -> Intermediates.
     */
    public function bulkRenewStrategy()
    {
        $rootDays = Config::get('openssl.durations.root', 7300);
        $intDays = Config::get('openssl.durations.intermediate', 3650);

        // 1. Get current latest Root
        $root = CaCertificate::where('ca_type', 'root')->where('is_latest', true)->first();
        
        // Fallback: If no 'is_latest' found (inconsistent state), take the most recent one
        if (!$root) {
            $root = CaCertificate::where('ca_type', 'root')->latest()->first();
        }

        if (!$root) throw new \Exception("Current Root CA not found.");

        // 2. Renew Root
        $newRoot = $this->executeRenewalFlow($root, $rootDays);

        // 3. Renew Intermediates using the NEW Root
        $intermediates = CaCertificate::whereIn('ca_type', ['intermediate_2048', 'intermediate_4096'])
            ->where('is_latest', true)
            ->get();

        foreach ($intermediates as $int) {
            $this->executeRenewalFlow($int, $intDays);
        }

        // 4. Final Mass Sync
        // 4. Final Mass Sync
        try {
            $installerService = app(\App\Services\CaInstallerService::class);
            $installerService->syncAllBundles();
        } catch (\Exception $e) {
            \Log::error("Failed to sync bundles after bulk renew: " . $e->getMessage());
        }
        
        return true;
    }

    /**
     * Handle the DB + CDN flow for a single renewal.
     */
    public function executeRenewalFlow(CaCertificate $cert, int $days)
    {
        $newData = $this->renewCaCertificate($cert, $days);

        // Unset latest for others of same type/CN
        CaCertificate::where('ca_type', $cert->ca_type)
            ->where('common_name', $cert->common_name)
            ->update(['is_latest' => false]);

        // Create new
        $newCert = CaCertificate::create([
            'ca_type' => $cert->ca_type,
            'common_name' => $cert->common_name,
            'organization' => $cert->organization,
            'key_content' => $cert->key_content,
            'cert_content' => $newData['cert_content'],
            'serial_number' => $newData['serial_number'],
            'valid_from' => $newData['valid_from'],
            'valid_to' => $newData['valid_to'],
            'issuer_name' => $newData['issuer_name'],
            'issuer_serial' => $newData['issuer_serial'],
            'family_id' => $newData['family_id'],
            'is_latest' => true,
        ]);

        // Sync to CDN
        $this->uploadPublicCertsOnly($newCert, 'both');
        // Sync to CDN
        $this->uploadPublicCertsOnly($newCert, 'both');

        try {
            $installerService = app(\App\Services\CaInstallerService::class);
            $installerService->uploadIndividualInstallersOnly($newCert, 'both');
        } catch (\Exception $e) {
            \Log::error("Failed to generate installers for renewed cert: " . $e->getMessage());
            // We do not re-throw, so the renewal itself is considered successful
        }

        return $newCert;
    }

    /**
     * Upload only PEM/DER (The CRT files) to CDN.
     */
    public function uploadPublicCertsOnly(CaCertificate $cert, string $mode = 'both')
    {
        $slug = Str::slug($cert->common_name);
        $paths = [];
        
        if ($mode === 'archive' || $mode === 'both') {
            $paths[] = "ca/archives/{$cert->uuid}/{$slug}";
        }
        if ($mode === 'latest' || $mode === 'both') {
            $paths[] = "ca/{$slug}";
        }

        foreach ($paths as $basePath) {
            $pemPath = $basePath . '.crt';
            $derPath = $basePath . '.der';

            // 1. Upload PEM (.crt)
            Storage::disk('r2-public')->put($pemPath, $cert->cert_content, [
                'visibility' => 'public',
                'ContentType' => 'application/x-x509-ca-cert',
                'CacheControl' => 'no-cache, no-store, must-revalidate'
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
            
            Storage::disk('r2-public')->put($derPath, $derContent, [
                'visibility' => 'public',
                'ContentType' => 'application/x-x509-ca-cert',
                'CacheControl' => 'no-cache, no-store, must-revalidate'
            ]);
        }

        // Always point model paths to the 'latest' version for public UI
        $cert->update([
            'cert_path' => "ca/{$slug}.crt",
            'der_path' => "ca/{$slug}.der",
            'last_synced_at' => now()
        ]);

        return true;
    }

    /**
     * Promote an archived certificate version to 'Latest' (public root)
     */
    public function promoteToLatest(CaCertificate $cert)
    {
        // Simply re-sync this specific certificate version as 'latest'
        $this->uploadPublicCertsOnly($cert, 'latest');
        
        // Delegate installer uploads to CaInstallerService
        $installerService = app(\App\Services\CaInstallerService::class);
        $installerService->uploadIndividualInstallersOnly($cert, 'latest');
        
        // Also sync all bundles to ensure global installers are updated with this promoted version
        $installerService->syncAllBundles();
        
        return true;
    }

    /**
     * Legacy/Full Upload (Uploads everything)
     */
    public function uploadToCdn(CaCertificate $cert)
    {
        try {
            $this->uploadPublicCertsOnly($cert);
            
            // Delegate installer logic
            $installerService = app(\App\Services\CaInstallerService::class);
            $installerService->uploadIndividualInstallersOnly($cert);
            $installerService->syncAllBundles();
            
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to upload CA to R2: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Purge everything under the 'ca/' directory on the CDN.
     */
    public function purgeAllCaFromCdn()
    {
        $disk = Storage::disk('r2-public');
        
        if ($disk->exists('ca')) {
            $disk->deleteDirectory('ca');
        }

        // Reset local database sync status
        CaCertificate::query()->update([
            'last_synced_at' => null,
            'cert_path' => null,
            'der_path' => null,
            'bat_path' => null,
            'mac_path' => null,
            'linux_path' => null,
        ]);

        return true;
    }
}
