<?php

namespace App\Http\Controllers\Admin;

use App\Models\Brand;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends AdminController
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $users = User::with('brand')->orderBy('role')->orderBy('name')->get();
        $brands = Brand::orderBy('sort_order')->orderBy('name')->get();

        return view('admin.users.index', compact('users', 'brands'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in(Roles::ALL)],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        if ($data['role'] !== Roles::ADMIN && empty($data['brand_id'])) {
            return back()->withInput()->withErrors([
                'brand_id' => 'Tài khoản quản lý và chỉ xem bắt buộc phải gắn với một quán.',
            ]);
        }

        User::create($data + ['is_active' => true]);

        return back()->with('status', 'Đã tạo tài khoản '.$data['email'].'.');
    }

    public function update(Request $request, User $user)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in(Roles::ALL)],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        if ($data['role'] !== Roles::ADMIN && empty($data['brand_id'])) {
            return back()->withErrors([
                'brand_id' => 'Tài khoản quản lý và chỉ xem bắt buộc phải gắn với một quán.',
            ]);
        }

        if (blank($data['password'])) {
            unset($data['password']);
        }

        // Khong tu khoa chinh minh ra khoi he thong.
        $isSelf = $user->id === $request->user()->id;
        $data['is_active'] = $isSelf ? true : $request->boolean('is_active');

        if ($isSelf) {
            $data['role'] = Roles::ADMIN;
        }

        $user->update($data);

        return back()->with('status', 'Đã cập nhật tài khoản '.$user->email.'.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_unless($request->user()->isAdmin(), 403);

        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Không thể xóa tài khoản đang đăng nhập.']);
        }

        $user->update(['is_active' => false]);

        return back()->with('status', 'Đã khóa tài khoản '.$user->email.'.');
    }
}
