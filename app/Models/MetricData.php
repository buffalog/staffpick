<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetricData extends Model
{
    public $timestamps = false;

    use HasFactory;

    protected $fillable = [
        'metric_id',
        'value',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metric_id' => 'integer',
        ];
    }
}
