<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    protected $dateFormat = 'Y-m-d H:i:sP';

    protected $fillable = ['slot_id', 'customer_name', 'customer_email'];

    protected $hidden = ['customer_name', 'customer_email'];

    protected function casts(): array
    {
        return ['cancelled_at' => 'immutable_datetime'];
    }
}
