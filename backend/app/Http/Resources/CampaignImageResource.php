<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CampaignImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $url = $this->url;

        // If the URL is already an absolute HTTP/HTTPS URL (e.g. from seeder or external CDN), return directly
        if ($url && Str::startsWith($url, ['http://', 'https://', '//'])) {
            $formattedUrl = $url;
        } elseif ($url) {
            $formattedUrl = Storage::disk('public')->url($url);
        } else {
            $formattedUrl = null;
        }

        return [
            "id" => $this->id,
            "url" => $formattedUrl,
            "is_primary" => (bool) $this->is_primary,
        ];
    }
}
