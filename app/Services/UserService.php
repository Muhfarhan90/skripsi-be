<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function getAll(string $search = '', int $perPage = 10)
    {
        $perPage = max($perPage, 1);

        return User::query()
            ->with('role')
            ->withCount('orders')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($builder) use ($search) {
                    $builder->where('fullname', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhereHas('role', function ($roleQuery) use ($search) {
                            $roleQuery->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate($perPage);
    }

    public function findById(int $id)
    {
        return User::with(['role', 'orders.transactions', 'enrollments.courseOffering.course'])->findOrFail($id);
    }

    public function create(array $data)
    {
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Auto verify email when created by Admin
        $data['email_verified_at'] = now();
        $data['is_active'] = true;

        $user = User::create($data);
        $user->load('role');

        return $user;
    }

    public function update(int $id, array $data)
    {
        $user = $this->findById($id);

        if (isset($data['password']) && !empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        $user->load('role');

        return $user;
    }

    public function delete(int $id)
    {
        $user = $this->findById($id);
        $user->delete();

        return true;
    }
}
