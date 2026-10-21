<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->with('roles', 'visitor')
            ->when($request->input('search'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")))
            ->when($request->input('role'), fn ($q, $role) => $q->whereHas('roles', fn ($r) => $r->where('name', $role)))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users, 'roles' => Role::orderBy('display_name')->get()]);
    }

    public function create(): View
    {
        return $this->form(new User(['is_active' => true]));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules() + [
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create($data);
        $user->forceFill(['email_verified_at' => now(), 'is_active' => $request->boolean('is_active')])->save();
        $this->syncAccess($request, $user);
        ActivityLog::record('user.created', $user);

        return redirect()->route('admin.users.edit', $user)->with('success', __('User created.'));
    }

    public function edit(User $user): View
    {
        return $this->form($user->load('roles', 'permissions', 'visitor'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin() && ! $request->user()->isSuperAdmin(), 403);

        $data = $request->validate($this->rules($user) + [
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $isSelf = $user->is($request->user());
        $data['is_active'] = $isSelf ? true : $request->boolean('is_active'); // you can't lock yourself out

        $user->update($data);
        $this->syncAccess($request, $user);
        ActivityLog::record('user.updated', $user, ['changes' => array_keys($user->getChanges())]);

        return back()->with('success', __('User updated.'));
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_if($user->is($request->user()), 422, __('You cannot delete your own account here.'));
        abort_if($user->isSuperAdmin() && ! $request->user()->isSuperAdmin(), 403);

        $user->tokens()->delete();
        $user->delete();
        ActivityLog::record('user.deleted', $user);

        return redirect()->route('admin.users.index')->with('success', __('User deleted.'));
    }

    protected function form(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user,
            'roles' => Role::orderBy('display_name')->get(),
            'permissions' => Permission::orderBy('group')->orderBy('name')->get()->groupBy('group'),
        ]);
    }

    protected function rules(?User $user = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'personal_id' => ['nullable', 'string', 'regex:/^\d{11}$/', Rule::unique('users')->ignore($user?->id)],
            'roles' => ['array'],
            'roles.*' => ['exists:roles,id'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ];
    }

    /** Roles and direct permissions. Only admins can hand out the admin role. */
    protected function syncAccess(Request $request, User $user): void
    {
        $actor = $request->user();
        $superRole = Role::where('name', config('permissions.super_admin_role'))->first();
        $roleIds = collect($request->input('roles', []))->map(fn ($id) => (int) $id);

        if ($superRole && ! $actor->isSuperAdmin()) {
            // Keep whatever the user had for the admin role; ignore what the form sent.
            $roleIds = $roleIds->reject(fn ($id) => $id === $superRole->id);
            if ($user->roles()->whereKey($superRole->id)->exists()) {
                $roleIds->push($superRole->id);
            }
        }
        if ($user->is($actor) && $superRole && $actor->isSuperAdmin()) {
            $roleIds->push($superRole->id); // an admin can't remove their own admin role
        }

        $user->syncRoles($roleIds->unique()->all());
        $user->syncDirectPermissions($request->input('permissions', []));
    }
}
