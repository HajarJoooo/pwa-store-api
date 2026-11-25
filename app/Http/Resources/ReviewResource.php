<?php

namespace App\Http\Resources;

use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Review
 */
class ReviewResource extends JsonResource
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
            'rating' => (int) $this->rating,
            'title' => $this->title,
            'content' => $this->content,
            'helpful_count' => (int) $this->helpful_count,
            'created_at' => $this->created_at?->toIso8601String(),
            'reviewer' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'first_name' => $this->user->first_name,
                'username' => $this->user->username,
                'avatar_url' => $this->user->avatar_url,
            ]),
        ];
    }
}
