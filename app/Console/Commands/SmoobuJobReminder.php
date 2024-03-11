<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\SmoobuJob;
use App\Models\User;
use App\Jobs\SendMessageJob;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SmoobuJobReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'staffmanager:smoobu-job-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send job reminder scheduled for the current date.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $users = User::get();

        $message = 'staffmanager test message.';
        $recipient = '+4366475019284 ';

        $send_message = SendMessageJob::dispatch($recipient, $message);

        foreach($users as $user) {
            $message = 'staffmanager test message.';

            echo "Sending to $user->first_name $user->last_name \r\n";

            $send_message = SendMessageJob::dispatch($user->phone_number, $message);

            echo "MESSAGE SENT! \r\n";
            echo "\r\n";
        }

        return false;

        $jobs = SmoobuJob::with('user')
                ->where('status', 'taken')
                ->whereNotNull('staff_id')
                ->whereDate('start', Carbon::today())
                ->get();

        if($jobs) {
            foreach ($jobs as $job) {
                $job_start = Carbon::parse($job->start);
                $job_start = $job_start->format('H:i');

                $message = 'Kurze Erinnerung für deinen Dienst heute um ' . $job_start . ' im Apartment ' . $job->title;
                $recipient = $job->user->phone_number;

                $send_message = SendMessageJob::dispatch($recipient, $message);
            }
        }
    }
}
