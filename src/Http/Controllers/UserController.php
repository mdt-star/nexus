<?php

namespace MdtStar\Nexus\Http\Controllers;

use MdtStar\Nexus\Models\User;
use MdtStar\Nexus\Http\Requests\StoreUserRequest;
use MdtStar\Nexus\Http\Requests\UpdateUserRequest;
use MdtStar\Nexus\Http\Requests\UserFilterRequest;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Hash;

/**
 * 用户管理接口
 */
class UserController extends Controller
{
    public function index(UserFilterRequest $request)
    {
        return User::filter($request->filters())->get();
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        $data['password'] = Hash::make($data['password']);
        return User::create($data);
    }

    public function show(User $user)
    {
        return $user;
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }
        $user->update($data);
        return $user;
    }

    public function destroy(User $user)
    {
        $user->delete();
        return response()->noContent();
    }
}
