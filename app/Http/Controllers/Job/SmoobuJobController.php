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

        if($request->status) {
            $jobs = $jobs->where('status', $request->status);
        }

        if($request->staff) {
            $jobs = $jobs->where('staff_id', $request->staff);
        }

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
        }

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
                ->where('status', 'available')
                ->orWhere('status', 'taken')
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
                $message = 'Job ' . $job->title . ' has been cancelled. Login to Staffmanager account.';
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

            $update = SmoobuJob::where('id', $request->id)->update([
                'title'         => $request->title,
                'staff_id'      => $request->staff,
                'start'         => $request->start,
                'end'           => $request->end,
                'location'      => $request->location,
                'description'   => $request->description,
                'status'        => $request->staff ? 'taken' : $job->status
            ]);

            $job = SmoobuJob::with('user')->where('id', $request->id)->first();

            if($job->staff_id) {
                if($is_assignment) {
                    $message = 'Job ' . $job->title . ' has been assigned to you. Login to Staffmanager account.';
                } else {
                    $message = 'Job ' . $job->title . ' has been updated. Login to Staffmanager account.';
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
            $delete = SmoobuJob::where('uuid', $request->uuid)->delete();

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
        
        try {
            DB::beginTransaction();

            if($request->action === 'newReservation') {
                SmoobuJob::create([
                    'uuid'          => Str::uuid(),
                    'smoobu_id'     => $request['data']['id'],
                    'title'         => $request['data']['apartment']['name'] . ' - ' . $request['data']['guest-name'],
                    'start'         => $request['data']['departure'] . ' 12:00:00',
                    'end'           => $request['data']['departure'] . ' 14:00:00',
                    'location'      => $request['data']['apartment']['name'],
                    'description'   => 'Adults: ' . $request['data']['adults'] . ', Children: ' . $request['data']['children'] . ', Notice: ' . $request['data']['notice'],
                    'status'        => 'available'
                ]);
            }

            if($request->action === 'cancelReservation') {
                $job = SmoobuJob::with('user')->where('smoobu_id', $request['data']['id'])->first();

                $update = SmoobuJob::where('smoobu_id', $request['data']['id'])->update([
                    'status'    => 'cancelled',
                    'staff_id'  => NULL
                ]);

                if($job->staff_id) {
                    $message = 'Job ' . $job->title . ' has been cancelled. Login to Staffmanager account.';
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
}
