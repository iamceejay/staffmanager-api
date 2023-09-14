<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SyncSmoobuBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staffmanager:sync-smoobu-bookings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Smoobu bookings starting from current month.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $key = getenv('SMOOBU_KEY');

        $bookings = Http::acceptJson()->withHeaders([
            'Api-Key'       => $key,
            'Cache-Control' => 'no-cache'
        ])->get('https://login.smoobu.com/api/reservations');

        var_dump($bookings);
    }
}
