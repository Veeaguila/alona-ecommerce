<?php

namespace Tests\Feature;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SellerStoreProfileTest extends TestCase
{
    use RefreshDatabase;

    protected User $seller1;
    protected User $seller2;
    protected User $sellerPending;
    protected User $buyer;
    protected Category $category;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seller1 = User::factory()->create([
            'name' => 'Maria Clara',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'MC Store',
            'store_description' => 'Original description for seller 1.',
            'contact_no' => '09171234567',
            'province' => 'Bulacan',
            'municipality' => 'Malolos',
            'barangay' => 'Santo Rosario',
            'street_address' => '123 Artisan Way',
            'address' => '123 Artisan Way, Santo Rosario, Malolos, Bulacan',
        ]);

        $this->seller2 = User::factory()->create([
            'name' => 'RV Tech Owner',
            'usertype' => 'seller',
            'status' => 'approved',
            'store_name' => 'RV Gadget Hub',
            'store_description' => 'Original description for seller 2.',
            'contact_no' => '09187654321',
        ]);

        $this->sellerPending = User::factory()->create([
            'name' => 'Pedro Pending',
            'usertype' => 'seller',
            'status' => 'pending',
            'store_name' => 'Pedro Handwoven Crafts',
            'contact_no' => '09191112233',
        ]);

        $this->buyer = User::factory()->create([
            'name' => 'Juan Dela Cruz',
            'usertype' => 'buyer',
            'status' => 'approved',
        ]);

        $this->category = Category::create([
            'name' => 'Handicrafts & Decor',
            'slug' => 'handicrafts-and-decor',
            'is_active' => true,
        ]);

