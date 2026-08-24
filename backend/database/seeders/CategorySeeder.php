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
                'name' => 'Teknologi',
                'slug' => 'teknologi',
            ],
            [
                'name' => 'Seni & Kerajinan',
                'slug' => 'seni-kerajinan',
            ],
            [
                'name' => 'Lingkungan',
                'slug' => 'lingkungan',
            ],
            [
                'name' => 'Sosial & Kemanusiaan',
                'slug' => 'sosial-kemanusiaan',
            ],
            [
                'name' => 'Pendidikan',
                'slug' => 'pendidikan',
            ],
            [
                'name' => 'Kesehatan',
                'slug' => 'kesehatan',
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
