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

        $heroBannersSetting = PlatformSetting::where('key', 'home.hero_banners')->first();
        $heroBanners = null;
        if ($heroBannersSetting && $heroBannersSetting->value) {
            $heroBanners = json_decode($heroBannersSetting->value, true);
        }

        if (empty($heroBanners) || !is_array($heroBanners)) {
            $heroBannerSetting = PlatformSetting::where('key', 'home.hero_banner')->first();
            if ($heroBannerSetting && $heroBannerSetting->value) {
                $single = json_decode($heroBannerSetting->value, true);
                if (is_array($single)) {
                    $heroBanners = [$single];
                }
            }
        }

        if (empty($heroBanners) || !is_array($heroBanners)) {
            $heroBanners = [
                [
                    'id' => 'banner_1',
                    'is_active' => true,
                    'theme' => 'teal',
                    'badge_text' => '',
                    'title' => 'Welcome to',
                    'title_highlight' => 'Alona.',
                    'description' => 'Everything you love, all in one place. Discover quality products, trusted sellers, and great deals made for you.',
                    'primary_button_text' => 'Shop Now',
                    'primary_button_url' => '/products',
                    'secondary_button_text' => 'Explore Categories',
                    'secondary_button_url' => '#categories',
                    'image_path' => '/images/Alogo.png',
                    'show_floating_cards' => true,
                    'card_1_title' => 'Products',
                    'card_1_subtitle' => 'available',
                    'card_2_title' => 'CATEGORIES',
                    'card_2_subtitle' => 'to explore',
                ],
            ];
        }

        foreach ($heroBanners as $idx => &$bannerItem) {
            if (empty($bannerItem['id'])) {
                $bannerItem['id'] = 'banner_' . ($idx + 1);
            }
            if (!isset($bannerItem['is_active'])) {
                $bannerItem['is_active'] = true;
            }
            if (empty($bannerItem['layout_type'])) {
                $bannerItem['layout_type'] = 'split';
            }
            if (!isset($bannerItem['overlay_text'])) {
                $bannerItem['overlay_text'] = false;
            }
            if (!isset($bannerItem['show_primary_button'])) {
                $bannerItem['show_primary_button'] = !empty($bannerItem['primary_button_text']);
            }
            if (!isset($bannerItem['show_primary_button_arrow'])) {
                $bannerItem['show_primary_button_arrow'] = true;
            }
            if (!isset($bannerItem['show_secondary_button'])) {
                $bannerItem['show_secondary_button'] = !empty($bannerItem['secondary_button_text']);
            }
            if (!isset($bannerItem['show_image'])) {
                $bannerItem['show_image'] = true;
            }
            if (empty($bannerItem['theme'])) {
                $bannerItem['theme'] = 'teal';
            }
        }
        unset($bannerItem);

        $heroBanner = $heroBanners[0] ?? null;

        $whyAlonaSetting = PlatformSetting::where('key', 'home.why_alona')->first();
        $whyAlona = $whyAlonaSetting && $whyAlonaSetting->value
            ? json_decode($whyAlonaSetting->value, true)
            : [
                'eyebrow' => 'WHY ALONA',
                'title' => 'Shopping should feel effortless.',
                'description' => 'Alona brings products and sellers together through a marketplace designed to keep discovering simple.',
                'cards' => [
                    [
                        'number' => '01',
                        'title' => 'Curated for you',
                        'description' => 'Discover products selected from different categories in one simple marketplace.',
                    ],
                    [
                        'number' => '02',
                        'title' => 'Trusted sellers',
                        'description' => 'Shop from sellers who are part of the Alona marketplace community.',
                    ],
                    [
                        'number' => '03',
                        'title' => 'Simple shopping',
                        'description' => 'Find products, compare choices, and shop without unnecessary steps.',
                    ],
                ],
            ];

        return Inertia::render('Admin/Settings', [
            'title' => 'Platform Settings',
            'eyebrow' => 'Settings',
            'description' => 'Configure global marketplace settings, announcements, policies, and FAQs.',
            'active' => 'settings',

            'settings' => $settings,
            'announcements' => $announcements,
            'policies' => $policies,
            'faqs' => $faqs,
            'heroBanners' => $heroBanners,
            'heroBanner' => $heroBanner,
            'whyAlona' => $whyAlona,
            'footer' => \App\Services\FooterService::get(),
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
            'description' => 'Create, edit, and manage the policies that govern the Alona marketplace.',
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

    public function updateHeroBanner(Request $request): RedirectResponse
    {
        $rawBanners = null;
        if ($request->filled('banners_data')) {
            $decoded = json_decode($request->input('banners_data'), true);
            if (is_array($decoded)) {
                $rawBanners = $decoded;
            }
        }

        if ($rawBanners === null && $request->has('banners') && is_array($request->input('banners'))) {
            $rawBanners = $request->input('banners');
        }

        if ($rawBanners === null) {
            $all = $request->all();
            if (!empty($all['banners_data'])) {
                $decoded = json_decode($all['banners_data'], true);
                if (is_array($decoded)) {
                    $rawBanners = $decoded;
                }
            }
        }

        if ($rawBanners === null || empty($rawBanners)) {
            $all = $request->all();
            if (!empty($all)) {
                $rawBanners = [$all];
            } else {
                $rawBanners = [];
            }
        }

        $processedBanners = [];
        foreach ($rawBanners as $index => $banner) {
            $id = !empty($banner['id']) ? (string) $banner['id'] : 'banner_' . ($index + 1);
            $imagePath = !empty($banner['image_path']) ? $banner['image_path'] : '/images/Alogo.png';

            // Check for file uploads: banner_image_{id}, banner_image_{index}, or legacy image
            if ($request->hasFile("banner_image_{$id}")) {
                $uploaded = $request->file("banner_image_{$id}")->store('banners', 'public');
                $imagePath = '/storage/' . $uploaded;
            } elseif ($request->hasFile("banner_image_{$index}")) {
                $uploaded = $request->file("banner_image_{$index}")->store('banners', 'public');
                $imagePath = '/storage/' . $uploaded;
            } elseif ($request->hasFile('image') && count($rawBanners) === 1) {
                $uploaded = $request->file('image')->store('banners', 'public');
                $imagePath = '/storage/' . $uploaded;
            }

            $processedBanners[] = [
                'id' => $id,
                'is_active' => isset($banner['is_active']) ? (bool) $banner['is_active'] : true,
                'layout_type' => !empty($banner['layout_type']) && in_array($banner['layout_type'], ['split', 'full']) ? $banner['layout_type'] : 'split',
                'overlay_text' => isset($banner['overlay_text']) ? (bool) $banner['overlay_text'] : false,
                'theme' => !empty($banner['theme']) ? trim($banner['theme']) : 'teal',
                'badge_text' => trim((string) ($banner['badge_text'] ?? '')),
                'title' => trim((string) ($banner['title'] ?? 'Welcome to')),
                'title_highlight' => trim((string) ($banner['title_highlight'] ?? '')),
                'description' => trim((string) ($banner['description'] ?? '')),
                
                // Primary Button (can be renamed or removed)
                'show_primary_button' => isset($banner['show_primary_button']) ? (bool) $banner['show_primary_button'] : (!empty($banner['primary_button_text'])),
                'primary_button_text' => !empty($banner['primary_button_text']) ? trim((string) $banner['primary_button_text']) : 'Shop Now',
                'primary_button_url' => trim((string) ($banner['primary_button_url'] ?? '/products')),
                'show_primary_button_arrow' => isset($banner['show_primary_button_arrow']) ? (bool) $banner['show_primary_button_arrow'] : true,

                // Secondary Button (can be renamed or removed)
                'show_secondary_button' => isset($banner['show_secondary_button']) ? (bool) $banner['show_secondary_button'] : (!empty($banner['secondary_button_text'])),
                'secondary_button_text' => trim((string) ($banner['secondary_button_text'] ?? '')),
                'secondary_button_url' => trim((string) ($banner['secondary_button_url'] ?? '')),

                // Illustration Image (can be replaced or removed)
                'show_image' => isset($banner['show_image']) ? (bool) $banner['show_image'] : true,
                'image_path' => $imagePath,

                'show_floating_cards' => isset($banner['show_floating_cards']) ? (bool) $banner['show_floating_cards'] : true,
                'card_1_title' => trim((string) ($banner['card_1_title'] ?? 'Products')),
                'card_1_subtitle' => trim((string) ($banner['card_1_subtitle'] ?? 'available')),
                'card_2_title' => trim((string) ($banner['card_2_title'] ?? 'CATEGORIES')),
                'card_2_subtitle' => trim((string) ($banner['card_2_subtitle'] ?? 'to explore')),
            ];
        }

        if (empty($processedBanners)) {
            return back()->withErrors(['banners' => 'At least one hero banner is required.']);
        }

        PlatformSetting::updateOrCreate(
            ['key' => 'home.hero_banners'],
            [
                'value' => json_encode($processedBanners),
                'description' => 'Marketplace Hero Banners configuration (multi-slide).',
                'is_active' => true,
            ]
        );

        // Also update legacy single-banner setting with the first banner
        PlatformSetting::updateOrCreate(
            ['key' => 'home.hero_banner'],
            [
                'value' => json_encode($processedBanners[0]),
                'description' => 'Marketplace Hero Banner configuration.',
                'is_active' => true,
            ]
        );

        return back()->with(
            'status',
            'Hero Banners updated successfully.'
        );
    }

    public function updateWhyAlona(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'eyebrow' => ['nullable', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:1000'],
            'cards' => ['required', 'array', 'size:3'],
            'cards.*.title' => ['required', 'string', 'max:100'],
            'cards.*.description' => ['required', 'string', 'max:500'],
        ]);

        $cards = [];
        foreach ($data['cards'] as $index => $card) {
            $cards[] = [
                'number' => sprintf('%02d', $index + 1),
                'title' => trim($card['title']),
                'description' => trim($card['description']),
            ];
        }

        $payload = [
            'eyebrow' => trim($data['eyebrow'] ?: 'WHY ALONA'),
            'title' => trim($data['title']),
            'description' => trim($data['description']),
            'cards' => $cards,
        ];

        PlatformSetting::updateOrCreate(
            ['key' => 'home.why_alona'],
            [
                'value' => json_encode($payload),
                'description' => 'Why Alona promotional banner on Buyer and Guest homepage.',
                'is_active' => true,
            ]
        );

        return back()->with(
            'status',
            '"Why Alona" banner updated successfully.'
        );
    }

    public function updateFooter(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'theme' => ['nullable', 'string', 'in:dark,light'],
            'brand' => ['nullable', 'array'],
            'brand.tagline' => ['nullable', 'string', 'max:500'],
            'brand.copyright' => ['nullable', 'string', 'max:255'],
            'brand.subtext' => ['nullable', 'string', 'max:255'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.id' => ['nullable', 'string', 'max:50'],
            'sections.*.title' => ['required', 'string', 'max:100'],
            'sections.*.icon' => ['nullable', 'string', 'max:50'],
            'sections.*.badge' => ['nullable', 'string', 'max:50'],
            'sections.*.links' => ['nullable', 'array'],
            'sections.*.links.*.label' => ['required', 'string', 'max:100'],
            'sections.*.links.*.url' => ['required', 'string', 'max:255', "regex:/^(https?:\/\/[a-zA-Z0-9_\-\.\/?&=%+#]+|#|\/[a-zA-Z0-9_\-\.\/?&=%+#]*)$/"],
        ]);

        $payload = [
            'theme' => $data['theme'] ?? 'dark',
            'brand' => [
                'tagline' => trim($data['brand']['tagline'] ?? 'Discover products, connect with sellers, and enjoy simple shopping with Alona.'),
                'copyright' => trim($data['brand']['copyright'] ?? ('© ' . date('Y') . ' Alona. All rights reserved.')),
                'subtext' => trim($data['brand']['subtext'] ?? 'A marketplace made simple.'),
            ],
            'sections' => array_values(array_map(function ($section, $idx) {
                return [
                    'id' => !empty($section['id']) ? $section['id'] : 'section_' . ($idx + 1),
                    'title' => trim($section['title']),
                    'icon' => !empty($section['icon']) ? trim($section['icon']) : 'bag',
                    'badge' => !empty($section['badge']) ? trim($section['badge']) : null,
                    'links' => array_values(array_map(function ($link) {
                        return [
                            'label' => trim($link['label']),
                            'url' => trim($link['url']),
                        ];
                    }, $section['links'] ?? [])),
                ];
            }, $data['sections'], array_keys($data['sections']))),
        ];

        PlatformSetting::updateOrCreate(
            ['key' => 'marketplace.footer'],
            [
                'value' => json_encode($payload),
                'description' => 'Marketplace footer sections, badges, and navigation links.',
                'is_active' => true,
            ]
        );

        return back()->with(
            'status',
            'Marketplace footer navigation updated successfully.'
        );
    }
}