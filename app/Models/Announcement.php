<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'starts_at',
        'ends_at',
        'is_active',
        'is_dismissible',
        'show_for_customers',
        'show_on_frontend',
        'show_on_user_dashboard',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_dismissible' => 'boolean',
            'show_for_customers' => 'boolean',
            'show_on_frontend' => 'boolean',
            'show_on_user_dashboard' => 'boolean',
        ];
    }
}
