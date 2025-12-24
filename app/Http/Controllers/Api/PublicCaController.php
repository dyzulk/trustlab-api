<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CaCertificate;
use Illuminate\Http\Request;

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
            ->get(['common_name', 'ca_type', 'serial_number', 'valid_to', 'cert_content'])
            ->map(function ($cert) {
                return [
                    'name' => $cert->common_name,
                    'type' => $cert->ca_type,
                    'serial' => $cert->serial_number,
                    'expires_at' => $cert->valid_to->toIso8601String(),
                    'certificate' => $cert->cert_content,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $certificates
        ]);
    }
}
