<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\User\StoreUserRequest;
use App\Http\Requests\Admin\User\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\Request;

class UserController extends Controller
{
    protected UserService $service;

    public function __construct(UserService $userService)
    {
        $this->service = $userService;
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $perPage = (int) $request->query('per_page', 10);
        $user = $this->service->getAll($search, $perPage);

        return response()->json([
            'success' => true,
            'message' => 'User list retrieved successfully',
            'data' => UserResource::collection($user),
            'meta' => [
                'current_page' => $user->currentPage(),
                'last_page' => $user->lastPage(),
                'per_page' => $user->perPage(),
                'total' => $user->total(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $user = $this->service->create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => new UserResource($user),
        ]);
    }

    public function show($id)
    {
        $user = $this->service->findById((int) $id);
        
        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => new UserResource($user),
        ]);
    }

    public function update(UpdateUserRequest $request, $id)
    {
        $user = $this->service->update((int) $id, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => new UserResource($user),
        ]);
    }

    public function destroy($id)
    {
        $this->service->delete((int) $id);

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }
}
