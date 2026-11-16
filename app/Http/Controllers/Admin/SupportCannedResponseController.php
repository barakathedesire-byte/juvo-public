<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportCannedResponse;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportCannedResponseController extends Controller
{
    protected const CATEGORIES = ['general', 'order', 'payment', 'return', 'technical', 'other'];

    public function __construct(protected AuditLogger $audit) {}

    public function index()
    {
        return view('admin.support.canned.index', [
            'responses' => SupportCannedResponse::orderBy('title')->get(),
            'categories' => self::CATEGORIES,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $response = SupportCannedResponse::create($data + ['created_by' => Auth::id()]);

        $this->audit->log('support.canned.created', $response, [], $data);

        return back()->with('success', 'Canned response created.');
    }

    public function update(Request $request, SupportCannedResponse $canned)
    {
        $data = $this->validated($request);
        $before = $canned->only(array_keys($data));

        $canned->update($data);

        $this->audit->log('support.canned.updated', $canned, $before, $data);

        return back()->with('success', 'Canned response updated.');
    }

    public function destroy(SupportCannedResponse $canned)
    {
        $before = $canned->only(['title', 'body', 'category', 'is_active']);
        $canned->delete();

        $this->audit->log('support.canned.deleted', $canned, $before, []);

        return back()->with('success', 'Canned response deleted.');
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'category' => ['nullable', 'in:'.implode(',', self::CATEGORIES)],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }
}
