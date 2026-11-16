<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesRep;
use App\Services\Admin\AuditLogger;
use App\Services\Sales\SalesAttributionService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Yajra\DataTables\Facades\DataTables;

class SalesRepController extends Controller
{
    public function __construct(
        protected SalesAttributionService $sales,
        protected AuditLogger $audit,
    ) {}

    public function index()
    {
        return view('admin.sales.reps.index', [
            'summary' => $this->sales->summary(),
            'leaderboard' => $this->sales->leaderboard(),
        ]);
    }

    public function getData(Request $request)
    {
        if (! $request->ajax()) {
            return;
        }

        $reps = SalesRep::with('user')->withCount(['leads', 'attributions'])->select('sales_reps.*');

        return DataTables::of($reps)
            ->addColumn('agent', fn ($row) => $row->user->name ?? '—')
            ->addColumn('status', fn ($row) => $row->is_active
                ? '<span class="badge bg-success">Active</span>'
                : '<span class="badge bg-secondary">Inactive</span>')
            ->addColumn('action', fn ($row) => '<a href="'.route('admin.sales.reps.edit', $row->id).'" class="btn btn-sm btn-outline-dark">Edit</a>')
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    public function create()
    {
        return view('admin.sales.reps.create', ['rep' => new SalesRep]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $rep = SalesRep::create($data);

        $this->audit->log('sales.rep.created', $rep, [], $rep->only(array_keys($data)));

        return redirect()->route('admin.sales.reps.index')->with('success', 'Salesperson added.');
    }

    public function edit(SalesRep $rep)
    {
        return view('admin.sales.reps.edit', ['rep' => $rep]);
    }

    public function update(Request $request, SalesRep $rep)
    {
        $data = $this->validated($request, $rep);
        $before = $rep->only(array_keys($data));

        $rep->update($data);

        $this->audit->log('sales.rep.updated', $rep, $before, $rep->only(array_keys($data)));

        return redirect()->route('admin.sales.reps.index')->with('success', 'Salesperson updated.');
    }

    public function destroy(SalesRep $rep)
    {
        $before = $rep->only(['name', 'code', 'is_active']);
        $rep->delete();

        $this->audit->log('sales.rep.deleted', $rep, $before, []);

        return back()->with('success', 'Salesperson removed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request, ?SalesRep $rep = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'code' => ['nullable', 'string', 'max:50', 'alpha_dash', 'unique:sales_reps,code'.($rep ? ','.$rep->id : '')],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'monthly_target' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data['code'] = ($data['code'] ?? null) ?: $this->uniqueCode($data['name'], $rep);
        $data['monthly_target'] = $data['monthly_target'] ?? 0;
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }

    protected function uniqueCode(string $name, ?SalesRep $rep = null): string
    {
        $base = Str::upper(Str::slug($name));
        $code = $base;

        while (SalesRep::where('code', $code)->when($rep, fn ($q) => $q->where('id', '!=', $rep->id))->exists()) {
            $code = $base.'-'.Str::upper(Str::random(3));
        }

        return $code;
    }
}
