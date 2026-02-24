<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

use App\Traits\LogsActivity;

class CategoryController extends Controller
{
    use LogsActivity;
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $categories = Category::withCount('items')->latest()->get();
        return response()->json($categories);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $category = Category::create($request->only(['name', 'description']));

        // Log the activity
        $this->logActivity('Buat Kategori', "Admin membuat kategori: {$category->name}", null, $category->toArray());

        return response()->json($category, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $category = Category::findOrFail($id);
        return response()->json($category);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $oldValues = $category->getOriginal();
        $category->update($request->only(['name', 'description']));

        // Log the activity
        $this->logActivity('Update Kategori', "Admin mengupdate kategori: {$category->name}", $oldValues, $category->getChanges());

        return response()->json($category);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);
        $oldValues = $category->toArray();
        $category->delete(); // Soft delete

        // Log the activity
        $this->logActivity('Hapus Kategori', "Admin menghapus kategori: {$category->name}", $oldValues);

        return response()->json(['message' => 'Kategori berhasil dihapus']);
    }
}
