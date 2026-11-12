<?php

namespace Tests\Feature;

use App\Models\AdminAuditLog;
use App\Models\AdminNotification;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductComplianceReview;
use App\Models\SellerNotification;
use App\Models\User;
use App\Services\AiProductVerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AiProductApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller;
    protected User $admin;
    protected User $buyer;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Config::set('services.ai.provider', 'openai');
        Config::set('services.gemini.api_key', '');
        Config::set('services.openai.api_key', 'test-openai-api-key-xyz');
        Config::set('services.openai.model', 'gpt-4o-mini');
        Config::set('services.openai.confidence_threshold', 0.85);
        Config::set('services.openai.enabled', true);

        $this->seller = User::factory()->create([
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'Bohol Gadgets Hub',
        ]);

        $this->admin = User::factory()->create([
            'usertype' => 'admin',
            'status' => 'approved',
        ]);

        $this->buyer = User::factory()->create([
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $this->category = Category::create([
            'name' => 'Audio & Accessories',
            'slug' => 'audio-accessories',
            'is_active' => true,
        ]);
    }

    /**
     * Helper to create a product model without relying on a factory.
     */
    protected function createProduct(array $overrides = []): Product
    {
        $name = $overrides['name'] ?? 'Test Product ' . uniqid();

        return Product::create(array_merge([
            'seller_id' => $this->seller->id,
            'category_id' => $this->category->id,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . uniqid(),
            'description' => 'Test product description for automated test suite.',
            'price' => 499.00,
            'stock' => 20,
            'weight' => 0.5,
            'status' => 'pending',
        ], $overrides));
    }

    /**
     * Helper to mock OpenAI Vision API responses.
     */
    protected function mockOpenAiVision(array $data, int $status = 200): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode($data),
                        ],
                    ],
                ],
            ], $status),
        ]);
    }

    /**
     * Test 1: Matching image and description -> automatic approval.
     */
    public function test_matching_image_and_description_triggers_automatic_approval(): void
    {
        $this->mockOpenAiVision([
            'is_match' => true,
            'confidence' => 0.96,
            'issue_type' => 'none',
            'explanation' => 'The uploaded product image clearly matches the wireless bluetooth headphones specified in the title and description.',
            'recommended_action' => 'Automatic approval recommended.',
        ]);

        $image = UploadedFile::fake()->image('headphones.jpg', 600, 600);

        $response = $this->actingAs($this->seller)->post('/seller/products', [
            'name' => 'Wireless Bluetooth Headphones Pro',
            'category_id' => $this->category->id,
            'price' => 1499.00,
            'stock' => 50,
            'weight' => 0.4,
            'description' => 'Over-ear noise cancelling wireless bluetooth headphones with 30-hour battery life.',
            'image' => $image,
            'status' => 'pending',
        ]);

        $response->assertRedirect('/seller/products');
        $response->assertSessionHas('status');

        $product = Product::where('name', 'Wireless Bluetooth Headphones Pro')->first();
        $this->assertNotNull($product);
        $this->assertSame('approved', $product->status);
        $this->assertSame('automatic', $product->approval_method);
        $this->assertSame('passed', $product->ai_verification_status);
        $this->assertEqualsWithDelta(0.96, (float) $product->ai_confidence_score, 0.001);
        $this->assertNotNull($product->ai_verified_at);

        // Verification compliance review log created
        $review = ProductComplianceReview::where('product_id', $product->id)->first();
        $this->assertNotNull($review);
        $this->assertNull($review->admin_id);
        $this->assertSame('approved', $review->action);
        $this->assertStringContainsString('Automatically approved by AI', $review->note);

        // Audit log created
        $auditLog = AdminAuditLog::where('action', 'product_auto_approved')->first();
        $this->assertNotNull($auditLog);
        $this->assertStringContainsString('Wireless Bluetooth Headphones Pro', $auditLog->description);

        // Visible to buyers
        $buyerResponse = $this->actingAs($this->buyer)->get('/buyer/products');
        $buyerResponse->assertOk();
        $buyerResponse->assertSee('Wireless Bluetooth Headphones Pro');
    }

    /**
     * Test 2: Mismatched image and description -> pending + admin notification with explanation.
     */
    public function test_mismatched_image_and_description_stays_pending_and_notifies_admin(): void
    {
        $this->mockOpenAiVision([
            'is_match' => false,
            'confidence' => 0.94,
            'issue_type' => 'image_mismatch',
            'explanation' => 'Automatic approval failed because the uploaded image shows running shoes, but the product name and description identify the item as wireless headphones. Please review the image and product details.',
            'recommended_action' => 'Please review the image and product details.',
        ]);

        $image = UploadedFile::fake()->image('shoes.jpg', 600, 600);

        $response = $this->actingAs($this->seller)->post('/seller/products', [
            'name' => 'Studio Wireless Headphones',
            'category_id' => $this->category->id,
            'price' => 1999.00,
            'stock' => 15,
            'weight' => 0.5,
            'description' => 'High-fidelity audio headphones.',
            'image' => $image,
            'status' => 'pending',
        ]);

        $response->assertRedirect('/seller/products');

        $product = Product::where('name', 'Studio Wireless Headphones')->first();
        $this->assertNotNull($product);
        $this->assertSame('pending', $product->status);
        $this->assertSame('manual', $product->approval_method);
        $this->assertSame('mismatch', $product->ai_verification_status);
        $this->assertStringContainsString('uploaded image shows running shoes', $product->ai_verification_explanation);

        // Admin notification created
        $notification = AdminNotification::where('product_id', $product->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame('ai_product_review_needed', $notification->type);
        $this->assertStringContainsString('Studio Wireless Headphones', $notification->title);
        $this->assertStringContainsString('running shoes', $notification->message);
        $this->assertSame('Bohol Gadgets Hub', $notification->data['seller_name']);
        $this->assertSame('mismatch', $notification->data['ai_verification_status']);
        $this->assertNotNull($notification->action_url);

        // Must not be visible in buyer storefront
        $buyerResponse = $this->actingAs($this->buyer)->get('/buyer/products');
        $buyerResponse->assertOk();
        $buyerResponse->assertDontSee('Studio Wireless Headphones');
    }

    /**
     * Test 3: Unclear or blurry image -> pending + admin notification with explanation.
     */
    public function test_unclear_or_blurry_image_stays_pending_and_notifies_admin(): void
    {
        $this->mockOpenAiVision([
            'is_match' => false,
            'confidence' => 0.35,
            'issue_type' => 'unclear_image',
            'explanation' => 'Automatic approval failed because the uploaded product image is too blurry to identify the item reliably. Please request a clearer image or review the product manually.',
            'recommended_action' => 'Please request a clearer image or review the product manually.',
        ]);

        $image = UploadedFile::fake()->image('blurry.jpg', 600, 600);

        $this->actingAs($this->seller)->post('/seller/products', [
            'name' => 'Action Camera 4K',
            'category_id' => $this->category->id,
            'price' => 2999.00,
            'stock' => 5,
            'weight' => 0.3,
            'description' => 'Waterproof 4K action camera.',
            'image' => $image,
            'status' => 'pending',
        ]);

        $product = Product::where('name', 'Action Camera 4K')->first();
        $this->assertNotNull($product);
        $this->assertSame('pending', $product->status);
        $this->assertSame('unclear_image', $product->ai_verification_status);
        $this->assertStringContainsString('too blurry', $product->ai_verification_explanation);

        $notification = AdminNotification::where('product_id', $product->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame('ai_product_review_needed', $notification->type);
        $this->assertStringContainsString('too blurry', $notification->message);
        $this->assertSame('unclear_image', $notification->data['ai_verification_status']);
    }

    /**
     * Test 4: AI API failure / 500 error -> pending + technical-error notification (never auto-approves).
     */
    public function test_ai_api_failure_keeps_product_pending_with_technical_notification(): void
    {
        Http::fake([
            'https://api.openai.com/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Rate limit exceeded or service unavailable.'],
            ], 500),
        ]);

        $image = UploadedFile::fake()->image('speaker.jpg', 600, 600);

        $this->actingAs($this->seller)->post('/seller/products', [
            'name' => 'Portable Bluetooth Speaker',
            'category_id' => $this->category->id,
            'price' => 899.00,
            'stock' => 20,
            'weight' => 0.6,
            'description' => 'Compact outdoor bluetooth speaker.',
            'image' => $image,
            'status' => 'pending',
        ]);

        $product = Product::where('name', 'Portable Bluetooth Speaker')->first();
        $this->assertNotNull($product);
        // Never auto-approve on API failure
        $this->assertSame('pending', $product->status);
        $this->assertSame('manual', $product->approval_method);
        $this->assertSame('service_error', $product->ai_verification_status);
        $this->assertStringContainsString('service was unavailable', $product->ai_verification_explanation);

        $notification = AdminNotification::where('product_id', $product->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame('ai_product_review_needed', $notification->type);
        $this->assertStringContainsString('unavailable', $notification->message);
        $this->assertSame('service_error', $notification->data['ai_verification_status']);
    }

    /**
     * Test 5: Missing required information / policy violation -> pending + explanation.
     */
    public function test_policy_violation_keeps_product_pending_with_prohibited_item_notification(): void
    {
        $image = UploadedFile::fake()->image('item.jpg', 600, 600);

        // Product contains prohibited item terms
        $this->actingAs($this->seller)->post('/seller/products', [
            'name' => 'Assault Firearm Replica Gun',
            'category_id' => $this->category->id,
            'price' => 5000.00,
            'stock' => 2,
            'weight' => 2.0,
            'description' => 'Authentic look firearm weapon for display.',
            'image' => $image,
            'status' => 'pending',
        ]);

        $product = Product::where('name', 'Assault Firearm Replica Gun')->first();
        $this->assertNotNull($product);
        $this->assertSame('pending', $product->status);
        $this->assertSame('policy_violation', $product->ai_verification_status);
        $this->assertStringContainsString('violating marketplace safety policies', $product->ai_verification_explanation);

        $notification = AdminNotification::where('product_id', $product->id)->first();
        $this->assertNotNull($notification);
        $this->assertSame('ai_product_review_needed', $notification->type);
        $this->assertStringContainsString('violating marketplace safety policies', $notification->message);
        $this->assertSame('policy_violation', $notification->data['ai_verification_status']);
    }

    /**
     * Test 6: Unauthorized approval attempt -> blocked (403 for non-admin).
     */
    public function test_unauthorized_user_cannot_approve_product_or_trigger_ai_recheck(): void
    {
        $product = $this->createProduct([
            'name' => 'Unauthorized Target Product',
            'status' => 'pending',
        ]);

        // Non-admin buyer attempt to approve
        $approveAttempt = $this->actingAs($this->buyer)->patch('/admin/compliance/' . $product->id . '/status', [
            'action' => 'approve',
        ]);
        $approveAttempt->assertForbidden();

        // Non-admin buyer attempt to trigger AI re-verification
        $recheckAttempt = $this->actingAs($this->buyer)->post('/admin/compliance/' . $product->id . '/verify-ai');
        $recheckAttempt->assertForbidden();

        // Seller attempt to approve their own product through admin compliance
        $sellerAttempt = $this->actingAs($this->seller)->patch('/admin/compliance/' . $product->id . '/status', [
            'action' => 'approve',
        ]);
        $sellerAttempt->assertForbidden();

        // Ensure status did not change
        $this->assertSame('pending', $product->fresh()->status);
    }

    /**
     * Test 7: Successful manual admin review -> existing approval and rejection workflow works.
     */
    public function test_manual_admin_approval_and_rejection_workflow(): void
    {
        $product = $this->createProduct([
            'name' => 'Admin Review Subject',
            'status' => 'pending',
            'approval_method' => 'manual',
            'ai_verification_status' => 'mismatch',
        ]);

        // Create an active admin notification for this product
        AdminNotification::create([
            'admin_id' => null,
            'product_id' => $product->id,
            'type' => 'ai_product_review_needed',
            'title' => 'Product Review Required',
            'message' => 'Mismatch detected',
            'data' => [],
            'action_url' => '/admin/compliance',
        ]);

        // Admin manually approves
        $response = $this->actingAs($this->admin)->patch('/admin/compliance/' . $product->id . '/status', [
            'action' => 'approve',
            'note' => 'Manually inspected and verified authentic packaging.',
        ]);

        $response->assertRedirect();
        $this->assertSame('approved', $product->fresh()->status);
        $this->assertSame('manual', $product->fresh()->approval_method);

        // Notification marked as read/resolved
        $this->assertNotNull(AdminNotification::where('product_id', $product->id)->first()->read_at);

        // Seller notified of approval
        $this->assertDatabaseHas('seller_notifications', [
            'user_id' => $this->seller->id,
            'type' => 'product_approved',
        ]);

        // Now test Admin Rejection workflow on a new product
        $rejectedProduct = $this->createProduct([
            'name' => 'Rejected Item Sample',
            'status' => 'pending',
            'approval_method' => 'manual',
        ]);

        $rejectResponse = $this->actingAs($this->admin)->patch('/admin/compliance/' . $rejectedProduct->id . '/status', [
            'action' => 'reject',
            'note' => 'Product counterfeiting suspected from the provided images.',
        ]);

        $rejectResponse->assertRedirect();
        $this->assertSame('inactive', $rejectedProduct->fresh()->status);
        $this->assertSame('Product counterfeiting suspected from the provided images.', $rejectedProduct->fresh()->rejection_reason);

        // Seller notified of rejection with reason
        $this->assertDatabaseHas('seller_notifications', [
            'user_id' => $this->seller->id,
            'type' => 'product_rejected',
        ]);
    }

    /**
     * Test 8: Repeated processing -> no duplicate notifications created.
     */
    public function test_repeated_ai_processing_does_not_create_duplicate_notifications(): void
    {
        $this->mockOpenAiVision([
            'is_match' => false,
            'confidence' => 0.88,
            'issue_type' => 'description_inconsistency',
            'explanation' => 'Automatic approval failed because the product image appears to show a red shirt, while the description specifies a blue shirt. Please verify the correct product color.',
            'recommended_action' => 'Please verify the correct product color.',
        ]);

        $imagePath = 'products/shirt_' . uniqid() . '.jpg';
        Storage::disk('public')->put($imagePath, 'fake-image-content-bytes');

        $product = $this->createProduct([
            'name' => 'Casual Cotton Shirt',
            'description' => 'A blue shirt with long sleeves.',
            'image_path' => $imagePath,
            'status' => 'pending',
        ]);

        $service = app(AiProductVerificationService::class);

        // First verification pass
        $service->verifyAndProcess($product);
        $this->assertSame(1, AdminNotification::where('product_id', $product->id)->count());

        // Second verification pass without admin resolving the first notification
        $service->verifyAndProcess($product);
        $this->assertSame(1, AdminNotification::where('product_id', $product->id)->count(), 'Duplicate unread notification should not be created');
    }

    /**
     * Test 9: Google Gemini Vision provider verification.
     */
    public function test_google_gemini_vision_verification_approves_matching_product(): void
    {
        Config::set('services.ai.provider', 'gemini');
        Config::set('services.gemini.api_key', 'test-gemini-key-12345');
        Config::set('services.gemini.model', 'gemini-2.5-flash');

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                [
                                    'text' => json_encode([
                                        'is_match' => true,
                                        'confidence' => 0.98,
                                        'issue_type' => 'none',
                                        'explanation' => 'Product verified by Google Gemini: Image and product details match.',
                                        'detected_item' => 'smartwatch',
                                        'recommended_action' => 'Automatic approval recommended.',
                                    ]),
                                ],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $image = UploadedFile::fake()->image('smartwatch.jpg', 600, 600);

        $response = $this->actingAs($this->seller)->post('/seller/products', [
            'name' => 'Fitness Smartwatch Waterproof',
            'category_id' => $this->category->id,
            'price' => 2499.00,
            'stock' => 30,
            'weight' => 0.2,
            'description' => 'Waterproof fitness smartwatch with heart rate monitor.',
            'image' => $image,
            'status' => 'pending',
        ]);

        $response->assertRedirect('/seller/products');

        $product = Product::where('name', 'Fitness Smartwatch Waterproof')->first();
        $this->assertNotNull($product);
        $this->assertSame('approved', $product->status);
        $this->assertSame('automatic', $product->approval_method);
        $this->assertSame('passed', $product->ai_verification_status);
        $this->assertEqualsWithDelta(0.98, (float) $product->ai_confidence_score, 0.001);
    }
}
