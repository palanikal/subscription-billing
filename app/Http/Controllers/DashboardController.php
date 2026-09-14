<?php

namespace App\Http\Controllers;

use App\Actions\Dashboard\BuildMerchantDashboardAction;
use App\Models\Merchant;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    /** Display the local demo merchant's billing overview. */
    public function __invoke(BuildMerchantDashboardAction $buildMerchantDashboard): View
    {
        $merchant = Merchant::query()->oldest('id')->first();

        return view('dashboard', [
            'merchant' => $merchant,
            'dashboard' => $merchant === null
                ? null
                : $buildMerchantDashboard->handle($merchant, CarbonImmutable::now('UTC')),
        ]);
    }
}
