<?php

namespace App\Http\Controllers\Settings;

use App\Actions\Users\StoreAvatar;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The signed-in user's own profile photo.
 */
class ProfilePhotoController extends Controller
{
    public function update(Request $request, StoreAvatar $storeAvatar): RedirectResponse
    {
        $request->validate(
            ['avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240']],
            [
                'avatar.required' => 'Choose a photo.',
                'avatar.image' => 'The file must be a photo.',
                'avatar.mimes' => 'Use a JPG, PNG or WebP photo.',
                'avatar.max' => 'The photo must be 10 MB or smaller.',
            ],
        );

        $storeAvatar->handle($request->user(), $request->file('avatar'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profile photo updated.']);

        return back();
    }

    public function destroy(Request $request, StoreAvatar $storeAvatar): RedirectResponse
    {
        $storeAvatar->remove($request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Profile photo removed.']);

        return back();
    }
}
