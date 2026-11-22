<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffWorkspaceController
{
    public function index(Request $request): View
    {
        $staff = $request->user('staff');

        return view('staff.home', [
            'restaurant' => $staff->restaurant,
            'branches' => $staff->branches()->where('branches.is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, string $branch): View
    {
        $staff = $request->user('staff');

        return view('staff.branch', [
            'restaurant' => $staff->restaurant,
            'branch' => $staff->branches()->where('branches.is_active', true)->findOrFail($branch),
        ]);
    }
}
