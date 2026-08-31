<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        DB::table('categories')->truncate();

        $categories = [
            ['name' => 'Teknologi', 'slug' => 'teknologi'],
            ['name' => 'Seni & Kerajinan', 'slug' => 'seni-kerajinan'],
            ['name' => 'Lingkungan', 'slug' => 'lingkungan'],
            ['name' => 'Sosial & Kemanusiaan', 'slug' => 'sosial-kemanusiaan'],
            ['name' => 'Pendidikan', 'slug' => 'pendidikan'],
            ['name' => 'Kesehatan', 'slug' => 'kesehatan'],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
