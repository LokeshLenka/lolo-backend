<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicRegistrationRequest;
use App\Http\Requests\UpdatePublicRegistrationRequest;
use App\Models\PublicRegistration;

class PublicRegistrationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StorePublicRegistrationRequest $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(PublicRegistration $publicRegistration)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePublicRegistrationRequest $request, PublicRegistration $publicRegistration)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PublicRegistration $publicRegistration)
    {
        //
    }
}
