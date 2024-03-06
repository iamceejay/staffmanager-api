<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SmoobuJob;
use Illuminate\Support\Facades\Http;

class RemoveBlockedBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staffmanager:remove-blocked-bookings';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up blocked bookings.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $key = getenv('SMOOBU_KEY');

        $jobs = SmoobuJob::get();

        foreach($jobs as $job) {
            $booking = Http::acceptJson()->withHeaders([
                'Api-Key'       => $key,
                'Cache-Control' => 'no-cache'
            ])->get('https://login.smoobu.com/api/reservations/' . $job->smoobu_id);

            if($booking['is-blocked-booking']) {
                SmoobuJob::where('smoobu_id', $job->smoobu_id)->delete();
                echo 'Deleted Job with smoobu_id ' . $job->smoobu_id;
            }
        }
    }
}
