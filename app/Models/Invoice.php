<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'smoobu_id',
        'customer_name',
        'customer_address',
        'arrival',
        'departure'
    ];
    
    public function job() {
        return $this->belongsTo(SmoobuJob::class, 'smoobu_id', 'smoobu_id');
    }
}