<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::withCount('users', 'permissions')->orderBy('display_name')->get(),
        ]);
    }

    public function create(): View
    {
        return $this->form(new Role);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate($this->rules());
        $role = Role::create($data);
        $role->permissions()->sync($request->input('permissions', []));
        ActivityLog::record('role.created', $role);

        return redirect()->route('admin.roles.index')->with('success', __('Role created.'));
    }

    public function edit(Role $role): View
    {
        return $this->form($role->load('permissions'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $data = $request->validate($this->rules($role));
        if ($role->is_system) {
            unset($data['name']); // system role names are referenced in code/config
        }

        $role->update($data);
        $role->permissions()->sync($request->input('permissions', []));
        ActivityLog::record('role.updated', $role);

        return redirect()->route('admin.roles.index')->with('success', __('Role updated.'));
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->is_system, 422, __('System roles cannot be deleted.'));

        $role->delete();
        ActivityLog::record('role.deleted', $role, ['name' => $role->name]);

        return redirect()->route('admin.roles.index')->with('success', __('Role deleted.'));
    }

    /** Read-only overview: which role has which permission. */
    public function permissions(): View
    {
        return view('admin.roles.permissions', [
            'roles' => Role::with('permissions')->orderBy('display_name')->get(),
            'permissions' => Permission::withCount('users')->orderBy('group')->orderBy('name')->get()->groupBy('group'),
        ]);
    }

    protected function form(Role $role): View
    {
        return view('admin.roles.form', [
            'role' => $role,
            'permissions' => Permission::orderBy('group')->orderBy('name')->get()->groupBy('group'),
        ]);
    }

    protected function rules(?Role $role = null): array
    {
        return [
            'name' => ['required', 'alpha_dash', 'max:64', Rule::unique('roles')->ignore($role?->id)],
            'display_name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'permissions' => ['array'],
            'permissions.*' => ['exists:permissions,id'],
        ];
    }
}
