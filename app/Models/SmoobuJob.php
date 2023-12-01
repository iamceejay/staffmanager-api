<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class SmoobuJob extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'uuid',
        'title',
        'start',
        'end',
        'description',
        'location',
        'status',
        'staff_id',
        'smoobu_id',
        'smoobu_created_at',
        'arrival'
    ];

    public function user() {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function invoices() {
        return $this->hasOne(Invoice::class, 'smoobu_id', 'smoobu_id')->orderBy('arrival');
    }
}
