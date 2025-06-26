<?php

namespace App\Http\Controllers;

use App\Models\ManagementProfile;
use Illuminate\Http\Request;

class ManagementProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $managementProfiles = ManagementProfile::query()
            ->when($request->has('type'), fn($q) => $q->type($request->type))
            ->when($request->has('sub_role'), fn($q) => $q->subRole($request->sub_role))
            ->when($request->has('year'), fn($q) => $q->Year($request->year))
            ->when($request->has('sub_role'), fn($q) => $q->subRole($request->sub_role))
            // ->when($request->has('active'), fn($q) => $q->active($request->boolean('active')))
            ->with('user') // include related user info
            ->paginate(20);

        return response()->json($managementProfiles);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(ManagementProfile $managementProfile)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ManagementProfile $managementProfile)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ManagementProfile $managementProfile)
    {
        //
    }

    // public function showUserManagementProfile()
    // {
    //     return response()->json([
    //         'message' => 'YOu'
    //     ]);
    // }
}
