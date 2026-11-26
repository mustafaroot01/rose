<?php

namespace Database\Seeders;

use App\Models\Plant;
use App\Models\PlantCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ===== المشرف الافتراضي =====
        $admin = User::updateOrCreate(
            ['phone' => '+9647710030147'],
            [
                'name' => 'مشرف النظام',
                'email' => 'admin@nabtah.test',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'status' => 'active',
                'phone_verified_at' => now(),
            ]
        );

        // ===== مواطن تجريبي =====
        $citizen = User::updateOrCreate(
            ['phone' => '+966555555555'],
            [
                'name' => 'مواطن تجريبي',
                'password' => Hash::make('password'),
                'role' => 'citizen',
                'status' => 'active',
                'phone_verified_at' => now(),
            ]
        );

        // ===== تصنيفات النباتات =====
        $categories = [
            ['name' => 'شجرة', 'icon' => 'tabler-tree', 'color' => '#2E7D32'],
            ['name' => 'زهرة', 'icon' => 'tabler-flower', 'color' => '#E91E63'],
            ['name' => 'شجيرة', 'icon' => 'tabler-plant', 'color' => '#8BC34A'],
            ['name' => 'نخلة', 'icon' => 'tabler-palette', 'color' => '#795548'],
            ['name' => 'عشب', 'icon' => 'tabler-plant-2', 'color' => '#4CAF50'],
        ];

        foreach ($categories as $cat) {
            PlantCategory::updateOrCreate(['name' => $cat['name']], $cat);
        }

        // ===== نباتات تجريبية (حول الرياض) =====
        if (Plant::count() === 0) {
            $catIds = PlantCategory::pluck('id')->all();
            $baseLat = 24.7136;   // الرياض
            $baseLng = 46.6753;
            $names = ['نخلة بلدي', 'شجرة سدر', 'زهرة الرازقي', 'شجيرة الآس', 'عشب برمودا', 'شجرة الزيتون', 'نبات الصبار', 'شجرة الليمون'];

            foreach ($names as $i => $name) {
                Plant::create([
                    'user_id' => $citizen->id,
                    'category_id' => $catIds[array_rand($catIds)],
                    'name' => $name,
                    'description' => 'نبتة مسجّلة من قبل المواطن ضمن البيانات التجريبية.',
                    'latitude' => $baseLat + (mt_rand(-400, 400) / 10000),
                    'longitude' => $baseLng + (mt_rand(-400, 400) / 10000),
                    'status' => $i % 3 === 0 ? 'pending' : 'approved',
                ]);
            }
        }
    }
}
