<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CampaignImageService
{
    public function __construct(protected CampaignService $campaignService) {}

    public function create(Campaign $campaign, UploadedFile $file, bool $isPrimary = false): CampaignImage
    {
        $this->campaignService->ensureEditable($campaign);

        if ($campaign->images()->count() >= 5) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(422, 'Maximum 5 images per campaign');
        }

        $path = $file->store('campaigns', 'public');

        return CampaignImage::create([
            'campaign_id' => $campaign->id,
            'url' => $path,
            'is_primary' => $isPrimary,
        ]);
    }

    public function deleteMany(Campaign $campaign, array $imageIds): void
    {
        $this->campaignService->ensureEditable($campaign);

        DB::transaction(function () use ($campaign, $imageIds) {
            $totalImages = $campaign->images()->lockForUpdate()->count();
            if (($totalImages - count($imageIds)) < 1) {
                throw new \Symfony\Component\HttpKernel\Exception\HttpException(422, 'Campaign must have at least 1 image');
            }

            $validCount = $campaign->images()->whereIn('id', $imageIds)->count();
            if ($validCount !== count($imageIds)) {
                throw new \Illuminate\Auth\Access\AuthorizationException();
            }

            $images = $campaign->images()->whereIn('id', $imageIds)->get();
            foreach ($images as $image) {
                Storage::disk('public')->delete($image->url);
            }

            $campaign->images()->whereIn('id', $imageIds)->delete();
        });
    }
}
