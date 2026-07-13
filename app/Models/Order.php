<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'status',
        'currency_id',
        'total_amount',
        'total_amount_after_discount',
        'total_discount_amount',
        'payment_provider_order_id',
        'payment_provider_id',
        'tenant_id',
        'is_local',
        'comments',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'currency_id' => 'integer',
            'total_amount' => 'integer',
            'total_amount_after_discount' => 'integer',
            'total_discount_amount' => 'integer',
            'payment_provider_id' => 'integer',
            'tenant_id' => 'integer',
            'is_local' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function discounts(): HasMany
    {
        return $this->hasMany(OrderDiscount::class);
    }

    public function getRouteKeyName(): string
    {
        // used to find a model by its uuid instead of its id
        return 'uuid';
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function paymentProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
