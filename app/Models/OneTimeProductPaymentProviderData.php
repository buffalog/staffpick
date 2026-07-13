<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OneTimeProductPaymentProviderData extends Model
{
    use HasFactory;

    protected $fillable = [
        'one_time_product_id',
        'payment_provider_id',
        'payment_provider_product_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'one_time_product_id' => 'integer',
            'payment_provider_id' => 'integer',
        ];
    }

    public function oneTimeProduct(): BelongsTo
    {
        return $this->belongsTo(OneTimeProduct::class);
    }

    public function paymentProvider(): BelongsTo
    {
        return $this->belongsTo(PaymentProvider::class);
    }
}
