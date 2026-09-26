<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RunUpdate extends Model
{
    protected $fillable = ['author_id', 'message'];

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(RunOccurrence::class, 'occurrence_id');
    }
}
