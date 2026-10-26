<?php

namespace App\Http\Controllers;

use App\Support\DisplayCurrency;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The header's currency switcher. Only changes how prices are shown.
 */
class CurrencyController extends Controller
{
    public function update(Request $request, DisplayCurrency $displayCurrency): RedirectResponse
    {
        $validated = $request->validate([
            'currency' => ['required', 'string', Rule::in(Money::currencies())],
        ]);

        $displayCurrency->remember($validated['currency']);

        return back();
    }
}
