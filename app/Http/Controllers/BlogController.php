<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use Auth;
use Illuminate\Http\Request;
use App\Http\Requests\StoreBlogRequest;

class BlogController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $blogs = Blog::where('username', Auth::user()->getUserName())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        if ($blogs->isEmpty()) {
            return response()->json([
                'message' => 'No blogs found.',
            ]);
        }

        return response()->json([
            'message' => 'Successfully retrieved all blogs',
            'blogs' => $blogs
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreBlogRequest $request)
    {

    }

    /**
     * Display the specified resource.
     */
    public function show(Blog $blog)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Blog $blog)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Blog $blog)
    {
        //
    }
}
