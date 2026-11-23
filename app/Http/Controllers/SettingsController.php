<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        return view('pages.settings', compact('user'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'annual_goal' => 'required|integer|min:1|max:1000',
            'timezone' => 'nullable|string|max:64',
            'quiet_hours_enabled' => 'nullable|boolean',
            'quiet_hours_start' => 'nullable|string',
            'quiet_hours_end' => 'nullable|string',
            'push_opt_in' => 'nullable|boolean',
            'bio' => 'nullable|string|max:500',
            'preferences' => 'nullable|array',
        ]);

        $user->update([
            'name' => $validated['name'],
            'annual_goal' => $validated['annual_goal'],
            'timezone' => $validated['timezone'] ?? $user->timezone ?? 'Asia/Riyadh',
            'quiet_hours_enabled' => $request->has('quiet_hours_enabled'),
            'quiet_hours_start' => $validated['quiet_hours_start'] ?? '22:00',
            'quiet_hours_end' => $validated['quiet_hours_end'] ?? '08:00',
            'push_opt_in' => $request->has('push_opt_in') || $request->boolean('push_opt_in'),
            'bio' => $validated['bio'] ?? $user->bio ?? '',
            'digest_enabled' => $request->has('digest_enabled'),
            'digest_time' => $request->input('digest_time', '20:00'),
        ]);

        if ($request->has('preferences') && is_array($request->input('preferences'))) {
            $user->updateNotificationPreferences($request->input('preferences'));
        }

        return back()->with('success', 'تم حفظ التفضيلات والإعدادات بنجاح.');
    }
}
