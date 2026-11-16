<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesLead;
use App\Models\SalesRep;
use App\Services\Access\AuthorizationService;
use App\Services\Admin\AuditLogger;
use App\Support\Access\AccessScope;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class SalesLeadController extends Controller
{
    protected const STATUSES = ['new', 'contacted', 'qualified', 'won', 'lost'];

    public function __construct(
        protected AuditLogger $audit,
        protected AuthorizationService $auth,
    ) {}

    public function index(Request $request)
    {
        return view('admin.sales.leads.index', [
            'reps' => SalesRep::orderBy('name')->get(['id', 'name']),
            'statuses' => self::STATUSES,
            'filters' => $request->only(['sales_rep_id', 'status']),
        ]);
    }

    public function getData(Request $request)
    {
        if (! $request->ajax()) {
            return;
        }

        $leads = SalesLead::with('rep')->select('sales_leads.*');

        // Record scope: salespeople limited to their own territory/records only
        // see leads belonging to the sales rep(s) linked to their account.
        $scope = $this->auth->scope($request->user());
        if (in_array($scope->type, [AccessScope::OWN, AccessScope::TERRITORY], true)) {
            $repIds = SalesRep::where('user_id', $request->user()->id)->pluck('id')->all();
            $leads->whereIn('sales_rep_id', $repIds ?: [-1]);
        }

        if ($repId = $request->input('sales_rep_id')) {
            $leads->where('sales_rep_id', $repId);
        }

        if ($status = $request->input('status')) {
            $leads->where('status', $status);
        }

        return DataTables::of($leads)
            ->addColumn('rep', fn ($row) => $row->rep->name ?? '—')
            ->addColumn('action', function ($row) {
                $options = collect(self::STATUSES)
                    ->map(fn ($s) => '<option value="'.$s.'"'.($row->status === $s ? ' selected' : '').'>'.ucfirst($s).'</option>')
                    ->implode('');

                return '<form method="POST" action="'.route('admin.sales.leads.status', $row->id).'" class="d-flex gap-1">'
                    .csrf_field().method_field('PUT')
                    .'<select name="status" class="form-select form-select-sm">'.$options.'</select>'
                    .'<button class="btn btn-sm btn-outline-dark">Save</button></form>';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function status(Request $request, SalesLead $lead)
    {
        $this->auth->authorize($request->user(), ['sales.manage', 'sales.view'], ['write' => true]);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::STATUSES)],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $before = ['status' => $lead->status];

        $lead->status = $data['status'];
        if (array_key_exists('notes', $data) && $data['notes'] !== null) {
            $lead->notes = $data['notes'];
        }
        $lead->save();

        $this->audit->log('sales.lead.status', $lead, $before, ['status' => $lead->status]);

        return back()->with('success', 'Lead updated.');
    }
}
