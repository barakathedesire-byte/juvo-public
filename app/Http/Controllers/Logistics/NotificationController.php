<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\AutomationRun;
use App\Models\Logistics\LogisticsAdmin;
use App\Models\Logistics\NotificationChannel;
use App\Models\Logistics\NotificationDelivery;
use App\Models\Logistics\NotificationTemplate;
use App\Models\Workflow;
use App\Services\Intelligence\AutomationRegistry;
use App\Services\Intelligence\AutomationRunner;
use App\Services\Logistics\LogisticsNotificationService;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        protected AutomationRegistry $registry,
        protected LogisticsNotificationService $notifications,
    ) {}

    public function index()
    {
        $this->canManage();

        $deliveries = NotificationDelivery::latest();
        $kpis = [
            'total' => NotificationDelivery::count(),
            'sent' => NotificationDelivery::whereIn('status', ['sent', 'delivered'])->count(),
            'delivered' => NotificationDelivery::where('status', 'delivered')->count(),
            'failed' => NotificationDelivery::where('status', 'failed')->count(),
            'pending' => NotificationDelivery::whereIn('status', ['queued'])->count(),
            'scheduled' => Workflow::where('scope', 'logistics')->where('trigger_type', 'schedule')->where('is_active', true)->count(),
            'retrying' => NotificationDelivery::where('status', 'retrying')->count(),
            'unread' => NotificationDelivery::whereNull('read_at')->count(),
        ];
        $recentFailures = NotificationDelivery::where('status', 'failed')->latest()->limit(10)->get();
        $volumeByChannel = NotificationDelivery::selectRaw('channel, COUNT(*) as total')->groupBy('channel')->pluck('total', 'channel');

        return view('logistics.notifications.index', compact('kpis', 'recentFailures', 'volumeByChannel'));
    }

    public function inbox(Request $request)
    {
        $this->canManage();

        $query = NotificationDelivery::latest();
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($channel = $request->string('channel')->toString()) {
            $query->where('channel', $channel);
        }
        if ($recipientType = $request->string('recipient_type')->toString()) {
            $query->where('recipient_type', $recipientType);
        }
        if ($search = $request->string('q')->toString()) {
            $query->where('dedup_key', 'like', "%{$search}%");
        }

        $deliveries = $query->paginate(25)->withQueryString();

        return view('logistics.notifications.inbox', compact('deliveries'));
    }

    public function channels()
    {
        $this->canManage();
        $channels = NotificationChannel::orderBy('name')->get();
        if ($channels->isEmpty()) {
            $this->seedChannels();
            $channels = NotificationChannel::orderBy('name')->get();
        }

        return view('logistics.notifications.channels', compact('channels'));
    }

    public function updateChannel(Request $request, NotificationChannel $channel)
    {
        $this->canManage();
        $data = $request->validate([
            'is_enabled' => 'nullable|boolean',
            'health_status' => 'nullable|string|max:32',
            'provider' => 'nullable|string|max:120',
        ]);
        $channel->update([
            'is_enabled' => $request->boolean('is_enabled'),
            'health_status' => $data['health_status'] ?? $channel->health_status,
            'provider' => $data['provider'] ?? $channel->provider,
            'last_checked_at' => now(),
        ]);

        return back()->with('success', 'Channel updated.');
    }

    public function testChannel(Request $request, NotificationChannel $channel)
    {
        $this->canManage();
        $admin = auth('logistics_admin')->user();
        $delivery = $this->notifications->send(
            'logistics_admin',
            $admin->id,
            $channel->key,
            'system',
            'Test notification',
            'This is a test notification for channel '.$channel->name.'.',
            ['test' => true],
            'test:'.$channel->key.':'.$admin->id
        );

        return back()->with('success', 'Test notification status: '.$delivery->status);
    }

    public function templates()
    {
        $this->canManage();
        $templates = NotificationTemplate::latest()->paginate(25);

        return view('logistics.notifications.templates', compact('templates'));
    }

    public function storeTemplate(Request $request)
    {
        $this->canManage();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'key' => 'required|string|max:120|unique:notification_templates,key',
            'channel' => 'required|string|max:32',
            'subject' => 'nullable|string|max:255',
            'body' => 'required|string',
            'variables' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);
        $data['variables'] = $data['variables'] ? array_map('trim', explode(',', $data['variables'])) : null;
        $data['is_active'] = $request->boolean('is_active', true);
        NotificationTemplate::create($data);

        return back()->with('success', 'Template created.');
    }

    public function automation()
    {
        $this->canManage();
        $workflows = Workflow::where('scope', 'logistics')->orderBy('name')->get();
        $actions = $this->registry->catalogue();

        return view('logistics.notifications.automation', compact('workflows', 'actions'));
    }

    public function storeAutomation(Request $request)
    {
        $this->canManage();
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'key' => 'required|string|max:120|unique:workflows,key',
            'trigger_type' => 'required|in:manual,schedule,event',
            'trigger' => 'nullable|string|max:120',
            'schedule' => 'nullable|string|max:120',
            'action' => 'required|string|max:120',
            'description' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
        ]);

        Workflow::create([
            'name' => $data['name'],
            'key' => $data['key'],
            'type' => 'logistics',
            'scope' => 'logistics',
            'action' => $data['action'],
            'trigger' => $data['trigger'] ?? $data['trigger_type'],
            'trigger_type' => $data['trigger_type'],
            'schedule' => $data['schedule'] ?? null,
            'description' => $data['description'] ?? null,
            'config' => [],
            'definition' => ['steps' => [['action' => $data['action'], 'input' => []]]],
            'retry_policy' => ['base_seconds' => 60, 'multiplier' => 2, 'max_seconds' => 3600],
            'timeout_seconds' => 60,
            'max_attempts' => 3,
            'version' => 1,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', 'Automation rule created.');
    }

    public function toggleAutomation(Workflow $workflow)
    {
        $this->canManage();
        $this->assertLogisticsWorkflow($workflow);
        $workflow->update(['is_active' => ! $workflow->is_active]);

        return back()->with('success', 'Automation rule updated.');
    }

    public function runAutomation(Workflow $workflow, AutomationRunner $runner)
    {
        $this->canManage();
        $this->assertLogisticsWorkflow($workflow);
        if (! $workflow->is_active) {
            return back()->withErrors(['workflow' => 'Inactive rule cannot run.']);
        }
        $run = $runner->dispatch($workflow, (array) $workflow->config, 'manual', auth('logistics_admin')->id());

        return back()->with($run->status === 'succeeded' ? 'success' : 'error', 'Automation run '.$run->status.'.');
    }

    public function monitor()
    {
        $this->canManage();
        $runs = AutomationRun::with('workflow')
            ->whereHas('workflow', fn ($q) => $q->where('scope', 'logistics'))
            ->latest()
            ->paginate(25);

        $kpis = [
            'total' => AutomationRun::whereHas('workflow', fn ($q) => $q->where('scope', 'logistics'))->count(),
            'succeeded' => AutomationRun::whereHas('workflow', fn ($q) => $q->where('scope', 'logistics'))->where('status', 'succeeded')->count(),
            'failed' => AutomationRun::whereHas('workflow', fn ($q) => $q->where('scope', 'logistics'))->whereIn('status', ['failed', 'dead_letter'])->count(),
            'retrying' => AutomationRun::whereHas('workflow', fn ($q) => $q->where('scope', 'logistics'))->where('status', 'retrying')->count(),
        ];

        return view('logistics.notifications.monitor', compact('runs', 'kpis'));
    }

    public function retryRun(AutomationRun $run, AutomationRunner $runner)
    {
        $this->canManage();
        abort_unless($run->workflow && $run->workflow->scope === 'logistics', 404);
        $run->update(['status' => 'queued', 'next_retry_at' => null, 'attempt' => 0]);
        $runner->process($run->fresh());

        return back()->with('success', 'Run re-queued.');
    }

    protected function seedChannels(): void
    {
        $defaults = [
            ['name' => 'In-App', 'key' => 'in_app', 'type' => 'in_app', 'provider' => 'database', 'is_enabled' => true, 'health_status' => 'healthy'],
            ['name' => 'Email', 'key' => 'email', 'type' => 'email', 'provider' => config('mail.default'), 'is_enabled' => (bool) config('mail.mailers.smtp.host'), 'health_status' => config('mail.mailers.smtp.host') ? 'healthy' : 'not_configured'],
            ['name' => 'SMS', 'key' => 'sms', 'type' => 'sms', 'provider' => null, 'is_enabled' => false, 'health_status' => 'not_configured'],
            ['name' => 'Push', 'key' => 'push', 'type' => 'push', 'provider' => config('broadcasting.default'), 'is_enabled' => (bool) config('broadcasting.connections.pusher.key'), 'health_status' => config('broadcasting.connections.pusher.key') ? 'healthy' : 'not_configured'],
        ];

        foreach ($defaults as $channel) {
            NotificationChannel::firstOrCreate(['key' => $channel['key']], $channel);
        }
    }

    private function assertLogisticsWorkflow(Workflow $workflow): void
    {
        abort_unless($workflow->scope === 'logistics', 404);
    }

    private function canManage(): void
    {
        $admin = auth('logistics_admin')->user();
        abort_unless($admin && ($admin->isSuperAdmin() || $admin->canAccessLogisticsModule('notifications')), 403);
    }
}
