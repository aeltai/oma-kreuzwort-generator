<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    protected $fillable = [
        'org_id', 'name', 'notes', 'language', 'difficulty', 'family_story', 'custom_context',
    ];

    public function org(): BelongsTo
    {
        return $this->belongsTo(User::class, 'org_id');
    }

    public function puzzles(): HasMany
    {
        return $this->hasMany(Puzzle::class);
    }
}
