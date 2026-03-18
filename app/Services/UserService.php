<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserService
{
    public function getAll()
    {
        return User::with('role')->latest()->paginate(10);
    }

    public function findById(int $id)
    {
        return User::with('role')->findOrFail($id);
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
