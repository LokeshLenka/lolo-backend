<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicUserRequest;
use App\Http\Requests\UpdatePublicUserRequest;
use App\Models\PublicUser;
use Exception;
use Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PublicUserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        Gate::authorize('viewAny', PublicUser::class);

        $publicUsers = PublicUser::all();

        if ($publicUsers->isEmpty()) {
            return response()->json([
                'users' => $publicUsers
            ], 404);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePublicUserRequest $request)
    {
        $validatedData = $request->validated();

        try {
            DB::beginTransaction();
            $user = PublicUser::create([
                'uuid' => (string) Str::uuid(),
                'reg_num' => $validatedData['reg_num'],
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'gender' => $validatedData['gender'],
                'year' => $validatedData['year'],
                'branch' => $validatedData['branch'],
                'phone_no' => $validatedData['phone_no'],
                'college_hostel_status' => $validatedData['college_hostel_status'],
            ]);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return $this->respondError($e->getMessage(), 500);
        }
        return $this->respondSuccess($user, 'User created successfully', 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(PublicUser $publicUser,)
    {
        // Gate::authorize('view', $publicUser,PublicUser::class);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePublicUserRequest $request, PublicUser $publicUser)
    {
        Gate::authorize('update', PublicUser::class);

        $validatedData = $request->validated();

        unset($validatedData['reg_num']);

        try {
            DB::beginTransaction();

            $publicUser->fill($validatedData);
            $publicUser->save();

            DB::commit();
        } catch (Exception $e) {

            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'User Profile updated successfully'
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PublicUser $publicUser)
    {
        Gate::authorize('delete', PublicUser::class);
        try {
            DB::beginTransaction();

            $publicUser->delete();

            DB::commit();
        } catch (Exception $e) {

            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage(),
            ]);
        }
        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    public function getUserByRegNum(string $reg_num)
    {
        $reg_num = strtoupper($reg_num);
        Log::info("Fetching user with reg_num: $reg_num");
        $publicUser = PublicUser::where('reg_num', $reg_num)->first();

        Log::info('User fetch result', ['user' => $publicUser]);

        if (!$publicUser) {
            return $this->respondError('User not found', 404);
        }

        // CRITICAL FIX: Change 'null' to '$publicUser'
        return $this->respondSuccess($publicUser, 'User retrieved successfully', 200);
    }
}
