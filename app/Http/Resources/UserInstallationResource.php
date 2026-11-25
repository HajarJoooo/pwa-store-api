<?php

namespace App\Http\Resources;

use App\Models\Installation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Installation
 */
class UserInstallationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pwa = $this->pwa;
        $pwaData = $pwa ? (new PwaListResource($pwa))->toArray($request) : [];

        return array_merge($pwaData, [
            'installed_at' => $this->installed_at?->toIso8601String(),
        ]);
    }
}
