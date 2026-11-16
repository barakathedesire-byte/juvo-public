<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Services\Sales\AgentDashboardService;

class DashboardController extends Controller
{
    public function __construct(
        protected AgentDashboardService $dashboard,
    ) {}

    public function index()
    {
        $agent = request()->attributes->get('agent');

        return view('agent.dashboard', $this->dashboard->overview($agent));
    }
}
