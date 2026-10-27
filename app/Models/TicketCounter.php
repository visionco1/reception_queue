<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketCounter extends Model
{
    public $timestamps = false;

    protected $fillable = ['counter_date', 'prefix', 'last_number'];
}
