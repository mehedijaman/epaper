<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageHotspot extends Model
{
    /** @use HasFactory<\Database\Factories\PageHotspotFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'page_id',
        'type',
        'relation_kind',
        'target_page_no',
        'target_hotspot_id',
        'linked_hotspot_id',
        'x',
        'y',
        'w',
        'h',
        'label',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_id' => 'integer',
            'type' => 'string',
            'relation_kind' => 'string',
            'target_page_no' => 'integer',
            'target_hotspot_id' => 'integer',
            'linked_hotspot_id' => 'integer',
            'x' => 'float',
            'y' => 'float',
            'w' => 'float',
            'h' => 'float',
            'label' => 'string',
            'created_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<PageHotspot, $this>
     */
    public function targetHotspot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'target_hotspot_id');
    }

    /**
     * @return BelongsTo<PageHotspot, $this>
     */
    public function linkedHotspot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'linked_hotspot_id');
    }
}
