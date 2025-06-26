<?php

namespace App\Http\Controllers;

use App\Models\MusicProfile;
use Illuminate\Http\Request;

class MusicProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $musicProfiles = MusicProfile::query()
            ->when($request->has('type'), fn($q) => $q->type($request->type))
            ->when($request->has('sub_role'), fn($q) => $q->type($request->type))
            // ->when($request->has('active'), fn($q) => $q->active($request->boolean('active')))
            ->with('user') // include related user info
            ->paginate(20);

        return response()->json($musicProfiles);
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
    public function show(MusicProfile $musicProfile)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, MusicProfile $musicProfile)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(MusicProfile $musicProfile)
    {
        //
    }
}
