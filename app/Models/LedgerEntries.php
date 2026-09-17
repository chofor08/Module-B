<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LedgerEntries extends Model
{
    
    public function legder_entries(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
}
