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
            $newData = $this->sslService->renewCaCertificate($certificate, $days);

            $certificate->update([
                'cert_content' => $newData['cert_content'],
                'serial_number' => $newData['serial_number'],
                'valid_from' => $newData['valid_from'],
                'valid_to' => $newData['valid_to'],
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Certificate renewed successfully.',
                'data' => $certificate
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Renewal failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function syncCrtOnly()
    {
        $this->authorizeAdminOrOwner();
        try {
            $certificates = CaCertificate::all();
            $count = 0;
            foreach ($certificates as $cert) {
                if ($this->sslService->uploadPublicCertsOnly($cert)) {
                    $count++;
                }
            }
            return response()->json(['status' => 'success', 'message' => "Successfully synced {$count} CRT files."]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => 'Sync failed: ' . $e->getMessage()], 500);
        }
    }

    public function syncInstallersOnly()
    {
        $this->authorizeAdminOrOwner();
        try {
            $certificates = CaCertificate::all();
            $count = 0;
            foreach ($certificates as $cert) {
                if ($this->sslService->uploadIndividualInstallersOnly($cert)) {
                    $count++;
                }
            }
            return response()->json(['status' => 'success', 'message' => "Successfully synced {$count} installer sets."]);
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

    public function syncToCdn()
    {
        $this->authorizeAdminOrOwner();

        try {
            $certificates = CaCertificate::all();
            $count = 0;

            foreach ($certificates as $cert) {
                if ($this->sslService->uploadToCdn($cert)) {
                    $count++;
                }
            }

            return response()->json([
                'status' => 'success',
                'message' => "Successfully synced everything ({$count} certs + bundles) to CDN."
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function authorizeAdminOrOwner()
    {
        if (!auth()->user()->isAdminOrOwner()) {
            abort(403, 'Unauthorized action. Admin/Owner access required.');
        }
    }
}
