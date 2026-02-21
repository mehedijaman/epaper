<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    /** @use HasFactory<\Database\Factories\PageFactory> */
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'edition_id',
        'page_no',
        'category_id',
        'image_original_path',
        'image_large_path',
        'image_thumb_path',
        'width',
        'height',
        'uploaded_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'edition_id' => 'integer',
            'page_no' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'category_id' => 'integer',
            'uploaded_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Edition, $this>
     */
    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return HasMany<PageHotspot, $this>
     */
    public function pageHotspots(): HasMany
    {
        return $this->hasMany(PageHotspot::class);
    }

    /**
     * Backward-compatible alias.
     *
     * @return HasMany<PageHotspot, $this>
     */
    public function hotspots(): HasMany
    {
        return $this->pageHotspots();
    }
}
