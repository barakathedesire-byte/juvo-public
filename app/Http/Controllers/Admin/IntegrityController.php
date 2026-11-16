<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\PlatformIntegrityService;

/**
 * Read-only integrity & reconciliation screen. Surfaces the same checks the
 * `platform:reconcile` command runs so staff can spot money or SLA anomalies
 * without shell access.
 */
class IntegrityController extends Controller
{
    public function __construct(protected PlatformIntegrityService $integrity) {}

    public function index()
    {
        $checks = $this->integrity->checks();

        return view('admin.system.integrity', [
            'checks' => $checks,
            'summary' => $this->integrity->summary(),
        ]);
    }
}
