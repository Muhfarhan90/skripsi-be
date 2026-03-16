<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Technology',
                'slug' => 'technology',
                'description' => 'All about the latest in technology.',
            ],
            [
                'name' => 'Health',
                'slug' => 'health',
                'description' => 'Tips and news about health and wellness.',
            ],
            [
                'name' => 'Travel',
                'slug' => 'travel',
                'description' => 'Guides and stories from around the world.',
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
