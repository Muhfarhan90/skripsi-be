<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Services\RoleService;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    protected RoleService $service;

    public function __construct(RoleService $roleService)
    {
        $this->service = $roleService;
    }

    public function index()
    {
        $roles = $this->service->getAll();

        return response()->json([
            'success' => true,
            'message' => 'Role list retrieved successfully',
            'data' => RoleResource::collection($roles),
        ]);
    }

    public function show($id)
    {
        $role = $this->service->findById((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'Role retrieved successfully',
            'data' => new RoleResource($role),
        ]);
    }
}
