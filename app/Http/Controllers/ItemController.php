<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\Request;

use App\Traits\LogsActivity;

class ItemController extends Controller
{
    use LogsActivity;
    
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

        return response()->json($query->latest()->get());
    }

    
    public function available(Request $request)
    {

        $query = Item::with('category')
            ->where('is_active', true)
            ->where('condition', '!=', 'rusak berat');

        if ($request->has('category_id') && $request->category_id != 'all') {
            $query->where('category_id', $request->category_id);
        }

        $items = $query->orderBy('created_at', 'desc')->get();

        return response()->json($items);
    }

    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120', 
            'stock' => 'required|integer|min:0',
            'condition' => 'required|string',
            'is_active' => 'boolean'
        ]);

        $data = $request->all();
        $data['available_stock'] = $data['stock'];

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->storeAs('items', $imageName, 'public');
            $data['image'] = 'items/' . $imageName;
        }

        $item = Item::create($data);

        $this->logActivity('Create Item', "Admin membuat item: {$item->name}", null, $item->toArray());

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
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120', 
            'stock' => 'integer|min:0',
            'condition' => 'string',
        ]);

        $data = $request->all();

        if ($request->hasFile('image')) {
            if ($item->image) {
                \Storage::disk('public')->delete($item->image);
            }

            $image = $request->file('image');
            $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();
            $image->storeAs('items', $imageName, 'public');
            $data['image'] = 'items/' . $imageName;
        }

        $fieldsToTrack = ['name', 'description', 'category_id', 'stock', 'available_stock', 'condition', 'is_active', 'image'];
        $oldValues = array_intersect_key($item->toArray(), array_flip($fieldsToTrack));
        
        if (isset($data['stock'])) {
            $stockDiff = (int)$data['stock'] - (int)$item->stock;
            $data['available_stock'] = (int)$item->available_stock + $stockDiff;
            
            if ($data['available_stock'] < 0) $data['available_stock'] = 0;
            if ($data['available_stock'] > $data['stock']) $data['available_stock'] = $data['stock'];
        }

        $item->update($data);
        $item->refresh();

        $newValues = array_intersect_key($item->toArray(), array_flip($fieldsToTrack));
        $this->logActivity('Update Item', "Admin mengupdate item: {$item->name}", $oldValues, $newValues);

        return response()->json($item);
    }

    public function destroy($id)
    {
        $item = Item::findOrFail($id);

        if ($item->image) {
            \Storage::disk('public')->delete($item->image);
        }

        $oldValues = $item->toArray();
        $item->delete();

        $this->logActivity('Delete Item', "Admin menghapus item: {$item->name}", $oldValues);

        return response()->json(['message' => 'Item berhasil dihapus']);
    }
}
