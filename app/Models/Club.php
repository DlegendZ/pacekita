<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Club extends Model
{
    protected $fillable = ['owner_id', 'name', 'slug', 'city', 'description', 'visibility', 'join_policy', 'status'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ClubMembership::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(Run::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isOrganizer(User $user): bool
    {
        return $this->memberships()->where('user_id', $user->id)->where('status', 'active')->whereIn('role', ['owner', 'organizer'])->exists();
    }

    public function hasMember(User $user): bool
    {
        return $this->memberships()->where('user_id', $user->id)->where('status', 'active')->exists();
    }
}
