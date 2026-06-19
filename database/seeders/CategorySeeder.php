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
                'name' => 'Ilmu Komputer',
                'slug' => 'ilmu-komputer',
                'description' => 'Kursus seputar pemrograman, pengembangan web, dan dasar teknologi informasi.',
            ],
            [
                'name' => 'Kedokteran',
                'slug' => 'kedokteran',
                'description' => 'Kursus pengantar untuk memahami konsep dasar kedokteran dan kesehatan klinis.',
            ],
            [
                'name' => 'Pertanian',
                'slug' => 'pertanian',
                'description' => 'Kursus tentang budidaya, teknologi pertanian, dan pengelolaan lahan produktif.',
            ],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(['slug' => $category['slug']], $category);
        }
    }
}
