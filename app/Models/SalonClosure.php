<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Jour exceptionnel (fermeture). */
class SalonClosure extends Model
{
    protected $fillable = ['date', 'label', 'is_closed'];

    protected function casts(): array
    {
        return ['date' => 'date:Y-m-d', 'is_closed' => 'boolean'];
    }
}
