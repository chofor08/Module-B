<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'status', 'total_price'])]
class Orders extends Model
{
    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order_items(): HasMany {
        return $this->hasMany(OrderItems::class, 'order_id');
    }

}
