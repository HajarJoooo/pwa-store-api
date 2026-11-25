<?php

namespace App\Http\Resources;

use App\Enums\PwaStatus;
use App\Models\Pwa;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Pwa
 */
class ModeratorPwaResource extends JsonResource
{
    /**
     * Transform the resource into an array for moderators.
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
            'status' => $this->status instanceof PwaStatus ? $this->status->value : $this->status,
            'is_published' => (bool) $this->is_published,
            'rejection_reason' => $this->rejection_reason,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
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
                'username' => $this->developer->username,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
