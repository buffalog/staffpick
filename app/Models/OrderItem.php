<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'one_time_product_id',
        'quantity',
        'currency_id',
        'price_per_unit',
        'price_per_unit_after_discount',
        'discount_per_unit',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'order_id' => 'integer',
            'one_time_product_id' => 'integer',
            'quantity' => 'integer',
            'currency_id' => 'integer',
            'price_per_unit' => 'integer',
            'price_per_unit_after_discount' => 'integer',
            'discount_per_unit' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function oneTimeProduct(): BelongsTo
    {
        return $this->belongsTo(OneTimeProduct::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }
}
