<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdSlot extends Model
{
    /** @use HasFactory<\Database\Factories\AdSlotFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'slot_no',
        'title',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slot_no' => 'integer',
            'title' => 'string',
        ];
    }

    /**
     * @return HasMany<Ad, $this>
     */
    public function ads(): HasMany
    {
        return $this->hasMany(Ad::class);
    }
}