        $this->product = Product::create([
            'category_id' => $this->category->id,
            'seller_id' => $this->seller1->id,
            'name' => 'P-02 Rattan Fruit Basket',
            'slug' => 'p-02-rattan-fruit-basket',
            'price' => 450.00,
            'stock' => 20,
            'status' => 'approved',
        ]);
    }

    /**
     * SEL-PROF-001: Update store profile with all valid data.
     */
    public function test_sel_prof_001_update_store_profile_with_all_valid_data(): void
    {
        $logo = UploadedFile::fake()->image('store_logo.png', 400, 400)->size(300);
        $banner = UploadedFile::fake()->image('store_banner.jpg', 1200, 300)->size(1800);

        $response = $this->actingAs($this->seller1)
            ->patch(route('seller.store.update'), [
                'store_name' => 'Maria Clara Handicrafts PH',
                'store_description' => 'Handwoven bags and home decor from Bulacan artisans.',
                'contact_no' => '09189876543',
                'province' => 'Bulacan',
                'municipality' => 'Malolos',
                'barangay' => 'Santo Rosario',
                'street_address' => '123 Artisan Way',
                'store_logo' => $logo,
                'store_banner' => $banner,
            ]);

        $response->assertRedirect();
        $this->seller1->refresh();

        $this->assertSame('Maria Clara Handicrafts PH', $this->seller1->store_name);
        $this->assertSame('Handwoven bags and home decor from Bulacan artisans.', $this->seller1->store_description);
        $this->assertSame('09189876543', $this->seller1->contact_no);
        $this->assertNotNull($this->seller1->store_logo_path);
        $this->assertNotNull($this->seller1->store_banner_path);
        Storage::disk('public')->assertExists($this->seller1->store_logo_path);
        Storage::disk('public')->assertExists($this->seller1->store_banner_path);

        // Buyer's store page shows the new name, description, logo, and banner
        $buyerResponse = $this->actingAs($this->buyer)->get(route('buyer.store.show', $this->seller1->id));
        $buyerResponse->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/SellerStore')
                ->where('seller.store_name', 'Maria Clara Handicrafts PH')
                ->where('seller.store_description', 'Handwoven bags and home decor from Bulacan artisans.')
                ->where('seller.store_logo_path', $this->seller1->store_logo_path)
                ->where('seller.store_banner_path', $this->seller1->store_banner_path)
            );

        // No other store's data changed
        $this->seller2->refresh();
        $this->assertSame('RV Gadget Hub', $this->seller2->store_name);
        $this->assertSame('Original description for seller 2.', $this->seller2->store_description);
    }

    /**
     * SEL-PROF-002: Each required field left blank.
     */
    public function test_sel_prof_002_required_fields_left_blank(): void
    {
        $initialName = $this->seller1->store_name;
        $initialContact = $this->seller1->contact_no;

        // 1. Blank Store Name
        $res1 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => '',
            'contact_no' => $initialContact,
        ]);
        $res1->assertSessionHasErrors('store_name');

        // 2. Blank Contact Number
        $res2 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => $initialName,
            'contact_no' => '',
        ]);
        $res2->assertSessionHasErrors('contact_no');

        // 3. Blank Pickup Address parts
        $res3 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => $initialName,
            'contact_no' => $initialContact,
            'province' => '',
            'municipality' => 'Malolos',
            'barangay' => 'Santo Rosario',
            'street_address' => '123 Artisan Way',
        ]);
        $res3->assertSessionHasErrors('province');

        $res4 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => $initialName,
            'contact_no' => $initialContact,
            'province' => 'Bulacan',
            'municipality' => '',
        ]);
        $res4->assertSessionHasErrors('municipality');

        $res5 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => $initialName,
            'contact_no' => $initialContact,
            'barangay' => '',
        ]);
        $res5->assertSessionHasErrors('barangay');

        $res6 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => $initialName,
            'contact_no' => $initialContact,
            'street_address' => '',
        ]);
        $res6->assertSessionHasErrors('street_address');

        // Verify original values are untouched
        $this->seller1->refresh();
        $this->assertSame($initialName, $this->seller1->store_name);
        $this->assertSame($initialContact, $this->seller1->contact_no);
    }

    /**
     * SEL-PROF-003: Fields containing only spaces.
     */
    public function test_sel_prof_003_fields_containing_only_spaces(): void
    {
        $oldStoreName = $this->seller1->store_name;

        $response = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => '     ', // 5 spaces
        ]);

        $response->assertSessionHasErrors('store_name');
        $this->seller1->refresh();
        $this->assertSame($oldStoreName, $this->seller1->store_name);
    }

    /**
     * SEL-PROF-004: Store name already used by another store.
     */
    public function test_sel_prof_004_store_name_already_used_by_another_store(): void
    {
        $oldStoreName = $this->seller1->store_name;

        // 1. Exact match
        $res1 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'RV Gadget Hub',
        ]);
        $res1->assertSessionHasErrors('store_name');

        // 2. Lowercase match (case-insensitive check)
        $res2 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'rv gadget hub',
        ]);
        $res2->assertSessionHasErrors('store_name');

        // 3. Leading and trailing spaces
        $res3 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => '  RV Gadget Hub  ',
        ]);
        $res3->assertSessionHasErrors('store_name');

        $this->seller1->refresh();
        $this->assertSame($oldStoreName, $this->seller1->store_name);
    }

    /**
     * SEL-PROF-005: Store name length at the limits.
     */
    public function test_sel_prof_005_store_name_length_at_limits(): void
    {
        // 2 characters: reject
        $res2 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC',
        ]);
        $res2->assertSessionHasErrors('store_name');

        // 3 characters: accept
        $res3 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MCH',
        ]);
        $res3->assertSessionDoesntHaveErrors('store_name');
        $this->seller1->refresh();
        $this->assertSame('MCH', $this->seller1->store_name);

        // 30 characters: accept
        $name30 = 'Maria Clara Handicrafts Bulaca'; // exactly 30 chars
        $this->assertSame(30, strlen($name30));
        $res30 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => $name30,
        ]);
        $res30->assertSessionDoesntHaveErrors('store_name');
        $this->seller1->refresh();
        $this->assertSame($name30, $this->seller1->store_name);

        // 31 characters: reject
        $name31 = 'Maria Clara Handicrafts Bulacan'; // exactly 31 chars
        $this->assertSame(31, strlen($name31));
        $res31 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => $name31,
        ]);
        $res31->assertSessionHasErrors('store_name');
        $this->seller1->refresh();
        // Kept 30 chars; not silently cut off
        $this->assertSame($name30, $this->seller1->store_name);
    }

    /**
     * SEL-PROF-006: Description length at the limit.
     */
    public function test_sel_prof_006_description_length_at_limit(): void
    {
        // 500 characters: accept
        $desc500 = str_repeat('A', 500);
        $res500 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC Store',
            'store_description' => $desc500,
        ]);
        $res500->assertSessionDoesntHaveErrors('store_description');
        $this->seller1->refresh();
        $this->assertSame($desc500, $this->seller1->store_description);

        // 501 characters: reject
        $desc501 = str_repeat('B', 501);
        $res501 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC Store',
            'store_description' => $desc501,
        ]);
        $res501->assertSessionHasErrors('store_description');
        $this->seller1->refresh();
        // 500 chars preserved; not silently cut
        $this->assertSame($desc500, $this->seller1->store_description);
    }

    /**
     * SEL-PROF-007: Contact number format.
     */
    public function test_sel_prof_007_contact_number_format(): void
    {
        // 09189876543 (accept)
        $res1 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC Store',
            'contact_no' => '09189876543',
        ]);
        $res1->assertSessionDoesntHaveErrors('contact_no');
        $this->seller1->refresh();
        $this->assertSame('09189876543', $this->seller1->contact_no);

        // +639189876543 (accept)
        $res2 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC Store',
            'contact_no' => '+639189876543',
        ]);
        $res2->assertSessionDoesntHaveErrors('contact_no');
        $this->seller1->refresh();
        $this->assertSame('+639189876543', $this->seller1->contact_no);

        // 0918987654 (10 digits, reject)
        $res3 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC Store',
            'contact_no' => '0918987654',
        ]);
        $res3->assertSessionHasErrors('contact_no');

        // 091898765432 (12 digits, reject)
        $res4 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC Store',
            'contact_no' => '091898765432',
        ]);
        $res4->assertSessionHasErrors('contact_no');

        // 0918-ABC-6543 (non-digits, reject)
        $res5 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC Store',
            'contact_no' => '0918-ABC-6543',
        ]);
        $res5->assertSessionHasErrors('contact_no');

        // 08189876543 (invalid prefix 08, reject)
        $res6 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC Store',
            'contact_no' => '08189876543',
        ]);
        $res6->assertSessionHasErrors('contact_no');
    }

    /**
     * SEL-PROF-008: Logo and banner file type and size.
     */
    public function test_sel_prof_008_logo_and_banner_file_type_and_size(): void
    {
        // 4.9 MB JPG (accept - max is 5120 KB = 5.0 MB)
        $file49 = UploadedFile::fake()->image('size_4.9MB.jpg', 600, 600)->size(5017);
        $res49 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC Store',
            'store_logo' => $file49,
        ]);
        $res49->assertSessionDoesntHaveErrors('store_logo');

        // 5.0 MB JPG (accept)
        $file50 = UploadedFile::fake()->image('size_5.0MB.jpg', 600, 600)->size(5120);
        $res50 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC Store',
            'store_banner' => $file50,
        ]);
        $res50->assertSessionDoesntHaveErrors('store_banner');

        // 5.1 MB file (reject)
        $file51 = UploadedFile::fake()->image('size_5.1MB.jpg', 600, 600)->size(5222);
        $res51 = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC Store',
            'store_logo' => $file51,
        ]);
        $res51->assertSessionHasErrors('store_logo');

        // DOCX file (reject)
        $docx = UploadedFile::fake()->create('price_list.docx', 500, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        $resDocx = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC Store',
            'store_logo' => $docx,
        ]);
        $resDocx->assertSessionHasErrors('store_logo');

        // EXE file (reject)
        $exe = UploadedFile::fake()->create('installer.exe', 500, 'application/x-msdownload');
        $resExe = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC Store',
            'store_logo' => $exe,
        ]);
        $resExe->assertSessionHasErrors('store_logo');

        // Renamed EXE (renamed_exe.jpg, reject via mime check)
        $renamedExe = UploadedFile::fake()->create('renamed_exe.jpg', 500, 'application/x-msdownload');
        $resRenamed = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC Store',
            'store_logo' => $renamedExe,
        ]);
        $resRenamed->assertSessionHasErrors('store_logo');
    }

    /**
     * SEL-PROF-009: Turn on vacation mode.
     */
    public function test_sel_prof_009_turn_on_vacation_mode(): void
    {
        $vacationMsg = 'We are taking a short break for warehouse audit.';

        $res = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC Store',
            'vacation_mode' => true,
            'vacation_message' => $vacationMsg,
        ]);
        $res->assertRedirect();
        $this->seller1->refresh();

        $this->assertTrue($this->seller1->vacation_mode);
        $this->assertSame($vacationMsg, $this->seller1->vacation_message);

        // Buyer visits store -> sees vacation mode
        $buyerStore = $this->actingAs($this->buyer)->get(route('buyer.store.show', $this->seller1->id));
        $buyerStore->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Buyer/SellerStore')
                ->where('seller.vacation_mode', true)
                ->where('seller.vacation_message', $vacationMsg)
            );

        // Add to cart is blocked
        $cartRes = $this->actingAs($this->buyer)->post(route('buyer.cart.store', $this->product->id), [
            'quantity' => 1,
        ]);
        $cartRes->assertSessionHasErrors('cart');

        // Checkout of item from this seller is blocked
        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $checkoutRes = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '456 Acacia Ave, Quezon City',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'selected_items' => [$cartItem->id],
        ]);
        $checkoutRes->assertSessionHasErrors('selected_items');
    }

    /**
     * SEL-PROF-010: Turn off vacation mode.
     */
    public function test_sel_prof_010_turn_off_vacation_mode(): void
    {
        $this->seller1->update([
            'vacation_mode' => true,
            'vacation_message' => 'On break',
        ]);

        // Turn off vacation mode
        $res = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC Store',
            'vacation_mode' => false,
        ]);
        $res->assertRedirect();
        $this->seller1->refresh();

        $this->assertFalse($this->seller1->vacation_mode);

        // Ordering is restored
        $cartItem = CartItem::create([
            'user_id' => $this->buyer->id,
            'product_id' => $this->product->id,
            'quantity' => 1,
        ]);

        $checkoutRes = $this->actingAs($this->buyer)->post(route('buyer.orders.store'), [
            'shipping_address' => '456 Acacia Ave, Quezon City',
            'payment_method' => 'cod',
            'shipping_method' => 'standard',
            'selected_items' => [$cartItem->id],
        ]);
        $checkoutRes->assertRedirect();
        $this->assertDatabaseHas('orders', [
            'user_id' => $this->buyer->id,
        ]);
    }

    /**
     * SEL-PROF-011: Seller that is not Approved tries to publish.
     */
    public function test_sel_prof_011_unapproved_seller_cannot_publish_product(): void
    {
        // 1. Pending seller tries to publish -> blocked
        $publishRes = $this->actingAs($this->sellerPending)->post('/seller/products', [
            'name' => 'Handwoven Coin Purse',
            'category_id' => $this->category->id,
            'price' => 120.00,
            'stock' => 10,
            'description' => 'A small purse.',
            'status' => 'approved',
        ]);
        // Either redirected to application status or blocked with error
        $this->assertTrue(
            $publishRes->isRedirect(route('application.status')) ||
            $publishRes->isRedirect() && session()->has('errors')
        );

        // 2. Pending seller saves as Draft -> succeeds
        $draftRes = $this->actingAs($this->sellerPending)->post('/seller/products', [
            'name' => 'Handwoven Coin Purse Draft',
            'category_id' => $this->category->id,
            'price' => 120.00,
            'stock' => 10,
            'description' => 'A small purse draft.',
            'status' => 'draft',
        ]);
        $draftRes->assertRedirect(route('seller.products'));

        $draftProduct = Product::where('name', 'Handwoven Coin Purse Draft')->first();
        $this->assertNotNull($draftProduct);
        $this->assertSame('draft', $draftProduct->status);

        // 3. Draft product is never visible to buyers
        $marketplaceRes = $this->get(route('buyer.products', ['search' => 'Handwoven Coin Purse Draft']));
        $marketplaceRes->assertOk();
        $marketplaceRes->assertInertia(fn (Assert $page) => $page
            ->component('Buyer/Products')
            ->where('products.data', fn ($items) => count($items) === 0)
        );
    }

    /**
     * SEL-PROF-012: Script and HTML in text fields.
     */
    public function test_sel_prof_012_script_and_html_in_text_fields(): void
    {
        $maliciousPayload = "<b>Sale!</b><script>alert('x')</script>\"><img src=x onerror=alert(1)>";

        $response = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'store_name' => 'MC Safe Store',
            'store_description' => $maliciousPayload,
            'store_tagline' => $maliciousPayload,
            'vacation_message' => $maliciousPayload,
        ]);

        $response->assertRedirect();
        $this->seller1->refresh();

        // Script and img tags must be stripped
        $this->assertStringNotContainsString('<script>', $this->seller1->store_description);
        $this->assertStringNotContainsString('</script>', $this->seller1->store_description);
        $this->assertStringNotContainsString('<img', $this->seller1->store_description);
        $this->assertStringNotContainsString('onerror', $this->seller1->store_description);

        $this->assertStringNotContainsString('<script>', $this->seller1->vacation_message);
        $this->assertStringNotContainsString('<img', $this->seller1->vacation_message);
    }

    /**
     * SEL-PROF-013: Seller cannot edit another store's settings.
     */
    public function test_sel_prof_013_seller_cannot_edit_another_store_settings(): void
    {
        $seller2OriginalName = $this->seller2->store_name;
        $seller2OriginalDesc = $this->seller2->store_description;

        // 1. Seller 1 tries to view Seller 2's store settings via URL -> 403 Forbidden
        $viewRes = $this->actingAs($this->seller1)->get("/seller/store/{$this->seller2->id}");
        $viewRes->assertForbidden();

        // 2. Seller 1 tries to patch Seller 2's store settings via URL -> 403 Forbidden
        $patchUrlRes = $this->actingAs($this->seller1)->patch("/seller/store/{$this->seller2->id}", [
            'store_name' => 'Hacked Store Name',
            'store_description' => 'Hacked description',
        ]);
        $patchUrlRes->assertForbidden();

        // 3. Seller 1 tries to pass seller_id in payload -> 403 Forbidden
        $patchBodyRes = $this->actingAs($this->seller1)->patch(route('seller.store.update'), [
            'seller_id' => $this->seller2->id,
            'store_name' => 'Hacked Store Name',
            'store_description' => 'Hacked description',
        ]);
        $patchBodyRes->assertForbidden();

        // Seller 2's data remains completely untouched
        $this->seller2->refresh();
        $this->assertSame($seller2OriginalName, $this->seller2->store_name);
        $this->assertSame($seller2OriginalDesc, $this->seller2->store_description);
    }
}
