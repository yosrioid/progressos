<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    public function dashboard(Request $request)
    {
        $data = $request->validate([
            'dashboard_layout' => ['required', 'array'],
            'dashboard_layout.*' => ['string'],
        ]);

        $request->user()->update($data);

        return back()->with('success', 'Dashboard layout saved.');
    }

    public function notifications(Request $request)
    {
        $data = $request->validate([
            'notification_preferences' => ['required', 'array'],
        ]);

        $request->user()->update($data);

        return back()->with('success', 'Notification preferences saved.');
    }
}
