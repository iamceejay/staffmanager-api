<?php

namespace App\Http\Controllers\Job;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SmoobuJob;
use Auth;
use App\Http\Resources\SmoobuJobCollection;
use App\Jobs\SendMessageJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\ConfirmBooking;
use Mail;
use App\Models\Invoice;

class SmoobuJobController extends Controller
{
    public function index(Request $request) {
        $jobs = SmoobuJob::with('user');

        if($request->keyword) {
            $jobs = $jobs->where('uuid', $request->keyword)
                    ->orWhere('title', 'LIKE', '%' . $request->keyword . '%')
                    ->orWhere('location', 'LIKE', '%' . $request->keyword . '%')
                    ->orWhere('smoobu_id', $request->keyword)
                    ->orWhereHas('user', function($query) use ($request) {
                        $query->where('first_name', 'LIKE', '%' . $request->keyword . '%')
                            ->orWhere('last_name', 'LIKE', '%' . $request->keyword . '%');
                    });
        }

        if($request->status && $request->status !== '') {
            $jobs = $jobs->where('status', $request->status);
        } else {
            $jobs = $jobs->where('status', '!=', 'cancelled');
        }

        if($request->staff) {
            $jobs = $jobs->where('staff_id', $request->staff);
        }

        if($request->sort && $request->sort !== '') {
            switch($request->sort) {
                case 'new':
                    $jobs->orderBy('created_at', 'desc');
                    break;
                case 'old':
                    $jobs->orderBy('created_at', 'asc');
                    break;
                case 'start_new':
                    $jobs->orderBy('start', 'desc');
                    break;
                case 'start_old':
                    $jobs->orderBy('start', 'asc');
                    break;
                case 'updated_old':
                    $jobs->orderBy('updated_at', 'asc');
                    break;
                case 'updated_new':
                    $jobs->orderBy('updated_at', 'desc');
                    break;
            }
        } else {
            $jobs->orderBy(DB::raw('ABS(DATEDIFF(smoobu_jobs.start, NOW()))'));
        }

        $jobs = $jobs->where('title', '!=', 'Reutte 1.OG');

        $jobs = $jobs->get();

        return response()->json([
            'status'    => 'success',
            'jobs'      => $jobs
        ], 200);
    }

    public function assigned(Request $request) {
        $jobs = SmoobuJob::where('staff_id', Auth::id());

        if($request->keyword) {
            $jobs = $jobs->where('uuid', $request->keyword)
                    ->orWhere('title', 'LIKE', '%' . $request->keyword . '%')
                    ->orWhere('location', 'LIKE', '%' . $request->keyword . '%')
                    ->orWhere('smoobu_id', $request->keyword)
                    ->orWhereHas('user', function($query) use ($request) {
                        $query->where('first_name', 'LIKE', '%' . $request->keyword . '%')
                            ->orWhere('last_name', 'LIKE', '%' . $request->keyword . '%');
                    });
        }

        $jobs = $jobs->where('status', '!=', 'cancelled');

        if($request->sort) {
            switch($request->sort) {
                case 'new':
                    $jobs->orderBy('created_at', 'desc');
                    break;
                case 'old':
                    $jobs->orderBy('created_at', 'asc');
                    break;
                case 'start_new':
                    $jobs->orderBy('start', 'desc');
                    break;
                case 'start_old':
                    $jobs->orderBy('start', 'asc');
                    break;
                case 'updated_old':
                    $jobs->orderBy('updated_at', 'asc');
                    break;
                case 'updated_new':
                    $jobs->orderBy('updated_at', 'desc');
                    break;
            }
        } else {
            $jobs->orderBy(DB::raw('ABS(DATEDIFF(smoobu_jobs.start, NOW()))'));
        }

        $jobs = $jobs->get();

        return response()->json([
            'status'    => 'success',
            'jobs'      => $jobs
        ], 200);
    }

    public function details($id) {
        $job = SmoobuJob::with('user')->where('uuid', $id)->first();

        if($job) {
            return response()->json([
                'status'    => 'success',
                'details'   => $job
            ], 200);
        }

        return response()->json([
            'status'    => 'error',
            'message'   => 'Job not found.'
        ], 404);
    }

