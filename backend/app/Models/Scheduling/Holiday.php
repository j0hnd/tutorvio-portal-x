<?php

namespace App\Models\Scheduling;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'date',
        'timezone',
        'country_code',
        'repeats_annually',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'repeats_annually' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
