<?php

namespace App\Http\Controllers;

use App\Http\Requests\LocationRequest;
use App\Models\Address;
use App\Support\CustomerLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer's delivery location (session) and a member's saved addresses.
 */
class LocationController extends Controller
{
    /**
     * Use a location for this visit; guests and members alike.
     */
    public function update(LocationRequest $request): RedirectResponse
    {
        CustomerLocation::set($request, $request->location());

        return back();
    }

    /**
     * Use one of the member's saved addresses for this visit.
     */
    public function useAddress(Request $request, Address $address): RedirectResponse
    {
        Gate::authorize('update', $address);

        CustomerLocation::set($request, CustomerLocation::fromAddress($address));

        return back();
    }

    public function index(Request $request): Response
    {
        return Inertia::render('addresses/index', [
            'addresses' => Address::query()
                ->where('user_id', $request->user()->id)
                ->orderByDesc('is_default')
                ->orderBy('label')
                ->get()
                ->map(fn (Address $address) => [
                    ...CustomerLocation::fromAddress($address),
                    'id' => $address->id,
                    'is_default' => $address->is_default,
                ]),
            'neighbourhoods' => config('marketplace.neighbourhoods'),
        ]);
    }

    public function store(LocationRequest $request): RedirectResponse
    {
        $address = new Address([...$request->location(), 'is_default' => $request->boolean('is_default')]);
        $address->user()->associate($request->user());

        // The first address is the default; a new default replaces the old one.
        if (Address::query()->where('user_id', $request->user()->id)->doesntExist()) {
            $address->is_default = true;
        }

        $address->save();

        if ($address->is_default) {
            Address::query()->where('user_id', $request->user()->id)->whereKeyNot($address->id)->update(['is_default' => false]);
        }

        CustomerLocation::set($request, CustomerLocation::fromAddress($address));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Saved {$address->label}."]);

        return back();
    }

    public function makeDefault(Request $request, Address $address): RedirectResponse
    {
        Gate::authorize('update', $address);

        Address::query()->where('user_id', $request->user()->id)->update(['is_default' => false]);
        $address->forceFill(['is_default' => true])->save();

        return back();
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        Gate::authorize('delete', $address);

        $wasInUse = (CustomerLocation::get($request)['address_id'] ?? null) === $address->id;
        $address->delete();

        if ($wasInUse) {
            CustomerLocation::forget($request);
        }

        return back();
    }
}
