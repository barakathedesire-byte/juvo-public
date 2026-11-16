<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;

class JoinController extends Controller
{
    public function sell()
    {
        return view('themes.xylo.join.sell', [
            'registerUrl' => route('vendor.register'),
            'loginUrl' => route('vendor.login'),
        ]);
    }

    public function deliver()
    {
        return view('themes.xylo.join.deliver', [
            'registerUrl' => route('rider.register'),
            'loginUrl' => route('rider.login'),
        ]);
    }

    public function jteam()
    {
        return view('themes.xylo.join.jteam', [
            'loginUrl' => route('agent.login'),
        ]);
    }
}
