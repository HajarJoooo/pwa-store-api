<?php

namespace App\Http\Resources;

use App\Models\Pwa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pwa
 */
class PwaResource extends JsonResource
{
    /**
     * Transform the resource into an array for public details.
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
            'description' => $this->description,
            'manifest_url' => $this->manifest_url,
            'start_url' => $this->start_url,
            'icon_url' => $this->icon_url,
            'screenshots' => $this->screenshots ?? [],
            'version' => $this->version,
            'pricing_type' => $this->pricing_type ?? 'free',
            'is_free' => ($this->pricing_type ?? 'free') === 'free' && (float) ($this->price ?? 0) === 0.0,
            'price' => (float) ($this->price ?? 0),
            'privacy_policy_url' => $this->privacy_policy_url,
            'is_verified' => (bool) $this->is_verified,
            'compatibility' => $this->compatibility,
            'compatibility_notes' => is_array($this->compatibility)
                ? ($this->compatibility['notes'] ?? implode('، ', array_map('strval', $this->compatibility)))
                : $this->compatibility,
            'rating_average' => (float) $this->rating_average,
            'review_count' => (int) $this->review_count,
            'install_count' => (int) $this->install_count,
            'views_count' => (int) ($this->views_count ?? 0),
            'clicks_count' => (int) ($this->clicks_count ?? 0),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'name_ar' => $this->category->name_ar,
                'slug' => $this->category->slug,
            ]),
            'developer' => $this->whenLoaded('developer', fn () => [
                'id' => $this->developer->id,
                'first_name' => $this->developer->first_name,
                'last_name' => $this->developer->last_name,
                'full_name' => $this->developer->full_name,
                'username' => $this->developer->username,
                'avatar_url' => $this->developer->avatar_url,
                'is_verified' => (bool) $this->developer->is_verified,
            ]),
        ];
    }
}
