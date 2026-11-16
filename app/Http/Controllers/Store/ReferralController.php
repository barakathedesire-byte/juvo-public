<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\SalesLead;
use App\Models\SalesRep;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    /**
     * Capture a referral visit from a salesperson's share link. The visit is
     * recorded once per session as an unqualified lead attributed to the rep,
     * and the visitor is returned to the storefront.
     */
    public function capture(Request $request, string $code)
    {
        $rep = SalesRep::forCode($code);

        if (! $rep) {
            return redirect()->route('xylo.home');
        }

        $sessionKey = 'sales_ref_'.$rep->id;

        if (! $request->session()->has($sessionKey)) {
            $request->session()->put($sessionKey, true);

            SalesLead::create([
                'sales_rep_id' => $rep->id,
                'source' => 'referral',
                'status' => 'new',
                'notes' => 'Referred via code '.$rep->code,
            ]);
        }

        $request->session()->put('sales_ref_code', $rep->code);

        return redirect()->route('xylo.home')->with('success', 'Referred by '.$rep->name.'.');
    }
}
