<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ad extends Model
{
    /** @use HasFactory<\Database\Factories\AdFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'ad_slot_id',
        'type',
        'image_url',
        'click_url',
        'embed_code',
        'is_active',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ad_slot_id' => 'integer',
            'type' => 'string',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AdSlot, $this>
     */
    public function adSlot(): BelongsTo
    {
        return $this->belongsTo(AdSlot::class);
    }

    public function scopeActive(Builder $query, \DateTimeInterface|string|null $at = null): Builder
    {
        $at = $at ?? now();

        return $query
            ->where('is_active', true)
            ->where(function (Builder $builder) use ($at): void {
                $builder->whereNull('starts_at')->orWhere('starts_at', '<=', $at);
            })
            ->where(function (Builder $builder) use ($at): void {
                $builder->whereNull('ends_at')->orWhere('ends_at', '>=', $at);
            });
    }
}
