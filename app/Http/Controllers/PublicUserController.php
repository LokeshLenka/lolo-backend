<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicUserRequest;
use App\Http\Requests\UpdatePublicUserRequest;
use App\Models\PublicUser;
use Exception;
use Gate;
use Illuminate\Support\Facades\DB;

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
            PublicUser::create([
                'reg_num' => $validatedData['reg_num'],
                'name' => $validatedData['name'],
                'gender' => $validatedData['gender'],
                'year' => $validatedData['year'],
                'branch' => $validatedData['branch'],
                'phone_no' => $validatedData['phone_no'],
            ]);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => $e->getMessage(),
            ]);
        }
        return response()->json([
            'message' => 'User registered successfully'
        ], 201);
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

        unset('reg_num', $validatedData['reg_num']);


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
}
