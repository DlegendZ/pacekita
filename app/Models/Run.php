<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Run extends Model
{
    protected $fillable = ['club_id', 'creator_id', 'title', 'description', 'terrain', 'route_summary', 'recurrence_rule'];

    protected function casts(): array
    {
        return ['recurrence_rule' => 'array'];
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(RunOccurrence::class);
    }
}
