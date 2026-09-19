<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'username' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call([
            ArticleTestSeeder::class,
        ]);

        // دالة مساعدة لتنسيق التواريخ داخل المصفوفت
        $formatDates = function (array $records) {
            return array_map(function ($record) {
                foreach ($record as $key => $value) {
                    if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $value)) {
                        $record[$key] = date('Y-m-d H:i:s', strtotime($value));
                    }
                }

                return $record;
            }, $records);
        };

        $totalCustomers = 2000;
        $chunkSize = 2000;

        for ($i = 0; $i < ($totalCustomers / $chunkSize); $i++) {
            $data = Customer::factory()->count($chunkSize)->make()->toArray();
            Customer::insert($formatDates($data));
        }

        $totalSlots = 2000;

        for ($i = 0; $i < ($totalSlots / $chunkSize); $i++) {
            $data = Slot::factory()->count($chunkSize)->make()->toArray();
            Slot::insert($formatDates($data));
        }
    }
}
