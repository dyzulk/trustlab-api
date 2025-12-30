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

    protected function authorizeAdminOrOwner()
    {
        if (!auth()->user()->isAdminOrOwner()) {
            abort(403, 'Unauthorized action. Admin/Owner access required.');
        }
    }
}
