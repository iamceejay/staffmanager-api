<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SmoobuJob;

class SmoobuJobSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        SmoobuJob::factory()->create([
            'start' => '2023-08-31 04:00',
            'end'   => '2023-08-31 05:00'
        ]);

        SmoobuJob::factory()->create([
            'start' => '2023-08-30 04:00',
            'end'   => '2023-08-30 05:00'
        ]);

        SmoobuJob::factory()->create([
            'status'    => 'taken',
            'start'     => '2023-08-30 10:00',
            'end'       => '2023-08-30 11:00'
        ]);

        SmoobuJob::factory()->create([
            'status'    => 'taken',
            'start'     => '2023-08-31 10:00',
            'end'       => '2023-08-31 11:00'
        ]);
    }
}
