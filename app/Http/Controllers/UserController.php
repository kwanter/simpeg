<?php

namespace App\Http\Controllers;

use App\Models\Pegawai;
use App\Models\Role;
use App\Models\User;
use App\Rules\SafeEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
        $allowedRoles = auth()->user()->hasRole('super-admin')
            ? Role::pluck('name')->toArray()
            : Role::where('name', '!=', 'super-admin')->pluck('name')->toArray();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'lowercase', new SafeEmail, 'unique:users,email'],
            'nip' => ['required', 'string', 'max:255', 'exists:pegawai,nip', 'unique:users,nip'],
            'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()],
            'roles' => ['required', 'array'],
            'roles.*' => [Rule::in($allowedRoles)],
        ]);

        $user = User::create([
            'uuid' => Str::uuid(),
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'nip' => $request->nip,
        ]);
        // Accounts are provisioned by trusted admins, not public self-registration.
        $user->email_verified_at = now();
        $user->save();
        $user->syncRoles($request->roles);

        return redirect('/users')->with('status', 'Data User Berhasil Ditambahkan');
    }

    public function edit(User $user)
    {
        $this->denyAdminAccessToSuperAdmin($user);

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
        $this->denyAdminAccessToSuperAdmin($user);

        $allowedRoles = auth()->user()->hasRole('super-admin')
            ? Role::pluck('name')->toArray()
            : Role::where('name', '!=', 'super-admin')->pluck('name')->toArray();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'lowercase', new SafeEmail, Rule::unique(User::class)->ignore($user)],
            'nip' => ['required', 'string', 'max:255', Rule::unique(User::class)->ignore($user)],
            'password' => ['nullable', Password::min(12)->mixedCase()->numbers()->symbols()],
            'roles' => ['required', 'array'],
            'roles.*' => [Rule::in($allowedRoles)],
            'status' => ['required', Rule::in(['0', '1', 0, 1])],
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

    public function destroy(User $user)
    {
        $this->denyAdminAccessToSuperAdmin($user);
        $user->delete();

        return redirect('/users')->with('status', 'User Delete Successfully');
    }

    private function denyAdminAccessToSuperAdmin(User $target): void
    {
        abort_if(! auth()->user()->hasRole('super-admin') && $target->hasRole('super-admin'), 403);
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
