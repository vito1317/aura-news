<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'title', 'content', 'summary', 'source_url', 'image_url', 'author', 'status', 'published_at',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}