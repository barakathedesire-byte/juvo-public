<?php

namespace App\Http\Controllers\Admin\Homepage;

use App\Http\Controllers\Admin\Homepage\Concerns\HandlesHomepageMedia;
use App\Http\Controllers\Controller;
use App\Models\HomepageSlide;
use Illuminate\Http\Request;

class SlideController extends Controller
{
    use HandlesHomepageMedia;

    public function index()
    {
        $slides = HomepageSlide::ordered()->get();

        return view('admin.homepage.slides.index', compact('slides'));
    }

    public function create()
    {
        return view('admin.homepage.slides.form', ['slide' => new HomepageSlide]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['sort_order'] = (int) HomepageSlide::max('sort_order') + 1;

        if ($request->hasFile('image')) {
            $data['image'] = $this->storeImage($request->file('image'), 'slides');
        }
        if ($request->hasFile('mobile_image')) {
            $data['mobile_image'] = $this->storeImage($request->file('mobile_image'), 'slides');
        }

        HomepageSlide::create($data);

        return redirect()->route('admin.homepage.slides.index')->with('success', 'Slide created.');
    }

    public function edit(HomepageSlide $slide)
    {
        return view('admin.homepage.slides.form', compact('slide'));
    }

    public function update(Request $request, HomepageSlide $slide)
    {
        $data = $this->validated($request);

        if ($request->hasFile('image')) {
            $this->deleteImage($slide->image);
            $data['image'] = $this->storeImage($request->file('image'), 'slides');
        }
        if ($request->hasFile('mobile_image')) {
            $this->deleteImage($slide->mobile_image);
            $data['mobile_image'] = $this->storeImage($request->file('mobile_image'), 'slides');
        }

        $slide->update($data);

        return redirect()->route('admin.homepage.slides.index')->with('success', 'Slide updated.');
    }

    public function destroy(HomepageSlide $slide)
    {
        $this->deleteImage($slide->image);
        $this->deleteImage($slide->mobile_image);
        $slide->delete();

        return back()->with('success', 'Slide deleted.');
    }

    public function toggle(HomepageSlide $slide)
    {
        $slide->update(['status' => $slide->status === 'published' ? 'draft' : 'published']);

        return back()->with('success', 'Slide visibility updated.');
    }

    public function reorder(Request $request)
    {
        $data = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer|exists:homepage_slides,id',
        ]);

        foreach ($data['order'] as $position => $id) {
            HomepageSlide::whereKey($id)->update(['sort_order' => $position + 1]);
        }

        return response()->json(['message' => 'Order saved.']);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => 'nullable|string|max:190',
            'subtitle' => 'nullable|string|max:190',
            'description' => 'nullable|string|max:1000',
            'cta_label' => 'nullable|string|max:60',
            'cta_url' => 'nullable|string|max:255',
            'badge' => 'nullable|string|max:60',
            'text_align' => 'nullable|in:left,center,right',
            'height' => 'nullable|in:small,medium,large,auto',
            'title_size' => 'nullable|integer|min:18|max:80',
            'bg_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'bg_color_2' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'text_color' => ['nullable', 'string', 'regex:/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'overlay' => 'nullable|in:none,light,medium,dark',
            'status' => 'required|in:draft,published',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240',
            'mobile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp,svg|max:10240',
        ]);

        $this->assertDestination($data['cta_url'] ?? null);

        $data['text_align'] = $data['text_align'] ?? 'left';
        $data['height'] = $data['height'] ?? 'medium';
        $data['overlay'] = $data['overlay'] ?? 'medium';
        unset($data['image'], $data['mobile_image']);

        return $data;
    }
}
