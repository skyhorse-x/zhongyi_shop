<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    /**
     * 获取角色列表
     */
    public function index(Request $request)
    {
        $roles = Role::withCount('admins')
            ->orderBy('id', 'asc')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $roles,
        ]);
    }

    /**
     * 获取所有角色（用于下拉选择）
     */
    public function all()
    {
        $roles = Role::where('status', 1)
            ->orderBy('id', 'asc')
            ->get(['id', 'name', 'code']);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $roles,
        ]);
    }

    /**
     * 创建角色
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:50',
            'code' => 'required|string|max:50|unique:roles,code',
            'description' => 'nullable|string|max:200',
        ]);

        $role = Role::create([
            'name' => $request->input('name'),
            'code' => $request->input('code'),
            'description' => $request->input('description', ''),
            'status' => 1,
        ]);

        // 同步权限
        if ($request->has('permissions')) {
            $role->permissions()->sync($request->input('permissions', []));
        }

        return response()->json([
            'code' => 0,
            'message' => '创建成功',
            'data' => $role,
        ]);
    }

    /**
     * 获取角色详情
     */
    public function show($id)
    {
        $role = Role::with('permissions')->findOrFail($id);

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $role,
        ]);
    }

    /**
     * 更新角色
     */
    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        // 超级管理员角色不允许修改
        if ($role->code === 'super_admin') {
            return response()->json([
                'code' => 400,
                'message' => '超级管理员角色不可修改',
            ], 400);
        }

        $request->validate([
            'name' => 'required|string|max:50',
            'description' => 'nullable|string|max:200',
        ]);

        $role->update([
            'name' => $request->input('name'),
            'description' => $request->input('description', ''),
        ]);

        // 同步权限
        if ($request->has('permissions')) {
            $role->permissions()->sync($request->input('permissions', []));
        }

        return response()->json([
            'code' => 0,
            'message' => '更新成功',
            'data' => $role,
        ]);
    }

    /**
     * 删除角色
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        // 超级管理员角色不允许删除
        if ($role->code === 'super_admin') {
            return response()->json([
                'code' => 400,
                'message' => '超级管理员角色不可删除',
            ], 400);
        }

        // 检查是否有管理员使用该角色
        if ($role->admins()->count() > 0) {
            return response()->json([
                'code' => 400,
                'message' => '该角色下还有管理员，无法删除',
            ], 400);
        }

        $role->permissions()->detach();
        $role->delete();

        return response()->json([
            'code' => 0,
            'message' => '删除成功',
        ]);
    }

    /**
     * 切换角色状态
     */
    public function toggleStatus($id)
    {
        $role = Role::findOrFail($id);

        if ($role->code === 'super_admin') {
            return response()->json([
                'code' => 400,
                'message' => '超级管理员角色不可禁用',
            ], 400);
        }

        $role->status = $role->status === 1 ? 0 : 1;
        $role->save();

        return response()->json([
            'code' => 0,
            'message' => '操作成功',
            'data' => $role,
        ]);
    }

    /**
     * 获取权限列表
     */
    public function permissions()
    {
        $permissions = Permission::orderBy('module', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->groupBy('module');

        return response()->json([
            'code' => 0,
            'message' => 'success',
            'data' => $permissions,
        ]);
    }
}
