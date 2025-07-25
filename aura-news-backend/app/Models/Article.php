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
        'title', 'content', 'summary', 'source_url', 'image_url', 'author', 'status', 'view_count', 'published_at',
        'credibility_analysis', 'credibility_score', 'credibility_checked_at', 'popularity_score',
    ];

    protected $casts = [
        'credibility_score' => 'integer',
        'credibility_checked_at' => 'datetime',
        'published_at' => 'datetime',
        'popularity_score' => 'decimal:2',
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

    public static function cleanFirstSentence($content)
    {
        if (!is_string($content) || trim($content) === '') return $content;
        if (preg_match('/^.*?(?:[。.!?：:](?:\s*)|\n)/u', $content, $matches)) {
            $firstSentence = $matches[0];
            if (mb_strpos($firstSentence, '好的') !== false) {
                $newContent = mb_substr($content, mb_strlen($firstSentence));
                return ltrim($newContent);
            }
        }
        if (preg_match('/^(.*)$/u', $content, $matches)) {
            $firstSentence = $matches[1];
            if (mb_strpos($firstSentence, '好的') !== false) {
                $newContent = mb_substr($content, mb_strlen($firstSentence));
                return ltrim($newContent);
            }
        }
        return $content;
    }
}