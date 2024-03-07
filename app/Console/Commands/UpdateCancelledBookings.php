<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SmoobuJob;
use Illuminate\Support\Facades\Http;

class UpdateCancelledBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staffmanager:update-cancelled-bookings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updated status of jobs that were canceled on Smoobu.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $key = getenv('SMOOBU_KEY');

        $jobs = SmoobuJob::get();

        foreach($jobs as $job) {
            echo "Smoobu ID:  $job->smoobu_id \r\n";

            $booking = Http::acceptJson()->withHeaders([
                'Api-Key'       => $key,
                'Cache-Control' => 'no-cache'
            ])->get('https://login.smoobu.com/api/reservations/' . $job->smoobu_id);

            if($booking->getStatusCode() === 404) {
                continue;
            }

            if($booking['type'] === 'cancellation') {
                SmoobuJob::where('smoobu_id', $job->smoobu_id)->update([
                    'status' => 'cancelled'
                ]);

                echo "Updated Job with smoobu_id $job->smoobu_id \r\n";
            }
        }
    }
}
