<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;

class FaqAndSupportSettingSeeder extends Seeder
{
    public function run(): void
    {
        // Seed Platform Settings for Contact Details
        $contactSettings = [
            [
                'key' => 'support.email',
                'value' => 'support@alona.ph',
                'description' => 'Customer support email displayed on the buyer Help Center and communication channels.',
                'is_active' => true,
            ],
            [
                'key' => 'support.phone',
                'value' => '+63 (02) 8888-ALONA',
                'description' => 'Customer helpline phone number displayed on the buyer Help Center.',
                'is_active' => true,
            ],
            [
                'key' => 'support.hours',
                'value' => 'Mon – Sat: 8:00 AM – 8:00 PM PHT',
                'description' => 'Customer service operating hours displayed on the buyer Help Center.',
                'is_active' => true,
            ],
        ];

        foreach ($contactSettings as $setting) {
            PlatformSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        // Seed Default FAQs
        $faqs = [
            // Orders & Tracking
            [
                'category' => 'orders',
                'question' => 'How do I place an order on Alona?',
                'answer' => 'Browse products or search for what you need, choose your preferred variant (such as color or size) and quantity, then click "Add to Cart" or "Buy Now". Once in your cart, select the items you wish to purchase, proceed to Checkout, choose your delivery address and payment method, and confirm your order.',
                'sort_order' => 1,
            ],
            [
                'category' => 'orders',
                'question' => 'How do I track the shipping status of my order?',
                'answer' => 'Go to "My Orders" and click on your order to view its real-time tracking timeline. You will see fulfillment milestones (To Pack, Shipped, Out for Delivery, Delivered) as well as the assigned courier name and tracking number. You can click "Copy #" to track the parcel directly with the courier.',
                'sort_order' => 2,
            ],
            [
                'category' => 'orders',
                'question' => 'Can I cancel an order after placing it?',
                'answer' => 'Yes, you can cancel an order as long as it has not reached the shipped or out-for-delivery stage. Open the order in "My Orders", click "Cancel Order", select a cancellation reason, and submit. Any reserved inventory will be instantly restored.',
                'sort_order' => 3,
            ],
            [
                'category' => 'orders',
                'question' => 'How do I reorder items I purchased before?',
                'answer' => 'You can easily reorder past purchases by navigating to "My Orders" or "Order Details" and clicking the "Buy Again" button next to any eligible item or for the whole order.',
                'sort_order' => 4,
            ],

            // Payments & Checkout
            [
                'category' => 'payments',
                'question' => 'What payment methods can I use at checkout?',
                'answer' => 'Alona supports multiple flexible payment options: Cash on Delivery (COD), e-wallets (GCash and Maya), and Credit/Debit Cards (Visa, Mastercard, JCB). You can also save payment methods securely in your account for rapid one-click checkout.',
                'sort_order' => 5,
            ],
            [
                'category' => 'payments',
                'question' => 'Is my card and payment information secure?',
                'answer' => 'Yes, absolutely. Alona complies with modern payment security standards. Only the last 4 digits and expiration date are stored for saved payment methods. Sensitive card numbers and CVVs are never saved on our servers.',
                'sort_order' => 6,
            ],
            [
                'category' => 'payments',
                'question' => 'How are shipping fees calculated at checkout?',
                'answer' => 'Shipping fees are calculated based on the delivery province and municipality you select, combined with the courier shipping option chosen (e.g. Standard Delivery vs. Express Shipping). Any applicable shipping discount vouchers will be automatically applied to the total.',
                'sort_order' => 7,
            ],

            // Shipping & Delivery
            [
                'category' => 'shipping',
                'question' => 'How long will it take for my order to arrive?',
                'answer' => 'Metro Manila and nearby provinces typically take 1–3 business days. Provincial areas usually arrive within 3–7 business days. You can monitor courier tracking updates directly on your Order Details page.',
                'sort_order' => 8,
            ],
            [
                'category' => 'shipping',
                'question' => 'What should I do if my package is delayed?',
                'answer' => 'If your order tracking has not updated for several days past the expected delivery date, you can click "Contact Seller" on the order page or open a complaint ticket through the Help Center for platform assistance.',
                'sort_order' => 9,
            ],

            // Returns & Refunds
            [
                'category' => 'returns',
                'question' => 'How do I request a return or refund?',
                'answer' => 'Go to "My Orders", open the delivered order, and click "Return / Refund" next to the specific item. Select whether you are requesting a full return & refund or refund only, choose the appropriate reason (e.g. damaged, wrong item, counterfeit), provide a description, and upload photo/video proof.',
                'sort_order' => 10,
            ],
            [
                'category' => 'returns',
                'question' => 'How long does a seller have to respond to a return request?',
                'answer' => 'Sellers are given 48 to 72 hours to review and respond to return requests. If the seller approves or fails to respond within the timeframe, the platform administrative team will step in to resolve the refund in your favor.',
                'sort_order' => 11,
            ],
            [
                'category' => 'returns',
                'question' => 'When will I receive my refund?',
                'answer' => 'Once approved, refunds are credited back to your original payment method or wallet within 2 to 5 business days depending on your financial institution.',
                'sort_order' => 12,
            ],

            // Vouchers & Deals
            [
                'category' => 'vouchers',
                'question' => 'How do I claim and use discount vouchers?',
                'answer' => 'Visit the "Vouchers & Discounts" page from the main navigation or dashboard. Click "Claim Voucher" on any active promotion. Claimed vouchers will automatically appear in your voucher selection menu during checkout if your cart meets the minimum spend requirements.',
                'sort_order' => 13,
            ],
            [
                'category' => 'vouchers',
                'question' => 'Will I be notified when my vouchers are about to expire?',
                'answer' => 'Yes! Alona sends automated notification alerts to your inbox when a claimed voucher has 3 days or fewer remaining before expiration.',
                'sort_order' => 14,
            ],

            // Safety & Protection
            [
                'category' => 'safety',
                'question' => 'How do I report a suspicious product or misconduct by a seller?',
                'answer' => 'On any product detail page or seller store page, click the "Report Product" or "Report Seller" button. Select the reason for your report (e.g. Counterfeit / Fake item, Prohibited content, Fraud, Misleading specifications) and attach evidence. Our compliance and safety team investigates all reports thoroughly.',
                'sort_order' => 15,
            ],
            [
                'category' => 'safety',
                'question' => 'How can I track the status of my submitted complaints?',
                'answer' => 'Navigate to "My Complaints" under your buyer account menu or Help Center. You can view the review stage (Pending, Reviewing, Resolved, Rejected), submitted evidence, and the official admin resolution notes.',
                'sort_order' => 16,
            ],

            // Account & Profile
            [
                'category' => 'account',
                'question' => 'How do I change my saved delivery addresses?',
                'answer' => 'Go to your account profile dropdown and select "My Addresses". You can add new addresses, set a default address for instant checkout, edit existing entries, or delete addresses you no longer use.',
                'sort_order' => 17,
            ],
            [
                'category' => 'account',
                'question' => 'How do I update my password and security settings?',
                'answer' => 'Navigate to "My Account" → "Password & Security". Enter your current password and your new secure password to update your login credentials.',
                'sort_order' => 18,
            ],
        ];

        foreach ($faqs as $item) {
            Faq::firstOrCreate(
                [
                    'question' => $item['question'],
                ],
                [
                    'category' => $item['category'],
                    'answer' => $item['answer'],
                    'sort_order' => $item['sort_order'],
                    'is_published' => true,
                ]
            );
        }
    }
}

