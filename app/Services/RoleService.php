<?php

namespace App\Services;

use App\Models\Role;

class RoleService
{
    public function getAll()
    {
        return Role::all();
    }

    public function findById(int $id)
    {
        return Role::findOrFail($id);
    }
}
