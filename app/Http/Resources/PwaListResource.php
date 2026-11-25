<?php

namespace App\Http\Resources;

use App\Models\Pwa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pwa
 */
class PwaListResource extends JsonResource
{
    /**
     * Transform the resource into an array for public listings.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'icon_url' => $this->icon_url,
            'version' => $this->version,
            'rating_average' => (float) $this->rating_average,
            'review_count' => (int) $this->review_count,
            'install_count' => (int) $this->install_count,
            'category' => $this->whenLoaded('category', fn () => [
                'name' => $this->category->name,
                'name_ar' => $this->category->name_ar,
                'slug' => $this->category->slug,
            ]),
        ];
    }
}
