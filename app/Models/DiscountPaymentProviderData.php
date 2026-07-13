<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountPaymentProviderData extends Model
{
    use HasFactory;

    protected $fillable = [
        'discount_id',
        'payment_provider_id',
        'payment_provider_discount_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_id' => 'integer',
            'payment_provider_id' => 'integer',
        ];
    }
}