    public function calendarJobs() {
        $jobs = SmoobuJob::with('user')
                ->whereIn('status', ['available', 'taken'])
                ->where('title', '!=', 'Reutte 1.OG')
                ->get();

        return response()->json([
            'status'    => 'success',
            'jobs'      => $jobs ? new SmoobuJobCollection($jobs) : []
        ], 200);
    }

    public function cancel(Request $request) {
        DB::beginTransaction();

        try {
            $job = SmoobuJob::with('user')->where('id', $request->id)->first();

            $update = SmoobuJob::where('id', $request->id)->update([
                'status'    => 'cancelled',
                'staff_id'  => NULL
            ]);

            if($job->staff_id) {
                $message = 'Entschuldige, dein Dienst am ' . $job->start . ' im Apartment ' . $job->title . ' wurde storniert.';
                $recipient = $job->user->phone_number;

                $send_message = SendMessageJob::dispatch($recipient, $message);
            }

            DB::commit();

            return response()->json([
                'status'    => 'success',
                'message'   => 'Job has been cancelled.',
            ], 200);
        } catch(Throwable $e) {
            DB::rollBack();

            report($e);

            return response()->json([
                'message'   => 'Internal server error.',
                'status'    => 'error'
            ], 500);
        }
    }

    public function update(Request $request) {
        DB::beginTransaction();

        try {
            $job = SmoobuJob::with('user')->where('id', $request->id)->first();
            $is_assignment = false;

            if($request->staff && ($job->staff_id !== $request->staff)) {
                $is_assignment = true;
            }

            if($request->staff == '') {
                $request->staff = null;
            }

            $jobStatus = $job->status;

            if(!$request->staff && $job->status === 'taken') {
                $jobStatus = 'available';
            }

            $update = SmoobuJob::where('id', $request->id)->update([
                'title'         => $request->title,
                'staff_id'      => $request->staff,
                'start'         => $request->start,
                'end'           => $request->end,
                'location'      => $request->location,
                'description'   => $request->description,
                'status'        => $jobStatus
            ]);

            $job = SmoobuJob::with('user')->where('id', $request->id)->first();

            if($job->staff_id) {
                if($is_assignment) {
                    $message = 'Job ' . $job->title . ' - Dein Dienst hat sich geändert für ' . $job->start . ' Bitte überprüfe ob das so passt.';
                } else {
                    $message = 'Job ' . $job->title . ' hat sich geändert. Logge dich im Staffmanager ein um zu überprüfen, ob der Termin so für dich passt.';
                }

                $recipient = $job->user->phone_number;

                $send_message = SendMessageJob::dispatch($recipient, $message);
            }

            DB::commit();

            return response()->json([
                'status'    => 'success',
                'message'   => 'Job has been updated.',
            ], 200);
        } catch(Throwable $e) {
            DB::rollBack();

            report($e);

            return response()->json([
                'message'   => 'Internal server error.',
                'status'    => 'error'
            ], 500);
        }
    }

    public function delete(Request $request) {
        DB::beginTransaction();

        try {
            $job = SmoobuJob::with('user')->where('uuid', $request->uuid)->first();

            $delete = SmoobuJob::where('uuid', $request->uuid)->delete();

            if($job->staff_id) {
                $message = 'Entschuldige, dein Dienst am ' . $job->start . ' im Apartment ' . $job->title . ' wurde storniert.';
                $recipient = $job->user->phone_number;

                $send_message = SendMessageJob::dispatch($recipient, $message);
            }

            DB::commit();

            return response()->json([
                'status'    => 'success',
                'message'   => 'Job has been deleted.',
            ], 200);
        } catch(Throwable $e) {
            DB::rollBack();

            report($e);

            return response()->json([
                'message'   => 'Internal server error.',
                'status'    => 'error'
            ], 500);
        }
    }

