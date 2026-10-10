<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Faq;
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

        $faqs = Faq::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Admin/Settings', [
            'title' => 'Platform Settings',
            'eyebrow' => 'Settings',
            'description' => 'Configure global marketplace settings, announcements, policies, and FAQs.',
            'active' => 'settings',

            'settings' => $settings,
            'announcements' => $announcements,
            'policies' => $policies,
            'faqs' => $faqs,
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

    public function updatePlatformSetting(
        Request $request,
        PlatformSetting $platformSetting
    ): RedirectResponse {
        $data = $request->validate([
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

        $platformSetting->update([
            'value' => $data['value'] ?? '',
            'description' => $data['description'] ?? $platformSetting->description,
            'is_active' => $data['is_active'] ?? false,
        ]);

        return back()->with(
            'status',
            'Platform setting updated successfully.'
        );
    }

    public function destroyPlatformSetting(
        PlatformSetting $platformSetting
    ): RedirectResponse {
        $platformSetting->delete();

        return back()->with(
            'status',
            'Platform setting deleted successfully.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | FAQs
    |--------------------------------------------------------------------------
    */

    public function faqs(): Response
    {
        $faqs = Faq::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('Admin/Settings', [
            'title' => 'FAQs',
            'eyebrow' => 'Platform FAQs',
            'description' => 'Create, edit, and organize frequently asked questions for buyers and sellers.',
            'active' => 'faqs',

            'faqs' => $faqs,

            'settings' => [],
            'announcements' => [],
            'policies' => [],
        ]);
    }

    public function storeFaq(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'question' => [
                'required',
                'string',
                'max:500',
            ],

            'answer' => [
                'required',
                'string',
                'max:10000',
            ],

            'category' => [
                'required',
                'string',
                'max:100',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'is_published' => [
                'nullable',
                'boolean',
            ],
        ]);

        Faq::create([
            'question' => $data['question'],
            'answer' => $data['answer'],
            'category' => $data['category'],
            'sort_order' => $data['sort_order'] ?? 0,
            'is_published' => $data['is_published'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return back()->with(
            'status',
            'FAQ created successfully.'
        );
    }

    public function updateFaq(Request $request, Faq $faq): RedirectResponse
    {
        $data = $request->validate([
            'question' => [
                'required',
                'string',
                'max:500',
            ],

            'answer' => [
                'required',
                'string',
                'max:10000',
            ],

            'category' => [
                'required',
                'string',
                'max:100',
            ],

            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'is_published' => [
                'nullable',
                'boolean',
            ],
        ]);

        $faq->update([
            'question' => $data['question'],
            'answer' => $data['answer'],
            'category' => $data['category'],
            'sort_order' => $data['sort_order'] ?? $faq->sort_order,
            'is_published' => $data['is_published'] ?? false,
        ]);

        return back()->with(
            'status',
            'FAQ updated successfully.'
        );
    }

    public function destroyFaq(Faq $faq): RedirectResponse
    {
        $faq->delete();

        return back()->with(
            'status',
            'FAQ deleted successfully.'
        );
    }
}