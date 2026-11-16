<?php

namespace App\Http\Controllers\Admin\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\IntelligenceSavedReport;
use App\Services\Admin\AuditLogger;
use App\Services\Admin\UnifiedIntelligenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;

class UnifiedCommandCenterController extends Controller
{
    public const REPORT_TYPES = [
        'marketplace' => 'Marketplace performance',
        'vendors' => 'Vendor performance',
        'products' => 'Product demand',
        'customers' => 'Customer activity',
        'inventory' => 'Inventory availability',
        'logistics' => 'Logistics performance',
        'riders' => 'Rider performance',
        'geography' => 'Geographical performance',
        'support' => 'Customer support',
        'risks' => 'Operational risks',
        'health' => 'Integration health',
    ];

    public function __construct(protected UnifiedIntelligenceService $intelligence) {}

    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);
        $filters = $this->filters($request);

        return view('admin.command-center.intelligence.overview', [
            'from' => $from,
            'to' => $to,
            'filters' => $filters,
            'overview' => $this->intelligence->overview($from, $to, $filters),
            'filterOptions' => $this->filterOptions(),
            'canHealth' => $this->canHealth($request),
            'savedReports' => $this->savedReports($request),
        ]);
    }

    public function marketplace(Request $request)
    {
        [$from, $to] = $this->range($request);

        return view('admin.command-center.intelligence.marketplace', $this->shared($request, [
            'data' => $this->intelligence->marketplace($from, $to, $this->filters($request)),
        ]));
    }

    public function logistics(Request $request)
    {
        [$from, $to] = $this->range($request);

        return view('admin.command-center.intelligence.logistics', $this->shared($request, [
            'data' => $this->intelligence->logistics($from, $to, $this->filters($request)),
        ]));
    }

    public function vendors(Request $request)
    {
        [$from, $to] = $this->range($request);

        return view('admin.command-center.intelligence.vendors', $this->shared($request, [
            'data' => $this->intelligence->vendors($from, $to, $this->filters($request)),
        ]));
    }

    public function customers(Request $request)
    {
        [$from, $to] = $this->range($request);

        return view('admin.command-center.intelligence.customers', $this->shared($request, [
            'data' => $this->intelligence->customers($from, $to, $this->filters($request), $request->user()->can('customers.view')),
        ]));
    }

    public function geography(Request $request)
    {
        [$from, $to] = $this->range($request);

        return view('admin.command-center.intelligence.geography', $this->shared($request, [
            'data' => $this->intelligence->geography($from, $to, $this->filters($request)),
        ]));
    }

    public function risks(Request $request)
    {
        [$from, $to] = $this->range($request);

        return view('admin.command-center.intelligence.risks', $this->shared($request, [
            'data' => $this->intelligence->risks($from, $to),
            'insights' => $this->intelligence->insights($from, $to, $this->filters($request)),
        ]));
    }

    public function reports(Request $request)
    {
        [$from, $to] = $this->range($request);
        $filters = $this->filters($request);
        $type = $request->input('report_type', 'marketplace');
        if (! array_key_exists($type, self::REPORT_TYPES)) {
            $type = 'marketplace';
        }
        abort_if($type === 'health' && ! $this->canHealth($request), 403);

        return view('admin.command-center.intelligence.reports', $this->shared($request, [
            'filters' => $filters,
            'reportTypes' => self::REPORT_TYPES,
            'reportType' => $type,
            'dataset' => $this->intelligence->reportData($type, $from, $to, $filters, $request->user()->can('customers.view')),
            'canExport' => $this->canExport($request),
            'savedReports' => $this->savedReports($request),
        ]));
    }

    public function export(Request $request)
    {
        $this->authorizeExport($request);
        [$from, $to] = $this->range($request);
        $type = $request->input('report_type', 'marketplace');
        if (! array_key_exists($type, self::REPORT_TYPES)) {
            abort(422, 'Unknown report type.');
        }

        abort_if($type === 'customers' && ! $request->user()->can('customers.view'), 403);
        abort_if($type === 'health' && ! $this->canHealth($request), 403);

        $dataset = $this->intelligence->reportData($type, $from, $to, $this->filters($request), $request->user()->can('customers.view'));
        $filename = 'juvo-'.$type.'-'.$from->format('Ymd').'-'.$to->format('Ymd').'.csv';

        AuditLogger::record('intelligence.report.exported', null, [], [
            'report_type' => $type,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'filters' => $this->filters($request),
        ]);

        return Response::streamDownload(function () use ($dataset) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $dataset['columns']);
            foreach ($dataset['rows'] as $row) {
                fputcsv($handle, array_map(fn ($value) => is_array($value) ? json_encode($value) : $value, $row));
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function saveReport(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'report_type' => ['required', Rule::in(array_keys(self::REPORT_TYPES))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'vendor_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer'],
            'region_id' => ['nullable', 'integer'],
            'location_id' => ['nullable', 'integer'],
        ]);

        $saved = IntelligenceSavedReport::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'report_type' => $data['report_type'],
            'filters' => array_filter([
                'from' => $data['from'] ?? null,
                'to' => $data['to'] ?? null,
                'vendor_id' => $data['vendor_id'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'region_id' => $data['region_id'] ?? null,
                'location_id' => $data['location_id'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
        ]);

        AuditLogger::record('intelligence.report.saved', $saved, [], ['name' => $saved->name, 'report_type' => $saved->report_type]);

        return redirect()->route('admin.command-center.reports', ['report_type' => $saved->report_type] + $saved->filters)
            ->with('success', 'Report configuration saved.');
    }

    public function destroySavedReport(Request $request, IntelligenceSavedReport $savedReport)
    {
        abort_unless($savedReport->user_id === $request->user()->id || $request->user()->can('roles.view'), 403);
        AuditLogger::record('intelligence.report.deleted', $savedReport, ['name' => $savedReport->name], []);
        $savedReport->delete();

        return back()->with('success', 'Saved report removed.');
    }

    public function health(Request $request)
    {
        abort_unless($this->canHealth($request), 403);

        return view('admin.command-center.intelligence.health', $this->shared($request, [
            'health' => $this->intelligence->health(),
            'integrations' => \App\Models\IntegrationEvent::query()->orderByDesc('created_at')->limit(25)->get(),
        ]));
    }

    public function assistant(Request $request)
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:500']]);
        [$from, $to] = $this->range($request);
        $answer = $this->intelligence->assistantAnswer($data['question'], $from, $to, $request->user()->can('customers.view'));

        AuditLogger::record('intelligence.assistant.queried', null, [], [
            'question' => $data['question'],
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);

        if ($request->expectsJson()) {
            return response()->json($answer + [
                'period' => $from->format('d M Y').' - '.$to->format('d M Y'),
            ]);
        }

        return back()->with('assistant_answer', $answer + ['period' => $from->format('d M Y').' - '.$to->format('d M Y')]);
    }

    protected function shared(Request $request, array $extra = []): array
    {
        [$from, $to] = $this->range($request);

        return array_merge([
            'from' => $from,
            'to' => $to,
            'filters' => $this->filters($request),
            'filterOptions' => $this->filterOptions(),
            'canHealth' => $this->canHealth($request),
        ], $extra);
    }

    protected function range(Request $request): array
    {
        return $this->intelligence->range($request->input('from'), $request->input('to'));
    }

    protected function filters(Request $request): array
    {
        return collect(['vendor_id', 'category_id', 'region_id', 'location_id', 'status', 'product_id'])
            ->mapWithKeys(fn ($key) => [$key => $request->input($key)])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    protected function filterOptions(): array
    {
        return [
            'vendors' => \App\Models\Vendor::orderBy('shop_name')->orderBy('name')->limit(300)->get(['id', 'name', 'shop_name']),
            'categories' => \App\Models\Category::with('translation')->orderBy('id')->limit(300)->get(),
            'regions' => \App\Models\Region::active()->ordered()->get(),
            'locations' => \App\Models\Location::active()->ordered()->get(),
        ];
    }

    protected function savedReports(Request $request)
    {
        return IntelligenceSavedReport::where('user_id', $request->user()->id)->latest()->limit(20)->get();
    }

    protected function canHealth(Request $request): bool
    {
        return $request->user()->canAny(['audit.view', 'features.view', 'analytics.view']);
    }

    protected function canExport(Request $request): bool
    {
        return $request->user()->canAny(['analytics.export', 'orders.export', 'intelligence.export']);
    }

    protected function authorizeExport(Request $request): void
    {
        abort_unless($this->canExport($request), 403);
    }
}
