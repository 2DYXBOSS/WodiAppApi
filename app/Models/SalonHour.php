<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalonHour extends Model
{
    protected $primaryKey = 'iso_day';

    public $incrementing = false;

    protected $fillable = ['iso_day', 'is_closed', 'open_time', 'close_time'];

    protected function casts(): array
    {
        return ['iso_day' => 'integer', 'is_closed' => 'boolean'];
    }
}
