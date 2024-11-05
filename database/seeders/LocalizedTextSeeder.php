<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;


class LocalizedTextSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // create couple of new localized text without using factory
        \App\Models\LocalizedText::factory()->create(
            [
                'key' => 'hello',
                'text' => 'Hello',
                'locale' => 'en',
                'group' => 'general',
            ],

        );

        \App\Models\LocalizedText::factory()->create(
            [
                'key' => 'hello',
                'text' => 'درود',
                'locale' => 'fa',
                'group' => 'general',
            ],

        );

    }
            // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

}
