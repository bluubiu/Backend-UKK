<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

use App\Traits\LogsActivity;

class CategoryController extends Controller
{
    use LogsActivity;
   
    public function index()
    {
        $categories = Category::withCount('items')->latest()->get();
        return response()->json($categories);
    }

    
    public function create()
    {
        //
    }

    
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $category = Category::create($request->only(['name', 'description']));

        $this->logActivity('Create Category', "Admin membuat kategori: {$category->name}", null, $category->toArray());

        return response()->json($category, 201);
    }

   
    public function show(string $id)
    {
        $category = Category::findOrFail($id);
        return response()->json($category);
    }

    
    public function edit(string $id)
    {
        //
    }


    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
        ]);

        $oldValues = $category->only(['name', 'description']);
        $category->update($request->only(['name', 'description']));
        $category->refresh();

        // Log the activity
        $this->logActivity('Update Category', "Admin mengupdate kategori: {$category->name}", $oldValues, $category->only(['name', 'description']));

        return response()->json($category);
    }

    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);
        
        if ($category->items()->count() > 0) {
            return response()->json(['message' => 'Kategori tidak dapat dihapus karena masih memiliki barang terkait.'], 400);
        }

        $oldValues = $category->toArray();
        $category->delete(); // Soft delete

        $this->logActivity('Delete Category', "Admin menghapus kategori: {$category->name}", $oldValues);

        return response()->json(['message' => 'Kategori berhasil dihapus']);
    }
}
