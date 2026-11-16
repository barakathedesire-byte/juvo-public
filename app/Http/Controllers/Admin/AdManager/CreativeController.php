<?php

namespace App\Http\Controllers\Admin\AdManager;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdCreative;
use Illuminate\Http\Request;

class CreativeController extends Controller
{
    public function store(Request $request, AdCampaign $campaign)
    {
        $data = $request->validate([
            'headline' => 'nullable|string|max:190',
            'body' => 'nullable|string|max:1000',
            'image' => 'nullable|string|max:255',
            'target_url' => 'nullable|url|max:255',
            'note' => 'nullable|string|max:255',
        ]);

        $version = (int) $campaign->creatives()->max('version') + 1;

        $campaign->creatives()->create(array_merge($data, [
            'version' => $version,
            'is_approved' => false,
        ]));

        return back()->with('success', "Creative version {$version} added.");
    }

    public function approve(AdCampaign $campaign, AdCreative $creative)
    {
        abort_unless($creative->campaign_id === $campaign->id, 404);

        $campaign->creatives()->update(['is_approved' => false]);
        $creative->update(['is_approved' => true]);

        $campaign->update([
            'creative_headline' => $creative->headline,
            'creative_body' => $creative->body,
            'creative_image' => $creative->image,
            'target_url' => $creative->target_url,
        ]);

        return back()->with('success', "Creative version {$creative->version} approved and set live.");
    }
}
