<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $fillable = ['category', 'to', 'subject', 'body', 'status', 'channel', 'remote_id', 'error', 'attempts', 'related', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];
}
