<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CertificateSetting\UploadCertificateAssetRequest;
use App\Http\Requests\Admin\CertificateSetting\UpdateCertificateSettingRequest;
use App\Http\Resources\CertificateSettingResource;
use App\Services\CertificateService;

class CertificateSettingController extends Controller
{
    public function __construct(
        protected CertificateService $certificateService
    ) {}

    public function show()
    {
        return response()->json([
            'success' => true,
            'message' => 'Certificate settings retrieved successfully',
            'data' => new CertificateSettingResource($this->certificateService->getCertificateSettings()),
        ]);
    }

    public function update(UpdateCertificateSettingRequest $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Certificate settings updated successfully',
            'data' => new CertificateSettingResource(
                $this->certificateService->updateCertificateSettings($request->validated())
            ),
        ]);
    }

    public function uploadAsset(UploadCertificateAssetRequest $request)
    {
        $path = $this->certificateService->uploadCertificateAsset(
            $request->file('file'),
            (string) $request->validated('type')
        );

        return response()->json([
            'success' => true,
            'message' => 'Certificate asset uploaded successfully',
            'data' => [
                'path' => $path,
            ],
        ]);
    }
}
