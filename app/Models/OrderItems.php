<?php

namespace App\Models;

use Illuminate\Console\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'item_id', 'quantity', 'unit_price', 'sub_total'])]
class OrderItems extends Model
{
    public function order(): BelongsTo {
        return $this->belongsTo(Orders::class, 'order_id');
    }

    public function item(): BelongsTo {
        return $this->belongsTo(Items::class, 'item_id');
    }

}
