<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class Article extends Model
{
    use HasFactory, Searchable;

    protected $fillable = [
        'category_id',
        'title', 'content', 'summary', 'source_url', 'image_url', 'author', 'status', 'published_at',
        'credibility_analysis', 'credibility_score', 'credibility_checked_at',
    ];

    protected $casts = [
        'credibility_score' => 'integer',
        'credibility_checked_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function toSearchableArray()
    {
        return [
            'title' => $this->title,
            'content' => $this->content,
            'summary' => $this->summary,
        ];
    }
}