    public function complete(Request $request) {
        DB::beginTransaction();

        try {
            $job = SmoobuJob::with('user')->where('id', $request->id)->first();

            $update = SmoobuJob::where('id', $request->id)->update([
                'status'    => 'done',
            ]);

            if($job->staff_id) {
                $message = 'Job ' . $job->title . ' has been marked as done. Login to Staffmanager account.';
                $recipient = $job->user->phone_number;

                $send_message = SendMessageJob::dispatch($recipient, $message);
            }

            DB::commit();

            return response()->json([
                'status'    => 'success',
                'message'   => 'Job has been marked as done.',
            ], 200);
        } catch(Throwable $e) {
            DB::rollBack();

            report($e);

            return response()->json([
                'message'   => 'Internal server error.',
                'status'    => 'error'
            ], 500);
        }
    }

    public function webhook(Request $request) {
        Log::info($request);
        
        $key = getenv('SMOOBU_KEY');
        $exclude_apartments = [478239, 123946, 2093659];
        $is_excluded = false;

        try {
            DB::beginTransaction();

            if($request->action === 'newReservation') {
                $resp = Http::acceptJson()->withHeaders([
                    'Api-Key'       => $key,
                    'Cache-Control' => 'no-cache'
                ])->get('https://login.smoobu.com/api/reservations/' . $request->data['id']);

                if(in_array($resp['apartment']['id'], $exclude_apartments)) {
                    $is_excluded = true;
                }

                if($resp['is-blocked-booking']) {
                    return false;
                }

                $location = Http::acceptJson()->withHeaders([
                    'Api-Key'       => $key,
                    'Cache-Control' => 'no-cache'
                ])->get('https://login.smoobu.com/api/apartments/' . $resp['apartment']['id']);

                if(isset($location['location']) && isset($location['location']['city'])) {
                    $location = $location['location'];
                    $location = ltrim(implode(' ', $location));
                } else {
                    $location = $request->data['apartment']['name'];
                }

                SmoobuJob::create([
                    'uuid'              => Str::uuid(),
                    'smoobu_id'         => $request->data['id'],
                    'title'             => $request->data['apartment']['name'],
                    'start'             => $request->data['departure'] . ' ' . (isset($resp['check-out']) ? $resp['check-out'] . ':00' : '11:00:00'),
                    'end'               => $request->data['departure'] . ' ' . (isset($resp['check-in']) ? $resp['check-in'] . ':00' : '15:00:00'),
                    'location'          => $location,
                    'description'       => $request->data['notice'],
                    'status'            => 'available',
                    'smoobu_created_at' => $request->data['created-at'],
                    'arrival'           => $resp['arrival']
                ]);

                // if($resp['channel']['id'] !== 61551) {
                //     // Invoice
                //     $invoice_insert = Invoice::create([
                //         'smoobu_id'     => $request->data['id'],
                //         'customer_name' => $request->data['guest-name'],
                //         'arrival'       => date('Y.m.d', strtotime($request->data['arrival'])),
                //         'departure'     => date('Y.m.d', strtotime($request->data['departure'])),
                //     ]);
                // }

                if($is_excluded) {
                    SmoobuJob::where('smoobu_id', $request->data['id'])->delete();
                }
                // $invoice = $request->data;
                // $pdf = PDF::loadView(
                //     'invoice-confirmation',
                //     [
                //         'invoice'   => $invoice,
                //         'number'    => 1110 + $invoice_insert->id,
                //         'address'   => '',
                //         'customer'  => $request->data['guest-name']
                //     ]
                // );

                // Mail::send('mail.confirmation', [], function ($message) use ($pdf, $invoice_insert) {
                //     $message->to('test@email.com')
                //         ->subject('Noas Invoice')
                //         ->attachData($pdf->output(), 1110 + $invoice_insert->id . '.pdf');
                // });
            }

            if($request->action === 'cancelReservation') {
                $job = SmoobuJob::with('user')->where('smoobu_id', $request->data['id'])->first();

                $update = SmoobuJob::where('smoobu_id', $request->data['id'])->update([
                    'status'    => 'cancelled',
                    'staff_id'  => NULL
                ]);

                if($job->staff_id) {
                    $message = 'Entschuldige, dein Dienst am ' . $job->start . ' im Apartment ' . $job->title . ' wurde storniert.';
                    $recipient = $job->user->phone_number;

                    $send_message = SendMessageJob::dispatch($recipient, $message);
                }

                // Invoice
                // $invoice_data = Invoice::where('smoobu_id', $request->data['id'])->first();

                // $invoice = $request->data;
                // $pdf = PDF::loadView(
                //     'invoice-cancelled',
                //     [
                //         'invoice'   => $invoice,
                //         'number'    => 1110 + $invoice_data->id,
                //         'address'   => $invoice_data->customer_address,
                //         'customer'  => $request->data['guest-name']
                //     ]
                // );

                // Mail::send('mail.cancelled', [], function ($message) use ($pdf, $invoice_data) {
                //     $message->to('test@email.com')
                //         ->subject('Noas Invoice Cancelled')
                //         ->attachData($pdf->output(), 1110 + $invoice_data->id . '.pdf');
                // });
            }

            if($request->action === 'updateReservation') {
                $job = SmoobuJob::with('user')->where('smoobu_id', $request->data['id'])->first();

                if(!$job) {
                    return false;
                }

                $resp = Http::acceptJson()->withHeaders([
                    'Api-Key'       => $key,
                    'Cache-Control' => 'no-cache'
                ])->get('https://login.smoobu.com/api/reservations/' . $request->data['id']);

                $location = Http::acceptJson()->withHeaders([
                    'Api-Key'       => $key,
                    'Cache-Control' => 'no-cache'
                ])->get('https://login.smoobu.com/api/apartments/' . $resp['apartment']['id']);

                if(isset($location['location']) && isset($location['location']['city'])) {
                    $location = $location['location'];
                    $location = ltrim(implode(' ', $location));
                } else {
                    $location = $request->data['apartment']['name'];
                }

                $update = SmoobuJob::where('smoobu_id', $request->data['id'])->update([
                    'title'         => $request->data['apartment']['name'],
                    'start'         => $request->data['departure'] . ' ' . (isset($resp['check-out']) ? $resp['check-out'] . ':00' : '11:00:00'),
                    'end'           => $request->data['departure'] . ' ' . (isset($resp['check-in']) ? $resp['check-in'] . ':00' : '15:00:00'),
                    'location'      => $location,
                    'description'   => $request->data['notice'],
                    'arrival'       => $resp['arrival']
                ]);

                if($job->staff_id !== NULL) {
                    $message = 'Job ' . $job->title . ' hat sich geändert. Logge dich im Staffmanager ein um zu überprüfen, ob der Termin so für dich passt.';

                    $recipient = $job->user->phone_number;

                    $send_message = SendMessageJob::dispatch($recipient, $message);
                }
            }

            DB::commit();
        } catch(Throwable $e) {
            Log::error($e);
            DB::rollBack();
        }
    }

    public function staffAssignment(Request $request) {
        DB::beginTransaction();

        try {
            $job = SmoobuJob::with('user')->where('uuid', $request->uuid)->first();
            $user = Auth::user();

            $update = SmoobuJob::where('uuid', $request->uuid)->update([
                'staff_id'      => $user->id,
                'status'        => 'taken'
            ]);

            $job = SmoobuJob::with('user')->where('uuid', $request->uuid)->first();

            if($job->staff_id) {
                $message = 'Job ' . $job->title . ' - Du, wurdest soeben für den Dienst am ' . $job->start . ' eingetragen.';

                $recipient = $job->user->phone_number;

                $send_message = SendMessageJob::dispatch($recipient, $message);
            }

            DB::commit();

            return response()->json([
                'status'    => 'success',
                'message'   => 'Job has been updated.',
            ], 200);
        } catch(Throwable $e) {
            DB::rollBack();

            report($e);

            return response()->json([
                'message'   => 'Internal server error.',
                'status'    => 'error'
            ], 500);
        }
    }
}
