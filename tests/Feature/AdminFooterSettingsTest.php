<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\FooterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminFooterSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_footer_service_returns_expected_sections(): void
    {
        $defaultConfig = FooterService::defaultConfig();

        $this->assertSame('light', $defaultConfig['theme']);
        $this->assertCount(4, $defaultConfig['sections']);

        $shopSection = $defaultConfig['sections'][0];
        $this->assertSame('Shop', $shopSection['title']);
        $this->assertSame('bag', $shopSection['icon']);
        $this->assertCount(4, $shopSection['links']);
        $this->assertSame('All Products', $shopSection['links'][0]['label']);
        $this->assertSame('/products', $shopSection['links'][0]['url']);

        $supportSection = $defaultConfig['sections'][1];
        $this->assertSame('Customer Support', $supportSection['title']);
        $this->assertSame('headphones', $supportSection['icon']);

        $policiesSection = $defaultConfig['sections'][3];
        $this->assertSame('Policies & Information', $policiesSection['title']);
        $this->assertSame('shield', $policiesSection['icon']);
    }

    public function test_admin_can_view_footer_configuration_in_settings_page(): void
    {
        $admin = User::factory()->create([
            'usertype' => 'admin',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.settings'));

        $response->assertOk();
        $response->assertInertia(function (Assert $page) {
            $page->component('Admin/Settings')
                ->has('footer')
                ->where('footer.sections.0.title', 'Shop')
                ->where('footer.sections.1.title', 'Customer Support')
                ->where('footer.sections.3.title', 'Policies & Information');
        });
    }

    public function test_admin_can_update_footer_settings(): void
    {
        $admin = User::factory()->create([
            'usertype' => 'admin',
            'status' => 'approved',
        ]);

        $customPayload = [
            'theme' => 'light',
            'brand' => [
                'tagline' => 'Curated marketplace for local artisans and buyers.',
                'copyright' => '© 2026 Alona Inc. All rights reserved.',
                'subtext' => 'Quality verified marketplace.',
            ],
            'sections' => [
                [
                    'id' => 'shop',
                    'title' => 'Shop',
                    'icon' => 'bag',
                    'links' => [
                        ['label' => 'All Products', 'url' => '/products'],
                        ['label' => 'Categories', 'url' => '/categories'],
                        ['label' => 'Cart', 'url' => '/buyer/cart'],
                        ['label' => 'Track My Order', 'url' => '/buyer/orders'],
                    ],
                ],
                [
                    'id' => 'customer-support',
                    'title' => 'Customer Support',
                    'icon' => 'headphones',
                    'links' => [
                        ['label' => 'Help Center', 'url' => '/help'],
                        ['label' => 'Frequently Asked Questions', 'url' => '/faq'],
                        ['label' => 'Contact Us', 'url' => '/help'],
                        ['label' => 'Complaints & Ticket Status', 'url' => '/buyer/complaints'],
                        ['label' => 'Buyer Protection', 'url' => '/policies'],
                    ],
                ],
                [
                    'id' => 'policies',
                    'title' => 'Policies & Information',
                    'icon' => 'shield',
                    'links' => [
                        ['label' => 'Privacy Policy', 'url' => '/policies#privacy'],
                        ['label' => 'Terms & Conditions', 'url' => '/policies#terms'],
                        ['label' => 'Shipping & Delivery Policy', 'url' => '/policies#shipping'],
                        ['label' => 'Returns & Refunds Policy', 'url' => '/policies#returns'],
                        ['label' => 'Payment Methods', 'url' => '/buyer/payment-methods'],
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($admin)
            ->post(route('admin.settings.footer.update'), $customPayload);

        $response->assertRedirect();
        $response->assertSessionHas('status', 'Marketplace footer navigation updated successfully.');

        $this->assertDatabaseHas('platform_settings', [
            'key' => 'marketplace.footer',
            'is_active' => true,
        ]);

        $footerData = FooterService::get();
        $this->assertSame('Curated marketplace for local artisans and buyers.', $footerData['brand']['tagline']);
        $this->assertCount(3, $footerData['sections']);
        $this->assertSame('Shop', $footerData['sections'][0]['title']);
        $this->assertSame('Customer Support', $footerData['sections'][1]['title']);
        $this->assertSame('Policies & Information', $footerData['sections'][2]['title']);
    }

    public function test_non_admin_cannot_update_footer_settings(): void
    {
        $buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($buyer)
            ->post(route('admin.settings.footer.update'), [
                'theme' => 'light',
                'sections' => [
                    [
                        'title' => 'Hacked Section',
                        'links' => [
                            ['label' => 'Spam', 'url' => '/spam'],
                        ],
                    ],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_shared_inertia_prop_includes_footer_settings(): void
    {
        $buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $response = $this->actingAs($buyer)->get(route('buyer.dashboard'));

        $response->assertOk();
        $response->assertInertia(function (Assert $page) {
            $page->has('footerSettings')
                ->where('footerSettings.theme', 'light')
                ->has('footerSettings.sections', 4)
                ->where('footerSettings.sections.0.title', 'Shop')
                ->where('footerSettings.sections.1.title', 'Customer Support')
                ->where('footerSettings.sections.3.title', 'Policies & Information');
        });
    }
}
