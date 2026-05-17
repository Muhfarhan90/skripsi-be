<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CertificateResource;
use App\Services\CertificateService;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    protected CertificateService $service;

    public function __construct(CertificateService $certificateService)
    {
        $this->service = $certificateService;
    }

    /**
     * Mendapatkan semua sertifikat milik user yang login.
     */
    public function index(Request $request)
    {
        $certificates = $this->service->getUserCertificates($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Certificates retrieved successfully',
            'data' => CertificateResource::collection($certificates->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $certificates->currentPage(),
                'last_page' => $certificates->lastPage(),
                'per_page' => $certificates->perPage(),
                'total' => $certificates->total(),
            ],
        ]);
    }

    /**
     * Mendapatkan sertifikat untuk enrollment tertentu (jika ada).
     */
    public function show(Request $request, string $enrollmentId)
    {
        $certificate = $this->service->getCertificateByEnrollment((int) $enrollmentId, $request->user());

        if (!$certificate) {
            return response()->json([
                'success' => false,
                'message' => 'Certificate not found or not generated yet.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Certificate retrieved successfully',
            'data' => new CertificateResource($certificate),
        ]);
    }

    /**
     * Generate sertifikat (bisa dipanggil berulang, jika sudah ada akan return yang sama).
     */
    public function generate(Request $request, string $enrollmentId)
    {
        $certificate = $this->service->generateCertificate((int) $enrollmentId, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Certificate generated successfully',
            'data' => new CertificateResource($certificate),
        ], 201);
    }

    public function preview(Request $request, string $certificateId)
    {
        $certificate = $this->service->getOwnedCertificate((int) $certificateId, $request->user());

        return response($this->service->renderCertificatePrintPreview($certificate), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    public function download(Request $request, string $certificateId)
    {
        $certificate = $this->service->getOwnedCertificate((int) $certificateId, $request->user());

        return response($this->service->renderCertificatePdf($certificate), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->service->getDownloadFilename($certificate) . '"',
        ]);
    }
}
