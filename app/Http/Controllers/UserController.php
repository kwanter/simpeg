<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware('permission:view user', ['only' => ['index']]);
        $this->middleware('permission:create user', ['only' => ['create', 'store']]);
        $this->middleware('permission:update user', ['only' => ['update', 'edit']]);
        $this->middleware('permission:delete user', ['only' => ['destroy']]);
    }

    public function index()
    {
        if (auth()->user()->hasRole('super-admin')) {
            $users = User::paginate(15);
        } elseif (auth()->user()->hasRole('admin')) {
            $users = User::whereHas('roles', function ($query) {
                $query->where('name', '!=', 'super-admin');
            })->paginate(15);
        } else {
            // Handle case for users without super-admin or admin roles
            $users = collect(); // or redirect, or show an error
        }
        $pegawai = Pegawai::get();

        return view('role-permission.user.index', ['users' => $users, 'pegawai' => $pegawai]);
    }

    public function create()
    {
        if (auth()->user()->hasRole('super-admin')) {
            $roles = Role::pluck('name', 'name')->all();
        } elseif (auth()->user()->hasRole('admin')) {
            $roles = Role::where('name', '!=', 'super-admin')->pluck('name', 'name')->all();
        } else {
            $roles = collect();
        }
        $pegawai = Pegawai::get();

        return view('role-permission.user.create', ['roles' => $roles, 'pegawai' => $pegawai]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|max:20',
            'roles' => 'required',
        ]);

        $user = User::create([
            'uid' => Str::uuid(),
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'nip' => $request->nip,
        ]);

        $user->syncRoles($request->roles);

        return redirect('/users')->with('status', 'Data User Berhasil Ditambahkan');
    }

    public function edit(User $user)
    {
        if (auth()->user()->hasRole('super-admin')) {
            $roles = Role::pluck('name', 'name')->all();
        } elseif (auth()->user()->hasRole('admin')) {
            $roles = Role::where('name', '!=', 'super-admin')->pluck('name', 'name')->all();
        } else {
            // Handle case for users without super-admin or admin roles
            $roles = collect(); // or redirect, or show an error
        }
        $pegawai = Pegawai::get();
        $userRoles = $user->roles->pluck('name', 'name')->all();

        return view('role-permission.user.edit', [
            'user' => $user,
            'roles' => $roles,
            'userRoles' => $userRoles,
            'pegawai' => $pegawai,
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'nullable|string|min:8|max:20',
            'roles' => 'required',
            'status' => 'required',
        ]);

        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'status' => $request->status,
            'nip' => $request->nip,
        ];

        if (! empty($request->password)) {
            $data += [
                'password' => Hash::make($request->password),
            ];
        }

        $user->update($data);
        $user->syncRoles($request->roles);

        return redirect('/users')->with('status', 'Data User Berhasil Diubah');
    }

    public function destroy($userId)
    {
        $user = User::findOrFail($userId);
        $user->delete();

        return redirect('/users')->with('status', 'User Delete Successfully');
    }

    public function search(Request $request)
    {
        $search = $request->search;
        $users = User::where('name', 'like', '%'.$search.'%')
            ->orWhere('email', 'like', '%'.$search.'%')
            ->orWhere('nip', 'like', '%'.$search.'%')
            ->get();

        return view('role-permission.user.index', ['users' => $users]);
    }
}
