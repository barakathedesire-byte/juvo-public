<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Language;
use App\Services\Admin\CategoryService;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    protected $categoryService;

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
    }

    public function index()
    {
        return view('admin.categories.index');
    }

    public function getCategories(Request $request)
    {
        if ($request->ajax()) {
            return $this->categoryService->getCategoriesForDataTable($request);
        }
    }

    public function create()
    {
        $parentCategories = Category::with('translation')->orderBy('id')->get();

        return view('admin.categories.create', compact('parentCategories'));
    }

    public function store(Request $request)
    {
        $rules = [
            'translations' => 'required|array',
            'slug' => 'nullable|string|max:255',
            'parent_category_id' => 'nullable|exists:categories,id',
            'status' => 'nullable|boolean',
        ];

        foreach ($request->input('translations', []) as $lang => $data) {
            $rules["translations.$lang.name"] = 'required|string|max:255';
            $rules["translations.$lang.description"] = 'nullable|string|max:5000';
            $rules["translations.$lang.image"] = 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048';
        }

        $request->validate($rules);

        $translations = $request->input('translations');
        foreach ($translations as $languageCode => $translation) {
            if ($request->hasFile("translations.$languageCode.image")) {
                $translations[$languageCode]['image'] = $request->file("translations.$languageCode.image");
            }
        }

        $result = $this->categoryService->store($translations, [
            'slug' => $request->input('slug'),
            'parent_category_id' => $request->input('parent_category_id') ?: null,
            'status' => $request->has('status') ? (bool) $request->input('status') : true,
            'icon' => $request->input('icon'),
        ]);

        if ($result instanceof \Illuminate\Support\MessageBag) {
            return redirect()->back()->withErrors($result)->withInput();
        }

        return redirect()->route('admin.categories.index')->with('success', __('cms.categories.created'));
    }

    public function show(string $id)
    {
        //
    }

    public function edit(string $id)
    {
        $category = Category::with('translations')->findOrFail($id);

        $activeLanguages = Language::where('active', true)->get();
        $excludedIds = array_merge([$category->id], $this->descendantIds($category->id));
        $parentCategories = Category::with('translation')
            ->whereNotIn('id', $excludedIds)
            ->orderBy('id')
            ->get();

        return view('admin.categories.edit', compact('category', 'activeLanguages', 'parentCategories'));
    }

    /**
     * Collect the ids of every descendant of a category so a node cannot be
     * set as its own parent (which would create a cycle).
     */
    protected function descendantIds(int $categoryId): array
    {
        $ids = [];
        $frontier = [$categoryId];

        while ($frontier) {
            $children = Category::whereIn('parent_category_id', $frontier)->pluck('id')->all();
            $children = array_diff($children, $ids);
            $ids = array_merge($ids, $children);
            $frontier = $children;
        }

        return $ids;
    }

    public function update(Request $request, $id)
    {
        $translations = $request->all()['translations'];

        foreach ($translations as $languageCode => $translation) {
            if ($request->hasFile("translations.$languageCode.image")) {
                $translations[$languageCode]['image'] = $request->file("translations.$languageCode.image");
            }
        }

        $this->categoryService->update($request, $id);

        return redirect()->route('admin.categories.index')->with('success', __('cms.categories.updated'));
    }

    public function destroy($id)
    {
        $category = Category::find($id);

        if (! $category) {
            return response()->json([
                'success' => false,
                'message' => 'Category not found.',
            ], 404);
        }

        if ($category->children()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This category has sub-categories. Move or delete them first.',
            ], 422);
        }

        if ($category->products()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This category still has products. Reassign them before deleting.',
            ], 422);
        }

        $result = $this->categoryService->destroy($id);

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => __('cms.categories.deleted'),
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting category.',
            ]);
        }
    }

    public function updateCategoryStatus(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:categories,id',
            'status' => 'required|boolean',
        ]);

        $category = Category::find($request->id);
        $category->status = $request->status;
        $category->save();

        if ($category) {
            return response()->json([
                'success' => true,
                'message' => __('cms.categories.status_updated'),
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Category status could not be updated.',
            ]);
        }
    }
}
