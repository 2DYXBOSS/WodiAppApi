<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'email', 'phone', 'subject', 'message', 'status', 'read_at', 'ip_hash'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }
}
