<?php

namespace App\Http\Controllers;

use App\Domain\Commercial\ModuleAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffWorkspaceController
{
    public function index(Request $request): View
    {
        $staff = $request->user('staff');

        return view('staff.home', [
            'restaurant' => $staff->restaurant,
            'staff' => $staff,
            'branchRequests' => $staff->role === 'owner'
                ? $staff->branches()->where('branches.approval_status', '!=', 'approved')->orderBy('name')->get()
                : collect(),
            'branches' => $staff->activeBranches()->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, string $branch, ModuleAccess $access): View
    {
        $staff = $request->user('staff');

        return view('staff.branch', [
            'restaurant' => $staff->restaurant,
            'branch' => $staff->activeBranches()->findOrFail($branch),
            'modules' => $access->forBranch($staff, $branch),
        ]);
    }

    public function module(Request $request, string $branch, string $module, ModuleAccess $access): View
    {
        $staff = $request->user('staff');
        $outlet = $staff->activeBranches()->findOrFail($branch);
        $licensed = $access->forBranch($staff, $branch)->firstWhere('code', $module);
        abort_unless($licensed, 403);

        return view('staff.module', ['module' => $licensed, 'branch' => $outlet]);
    }
}
