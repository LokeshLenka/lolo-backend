<?php

namespace App\Http\Controllers;

use App\Models\TeamProfile;
use Illuminate\Http\Request;

class TeamProfileController extends Controller
{
     /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $teamProfiles = TeamProfile::with('images')->get();

        if ($teamProfiles->isEmpty()) {
            return $this->respondError('No Team Details Found', 404);
        }

        return $this->respondSuccess($teamProfiles, 'Team Details Fetched Successfully', 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            // Validate incoming request data
            $validatedData = $request->validate([
                'user_id' => 'required|exists:users,id',
                'job_title' => 'required|string|max:100',
                'job_description' => 'nullable|string|max:255',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:4096', // Example for image upload
            ]);

            // Generate a UUID for the new team profile
            $validatedData['uuid'] = Str::uuid();

            // Create a new team profile
            $teamProfile = TeamProfile::create($validatedData);

            $teamProfile->load('images');

            if ($teamProfile) {
                return $this->respondSuccess($teamProfile, 'Team Profile Created Successfully', 201);
            } else {
                return $this->respondError('Failed to Create Team Profile', 500);
            }

            // Catch any exceptions that occur during the process
        } catch (\Exception $e) {
            return $this->respondError('An error occurred: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $uuid)
    {
        $teamProfile = TeamProfile::where('uuid', $uuid)->first();

        if ($teamProfile) {
            return $this->respondSuccess($teamProfile, 'Team Profile Fetched Successfully', 200);
        } else {
            return $this->respondError('No Team Profile Found', 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TeamProfile $teamProfile)
    {
        try {
            return DB::transaction(function () use ($request, $teamProfile) {
                $validatedData = $request->validate([
                    'job_title' => 'sometimes|required|string|max:100',
                    'job_description' => 'sometimes|nullable|string|max:255',
                ]);

                $teamProfile->update($validatedData);

                return $this->respondSuccess($teamProfile, 'Team Profile Updated Successfully', 200);
            });
        } catch (\Exception $e) {
            return $this->respondError('Failed to update team profile details', 500, $e->getMessage());
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $uuid)
    {
        $teamProfile = TeamProfile::where('uuid', $uuid)->first();

        try {
            if ($teamProfile) {
                $teamProfile->delete();
                return $this->respondSuccess(null, 'Team Profile deleted successfully', 204);
            }
        } catch (\Exception $e) {
            return $this->respondError('Unable to delete the team profile', 500, $e->getMessage());
        }
    }
}
