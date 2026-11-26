<?php

namespace Database\Seeders;

use App\Models\PlantCategory;
use Illuminate\Database\Seeder;

class PlantCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'شجرة',           'icon' => 'tabler-tree',      'color' => '#2E7D32'],
            ['name' => 'شجرة مثمرة',     'icon' => 'tabler-apple',     'color' => '#C62828'],
            ['name' => 'نخلة',           'icon' => 'tabler-plant-2',   'color' => '#8D6E63'],
            ['name' => 'شجيرة',          'icon' => 'tabler-plant',     'color' => '#388E3C'],
            ['name' => 'زهور',           'icon' => 'tabler-flower',    'color' => '#EC407A'],
            ['name' => 'نبات زينة',      'icon' => 'tabler-plant',     'color' => '#AB47BC'],
            ['name' => 'أعشاب طبية',     'icon' => 'tabler-leaf',      'color' => '#43A047'],
            ['name' => 'أعشاب عطرية',    'icon' => 'tabler-leaf-2',    'color' => '#7CB342'],
            ['name' => 'صبّار',          'icon' => 'tabler-cactus',    'color' => '#00897B'],
            ['name' => 'نجيل وعشب',      'icon' => 'tabler-plant',     'color' => '#9CCC65'],
            ['name' => 'نبات متسلّق',    'icon' => 'tabler-plant-2',   'color' => '#558B2F'],
            ['name' => 'نبات مائي',      'icon' => 'tabler-droplet',   'color' => '#039BE5'],
            ['name' => 'خضروات',         'icon' => 'tabler-carrot',    'color' => '#F57C00'],
            ['name' => 'شتلة',           'icon' => 'tabler-seeding',   'color' => '#66BB6A'],
        ];

        foreach ($categories as $category) {
            PlantCategory::firstOrCreate(
                ['name' => $category['name']],
                [...$category, 'is_active' => true],
            );
        }
    }
}
