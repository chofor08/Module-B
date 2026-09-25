<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ItemsSeeder::class
        ]);
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'gustav',
            'email' => 'gustavgt008@gmail.com',
            'password' => 12345678,
        ]);
    }
}
