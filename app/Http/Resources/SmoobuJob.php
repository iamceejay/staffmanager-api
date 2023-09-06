<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Auth;

class SmoobuJob extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = Auth::user()->roles->pluck('name')->first();

        return [
            'id'          => $this->uuid,
            'job_id'      => $this->id,
            'title'       => $this->title,
            'with'        => $this->staff_id ? $this->user->first_name . ' ' . $this->user->last_name : '',
            'time'        => [
                'start'   => date('Y-m-d H:i', strtotime($this->start)),
                'end'     => date('Y-m-d H:i', strtotime($this->end)),
            ],
            'isEditable'  => $user === 'admin' ? true : false,
            'description' => $this->description,
            'location'    => $this->location,
            'colorScheme' => $this->status,
            'disableDnD'  => ['month', 'week', 'day']
        ];
    }
}
