<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaCertificate;
use App\Services\OpenSslService;
use Illuminate\Http\Request;

class RootCaApiController extends Controller
{
    protected $sslService;

    public function __construct(OpenSslService $sslService)
    {
        $this->sslService = $sslService;
    }

    public function index()
    {
        $this->authorizeAdminOrOwner();

        $certificates = CaCertificate::all()->map(function($cert) {
            $cert->status = $cert->valid_to->isFuture() ? 'valid' : 'expired';
            return $cert;
        });

        return response()->json([
            'status' => 'success',
            'data' => $certificates
        ]);
    }

    public function renew(Request $request, CaCertificate $certificate)
    {
        $this->authorizeAdminOrOwner();

        $days = (int) $request->input('days', 3650);

        try {
            $newCertificate = $this->sslService->executeRenewalFlow($certificate, $days);

            return response()->json([
                'status' => 'success',
                'message' => 'Certificate renewed as a new version successfully.',
                'data' => $newCertificate
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Renewal failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function renewAll(Request $request)
    {
        $this->authorizeAdminOrOwner();
        $days = (int) $request->input('days', 3650);

        try {
            $this->sslService->bulkRenewStrategy($days);
            return response()->json([
                'status' => 'success',
                'message' => 'Entire CA Chain (Root & Intermediates) renewed successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bulk renewal failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function syncCrtOnly(Request $request)
    {
        $this->authorizeAdminOrOwner();
        try {
            $mode = $request->input('mode', 'both');
            $certificates = CaCertificate::all();
            $count = 0;
            foreach ($certificates as $cert) {
                if ($this->sslService->uploadPublicCertsOnly($cert, $mode)) {
                    $count++;
                }
            }
            return response()->json(['status' => 'success', 'message' => "Successfully synced {$count} CRT files (Mode: {$mode})."]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Sync failed: ' . $e->getMessage()], 500);
        }
    }

    public function syncInstallersOnly(Request $request)
    {
        $this->authorizeAdminOrOwner();
        try {
            $mode = $request->input('mode', 'both');
            $certificates = CaCertificate::all();
            $count = 0;
            foreach ($certificates as $cert) {
                if ($this->sslService->uploadIndividualInstallersOnly($cert, $mode)) {
                    $count++;
                }
            }
            return response()->json(['status' => 'success', 'message' => "Successfully synced {$count} installer sets (Mode: {$mode})."]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Sync failed: ' . $e->getMessage()], 500);
        }
    }

    public function syncBundlesOnly()
    {
        $this->authorizeAdminOrOwner();
        try {
            if ($this->sslService->syncAllBundles()) {
                return response()->json(['status' => 'success', 'message' => "Successfully synced All-in-One bundles."]);
            }
            return response()->json(['status' => 'error', 'message' => 'No certificates found to bundle.'], 404);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Sync failed: ' . $e->getMessage()], 500);
        }
    }

    public function syncToCdn(Request $request)
    {
        $this->authorizeAdminOrOwner();
        $mode = $request->input('mode', 'both');

        try {
            $certificates = CaCertificate::all();
            $count = 0;

            foreach ($certificates as $cert) {
                if ($this->sslService->uploadPublicCertsOnly($cert, $mode)) {
                    $this->sslService->uploadIndividualInstallersOnly($cert, $mode);
                    $count++;
                }
            }
            
            // Also sync bundles (Always 'latest' as bundles are aggregate)
            $this->sslService->syncAllBundles();

            return response()->json([
                'status' => 'success',
                'message' => "Successfully synced everything ({$count} certs + bundles) to CDN (Mode: {$mode})."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function promote(CaCertificate $certificate)
    {
        $this->authorizeAdminOrOwner();
        try {
            // 1. Unset 'is_latest' from all versions of this CA type/name
            CaCertificate::where('ca_type', $certificate->ca_type)
                ->where('common_name', $certificate->common_name)
                ->update(['is_latest' => false]);

            // 2. Set this one as latest
            $certificate->update(['is_latest' => true]);

            // 3. Promote on CDN
            $this->sslService->promoteToLatest($certificate);

            return response()->json(['status' => 'success', 'message' => "Certificate version {$certificate->uuid} promoted to Latest successfully."]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Promotion failed: ' . $e->getMessage()], 500);
        }
    }

    protected function authorizeAdminOrOwner()
    {
        if (!auth()->user()->isAdminOrOwner()) {
            abort(403, 'Unauthorized action. Admin/Owner access required.');
        }
    }
}
