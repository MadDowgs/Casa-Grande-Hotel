<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row per call from the Hotel system (System 1) to the Payment API (System 2). Card data is masked. */
class ApiLog extends Model
{
    protected $fillable = ['method', 'url', 'headers', 'request', 'status', 'response'];
    protected $casts = ['headers' => 'array', 'request' => 'array', 'response' => 'array'];
}
