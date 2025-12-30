<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Certificate;
use App\Models\CaCertificate;
use App\Services\OpenSslService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;
use App\Traits\LogsActivity;
use App\Notifications\CertificateNotification;

class CertificateApiController extends Controller
{
    use LogsActivity;

    protected $sslService;

    public function __construct(OpenSslService $sslService)
    {
        $this->sslService = $sslService;
    }

    /**
     * List user certificates.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');

        $query = Certificate::where('user_id', Auth::id());

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('common_name', 'like', "%{$search}%")
                  ->orWhere('serial_number', 'like', "%{$search}%")
                  ->orWhere('san', 'like', "%{$search}%");
            });
        }

        $certificates = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $certificates,
            'ca_status' => $this->getCaStatus()
        ]);
    }

    /**
     * Generate a new certificate.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'common_name' => 'required|string|max:255',
            'config_mode' => 'required|in:default,manual',
            'organization' => 'nullable|required_if:config_mode,manual|string|max:255',
            'locality' => 'nullable|required_if:config_mode,manual|string|max:255',
            'state' => 'nullable|required_if:config_mode,manual|string|max:255',
            'country' => 'nullable|required_if:config_mode,manual|string|size:2',
            'san' => 'nullable|string',
            'key_bits' => 'required|in:2048,4096',
            'is_test_short_lived' => 'nullable|boolean',
        ]);

        if (!empty($validated['is_test_short_lived']) && !Auth::user()->isAdminOrOwner()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized for test mode'], 403);
        }

        try {
            if ($validated['config_mode'] === 'default') {
                $defaults = Config::get('openssl.ca_leaf_default');
                $validated['organization'] = $defaults['organizationName'];
                $validated['locality'] = $defaults['localityName'];
                $validated['state'] = $defaults['stateOrProvinceName'];
                $validated['country'] = $defaults['countryName'];
            }

            $result = $this->sslService->generateLeaf($validated);

            $certificate = Certificate::create([
                'user_id' => Auth::id(),
                'common_name' => $validated['common_name'],
                'organization' => $validated['organization'],
                'locality' => $validated['locality'],
                'state' => $validated['state'],
                'country' => $validated['country'],
                'san' => $validated['san'],
                'key_bits' => $validated['key_bits'],
                'serial_number' => $result['serial'],
                'cert_content' => $result['cert'],
                'key_content' => $result['key'],
                'csr_content' => $result['csr'],
                'valid_from' => $result['valid_from'],
                'valid_to' => $result['valid_to'],
            ]);

            $this->logActivity('issue_cert', "Issued certificate for {$certificate->common_name}");

            // Notify User
            try {
                Auth::user()->notify(new CertificateNotification($certificate, 'issued'));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send certificate notification: ' . $e->getMessage());
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Certificate generated successfully',
                'data' => $certificate
            ], 201);
        } catch (\Throwable $e) {
            \Log::error('Certificate generation failed: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate certificate: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show certificate details.
     */
    public function show(Certificate $certificate)
    {
        $this->authorizeOwner($certificate);

        return response()->json([
            'status' => 'success',
            'data' => $certificate
        ]);
    }

    /**
     * Delete a certificate.
     */
    public function destroy(Certificate $certificate)
    {
        $this->authorizeOwner($certificate);
        $commonName = $certificate->common_name;
        $certificate->delete();

        $this->logActivity('delete_cert', "Deleted certificate for {$commonName}");

        // Notify User
        try {
            Auth::user()->notify(new CertificateNotification($certificate, 'revoked'));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send certificate revocation notification: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Certificate deleted successfully'
        ]);
    }

    /**
     * Initialize CA (Admin only).
     */
    public function setupCa()
    {
        if (!Auth::user()->isAdminOrOwner()) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        // Allow setup if any of the required CA types are missing
        $status = $this->getCaStatus();
        if ($status['is_ready']) {
            return response()->json(['status' => 'error', 'message' => 'CA already fully initialized'], 400);
        }

        if ($this->sslService->setupCa()) {
            return response()->json(['status' => 'success', 'message' => 'CA successfully initialized']);
        }

        return response()->json(['status' => 'error', 'message' => 'Failed to initialize CA'], 500);
    }

    /**
     * Download certificate files.
     */
    public function downloadFile(Certificate $certificate, $type)
    {
        $this->authorizeOwner($certificate);

        $content = match($type) {
            'cert' => $certificate->cert_content,
            'key' => $certificate->key_content,
            'csr' => $certificate->csr_content,
            default => abort(404)
        };

        $extension = match($type) {
            'cert' => 'crt',
            'key' => 'key',
            'csr' => 'csr',
        };

        $filename = Str::slug($certificate->common_name) . '.' . $extension;

        return response($content)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', "attachment; filename={$filename}");
    }

    protected function getCaStatus()
    {
        $root = CaCertificate::where('ca_type', 'root')->exists();
        $int2048 = CaCertificate::where('ca_type', 'intermediate_2048')->exists();
        $int4096 = CaCertificate::where('ca_type', 'intermediate_4096')->exists();

        return [
            'root' => $root,
            'intermediate_2048' => $int2048,
            'intermediate_4096' => $int4096,
            'is_ready' => $root && $int2048 && $int4096,
            'missing' => array_keys(array_filter([
                'root' => !$root,
                'intermediate_2048' => !$int2048,
                'intermediate_4096' => !$int4096,
            ]))
        ];
    }

    protected function authorizeOwner(Certificate $certificate)
    {
        if ($certificate->user_id !== Auth::id()) {
            abort(403);
        }
    }
}
