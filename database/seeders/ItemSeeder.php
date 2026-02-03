<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Item;
use App\Models\Category;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        // First create a category if none exists
        $category = Category::firstOrCreate(
            ['name' => 'Alat Kesehatan'],
            ['description' => 'Kategori untuk peralatan kesehatan umum']
        );

        // Insert sample items
        Item::create([
            'category_id' => $category->id,
            'name' => 'Tensimeter Digital',
            'description' => 'Alat pengukur tekanan darah digital',
            'stock' => 5,
            'available_stock' => 0, // Stok habis untuk testing waiting list!
            'condition' => 'baik',
            'is_active' => true,
        ]);

        Item::create([
            'category_id' => $category->id,
            'name' => 'Termometer Infrared',
            'description' => 'Termometer non-contact infrared',
            'stock' => 10,
            'available_stock' => 8,
            'condition' => 'baik',
            'is_active' => true,
        ]);

        Item::create([
            'category_id' => $category->id,
            'name' => 'Stetoskop',
            'description' => 'Stetoskop untuk pemeriksaan jantung dan paru',
            'stock' => 3,
            'available_stock' => 3,
            'condition' => 'baik',
            'is_active' => true,
        ]);
    }
}
