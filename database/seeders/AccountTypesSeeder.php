<?php

namespace Database\Seeders;

use App\Models\AccountType;
use Illuminate\Database\Seeder;

class AccountTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'فرد', 'icon' => 'tabler-user'],
            ['name' => 'مؤسسة', 'icon' => 'tabler-building'],
            ['name' => 'منظمة', 'icon' => 'tabler-users-group'],
            ['name' => 'مدرسة', 'icon' => 'tabler-school'],
            ['name' => 'جامعة', 'icon' => 'tabler-building-bank'],
        ];

        foreach ($types as $type) {
            AccountType::firstOrCreate(['name' => $type['name']], $type);
        }
    }
}
