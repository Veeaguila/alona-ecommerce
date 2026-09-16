<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\PlatformSetting;
use App\Models\Policy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminSettingsController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Main Settings Page
    |--------------------------------------------------------------------------
    */

    public function settings(): Response
    {
        $settings = PlatformSetting::query()
            ->orderBy('key')
            ->get();

        $announcements = Announcement::query()
            ->latest()
            ->get();

        $policies = Policy::query()
            ->latest()
            ->get();

        return Inertia::render('Admin/Settings', [
            'title' => 'Platform Settings',
            'eyebrow' => 'Settings',
            'description' => 'Configure global marketplace settings, announcements, and policies.',
            'active' => 'settings',

            'settings' => $settings,
            'announcements' => $announcements,
            'policies' => $policies,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Announcements
    |--------------------------------------------------------------------------
    */

    public function announcements(): Response
    {
        $announcements = Announcement::query()
            ->latest()
            ->get();

        return Inertia::render('Admin/Settings', [
            'title' => 'Announcements',
            'eyebrow' => 'Marketplace Communications',
            'description' => 'Create, publish, and manage platform-wide announcements.',
            'active' => 'announcements',

            'announcements' => $announcements,

            'settings' => [],
            'policies' => [],
        ]);
    }

    public function storeAnnouncement(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'body' => [
                'required',
                'string',
                'max:5000',
            ],

            'status' => [
                'nullable',
                'in:draft,published,archived',
            ],
        ]);

        $status = $data['status'] ?? 'draft';

        Announcement::create([
            'title' => $data['title'],
            'body' => $data['body'],
            'status' => $status,
            'created_by' => $request->user()->id,
            'published_at' => $status === 'published'
                ? now()
                : null,
        ]);

        return back()->with(
            'status',
            'Announcement saved successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Policies
    |--------------------------------------------------------------------------
    */

    public function policies(): Response
    {
        $policies = Policy::query()
            ->latest()
            ->get();

        return Inertia::render('Admin/Settings', [
            'title' => 'Policies',
            'eyebrow' => 'Platform Policies',
            'description' => 'Create, edit, and manage the policies that govern the Zellora marketplace.',
            'active' => 'policies',

            'policies' => $policies,

            'settings' => [],
            'announcements' => [],
        ]);
    }

    public function storePolicy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'content' => [
                'required',
                'string',
            ],

            'version' => [
                'nullable',
                'string',
                'max:50',
            ],

            'is_published' => [
                'nullable',
                'boolean',
            ],
        ]);

        Policy::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'version' => $data['version'] ?? '1.0',
            'is_published' => $data['is_published'] ?? false,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with(
            'status',
            'Policy created successfully.'
        );
    }

    public function updatePolicy(
        Request $request,
        Policy $policy
    ): RedirectResponse {
        $data = $request->validate([
            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'content' => [
                'required',
                'string',
            ],

            'version' => [
                'nullable',
                'string',
                'max:50',
            ],

            'is_published' => [
                'nullable',
                'boolean',
            ],
        ]);

        $policy->update([
            'title' => $data['title'],
            'content' => $data['content'],
            'version' => $data['version'] ?? $policy->version,
            'is_published' => $data['is_published'] ?? false,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with(
            'status',
            'Policy updated successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Platform Settings
    |--------------------------------------------------------------------------
    */

    public function storePlatformSetting(
        Request $request
    ): RedirectResponse {
        $data = $request->validate([
            'key' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9._-]+$/',
            ],

            'value' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],
        ]);

        $existingSetting = PlatformSetting::query()
            ->where('key', $data['key'])
            ->first();

        if ($existingSetting) {
            return back()->withErrors([
                'key' => 'A platform setting with this key already exists.',
            ]);
        }

        PlatformSetting::create([
            'key' => $data['key'],
            'value' => $data['value'] ?? '',
            'description' => $data['description'] ?? '',
            'is_active' => $data['is_active'] ?? true,
        ]);

        return back()->with(
            'status',
            'Platform setting created successfully.'
        );
    }
}