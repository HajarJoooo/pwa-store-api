<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Category
 */
class CategoryResource extends JsonResource
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
            'name' => $this->name,
            'name_ar' => $this->name_ar,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon_url' => $this->icon_url,
            'sort_order' => (int) $this->sort_order,
            'pwas_count' => $this->whenNotNull($this->pwas_count),
            'children' => CategoryResource::collection($this->whenLoaded('children')),
        ];
    }
}
