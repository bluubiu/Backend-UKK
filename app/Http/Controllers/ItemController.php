<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

use App\Traits\LogsActivity;

class ItemController extends Controller
{
    use LogsActivity;
    /**
     * list alat (dengan filter kategori, status)
     */
    public function index(Request $request)
    {
        $query = Item::with('category');

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('condition')) {
            $query->where('condition', $request->condition);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }

        return response()->json($query->get());
    }

    /**
     * alat tersedia untuk dipinjam
     */
    public function available(Request $request)
    {
        // Business rule: Alat dengan condition "rusak berat" tidak muncul
        // Business rule: available_stock > 0
        $query = Item::with('category')
            ->where('is_active', true)
            ->where('condition', '!=', 'rusak berat');

        if ($request->has('category_id') && $request->category_id != 'all') {
            $query->where('category_id', $request->category_id);
        }

        // Show newest items first
        $items = $query->orderBy('created_at', 'desc')->get();

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120', 
            'stock' => 'required|integer|min:0',
            'condition' => 'required|string',
            'is_active' => 'boolean'
        ]);

        // Logic: available_stock starts same as stock
        $data = $request->all();
        $data['available_stock'] = $data['stock'];

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->storeAs('items', $imageName, 'public');
            $data['image'] = 'items/' . $imageName;
        }

        $item = Item::create($data);

        // Log the activity
        $this->logActivity('Create Item', "Admin created item: {$item->name}", null, $item->toArray());

        return response()->json($item, 201);
    }

    public function show($id)
    {
        return response()->json(Item::with('category')->findOrFail($id));
    }

    public function update(Request $request, $id)
    {
        $item = Item::findOrFail($id);

        $request->validate([
            'category_id' => 'exists:categories,id',
            'name' => 'string|max:255',
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120', // max 5MB
            'stock' => 'integer|min:0',
            // Note: updating stock might require re-calculating available_stock logic
            // providing simple update for now, advanced logic for stock adjustment usually handled separately
            'condition' => 'string',
        ]);

        $data = $request->all();

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($item->image) {
                \Storage::disk('public')->delete($item->image);
            }

            $image = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->storeAs('items', $imageName, 'public');
            $data['image'] = 'items/' . $imageName;
        }

        $oldValues = $item->getOriginal();
        
        // Logical check: If stock is updated, sync available_stock
        if (isset($data['stock'])) {
            $stockDiff = (int)$data['stock'] - (int)$item->stock;
            // new available_stock = old available_stock + difference in total stock
            $data['available_stock'] = (int)$item->available_stock + $stockDiff;
            
            // Ensure available_stock doesn't go negative or exceed new stock (safety)
            if ($data['available_stock'] < 0) $data['available_stock'] = 0;
            if ($data['available_stock'] > $data['stock']) $data['available_stock'] = $data['stock'];
        }

        $item->update($data);

        // Log the activity
        $this->logActivity('Update Item', "Admin mengupdate item: {$item->name}", $oldValues, $item->getChanges());

        return response()->json($item);
    }

    public function destroy($id)
    {
        $item = Item::findOrFail($id);

        // Delete image if exists
        if ($item->image) {
            \Storage::disk('public')->delete($item->image);
        }

        $oldValues = $item->toArray();
        $item->delete();

        // Log the activity
        $this->logActivity('Hapus Item', "Admin menghapus item: {$item->name}", $oldValues);

        return response()->json(['message' => 'Item berhasil dihapus']);
    }
}
