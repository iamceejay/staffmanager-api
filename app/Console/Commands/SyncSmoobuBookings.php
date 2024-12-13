<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\SmoobuJob;
use Illuminate\Support\Str;

class SyncSmoobuBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staffmanager:sync-smoobu-bookings {from} {to} {page} {size} {apartmentID}';

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

        $bookings = Http::acceptJson()->withQueryParameters([
            'pageSize'          => $this->argument('size'),
            'page'              => $this->argument('page'),
            'from'              => $this->argument('from'),
            'to'                => $this->argument('to'),
            'excludeBlocked'    => true,
            'apartmentId'       => $this->argument('apartmentID'),
        ])->withHeaders([
            'Api-Key'       => $key,
            'Cache-Control' => 'no-cache'
        ])->get('https://login.smoobu.com/api/reservations');

        echo 'Total Items: ' . $bookings['total_items'] . ' ';
        echo 'Page Count: ' . $bookings['page_count'] . ' ';

        if($bookings['total_items']) {
            $bookings = $bookings['bookings'];

            foreach($bookings as $booking) {
                if(strtotime($booking['arrival']) < strtotime($this->argument('from')) ||  strtotime($booking['arrival']) > strtotime($this->argument('to'))) {
                    continue;
                }

                echo 'Checkout: ' . $booking['check-out'];
                
                $location = Http::acceptJson()->withHeaders([
                    'Api-Key'       => $key,
                    'Cache-Control' => 'no-cache'
                ])->get('https://login.smoobu.com/api/apartments/' . $booking['apartment']['id']);

                if(isset($location['location']) && isset($location['location']['city'])) {
                    $location = $location['location'];
                    $location = ltrim(implode(' ', $location));
                } else {
                    $location = $booking['apartment']['name'];
                }

                $exists = SmoobuJob::where('smoobu_id', $booking['id'])->first();

                if($exists) {
                    SmoobuJob::where('smoobu_id', $booking['id'])->update([
                        'uuid'          => Str::uuid(),
                        'smoobu_id'     => $booking['id'],
                        'title'         => $booking['apartment']['name'],
                        'start'         => $booking['departure'] . ' ' . (isset($booking['check-out']) && $booking['check-out'] !== 'NULL' ? $booking['check-out'] . ':00' : '11:00:00'),
                        'end'           => $booking['departure'] . ' ' . (isset($booking['check-in']) && $booking['check-in'] !== 'NULL' ? $booking['check-in'] . ':00' : '15:00:00'),
                        'location'      => $location,
                        'description'   => $booking['notice']
                    ]);
                } else {
                    SmoobuJob::create([
                        'uuid'              => Str::uuid(),
                        'smoobu_id'         => $booking['id'],
                        'title'             => $booking['apartment']['name'],
                        'start' => $booking['departure'] . ' ' . (isset($booking['check-out']) && $booking['check-out'] !== 'NULL' ? $booking['check-out'] . ':00' : '11:00:00'),
                        'end'   => $booking['departure'] . ' ' . (isset($booking['check-in']) && $booking['check-in'] !== 'NULL' ? $booking['check-in'] . ':00' : '15:00:00'),
                        'location'          => $location,
                        'description'       => $booking['notice'],
                        'status'            => 'available',
                        'smoobu_created_at' => $booking['created-at'],
                        'arrival'           => $booking['arrival']
                    ]);
                }
            }
        }
    }
}
