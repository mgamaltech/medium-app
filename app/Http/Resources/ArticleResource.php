<?php

namespace App\Http\Resources;

use App\Models\Article;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Article
 *
 * @property int $id
 * @property string $title
 * @property string $body
 * @property User $user
 * @property Comment $comments
 */
class ArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,

            'author_name' => $this->whenLoaded('user', fn () => $this->user->name),
            'comments_count' => $this->whenCounted('comments'),

            'created_at' => $this->created_at,
        ];
    }
}
