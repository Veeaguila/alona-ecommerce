<?php

use App\Models\Policy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('policies')) {
            return;
        }

        $policies = [
            [
                'title' => 'Privacy Policy',
                'version' => '1.0',
                'is_published' => true,
                'content' => "1. Overview & Commitment\n"
                    . "Alona Marketplace (\"Alona\", \"we\", \"our\") is committed to protecting the personal data and privacy rights of our buyers, sellers, and delivery partners in accordance with the Data Privacy Act of 2012 (Republic Act No. 10173).\n\n"
                    . "2. Information We Collect\n"
                    . "• Personal Identifiers: Name, verified email address, mobile phone number, and account credentials.\n"
                    . "• Fulfillment Information: Shipping and delivery addresses, recipient contact details, and recipient identification for delivery verification.\n"
                    . "• Transactional Data: Purchase records, payment references, order items, and cancellation or refund requests.\n"
                    . "• Device & Usage Data: IP address, browser type, login session timestamps, and device identifiers used to detect unauthorized access.\n\n"
                    . "3. How We Use Your Information\n"
                    . "We process personal data strictly to fulfill your marketplace purchases, dispatch couriers to your address, provide real-time package tracking, resolve customer support inquiries and ticket complaints, and prevent fraudulent transactions.\n\n"
                    . "4. Data Sharing & Third Parties\n"
                    . "We only share your delivery name, address, and contact number with the designated seller and authorized courier partner for the sole purpose of delivering your orders. We do not sell or monetize personal data with third-party advertising networks.\n\n"
                    . "5. Security & Retention\n"
                    . "Your data is stored using industry-standard SSL encryption and secured cloud databases. Financial transactions are tokenized through licensed payment gateways. You may manage, download, or request deletion of your account data by contacting support@alona.ph.",
            ],
            [
                'title' => 'Terms & Conditions',
                'version' => '1.0',
                'is_published' => true,
                'content' => "1. Agreement to Terms\n"
                    . "By creating an account, browsing products, or completing an order on Alona Marketplace, you agree to comply with and be bound by these Terms and Conditions.\n\n"
                    . "2. Marketplace Intermediary Role\n"
                    . "Alona functions as an online e-commerce platform that connects independent merchants (\"Sellers\") with shoppers (\"Buyers\"). While Alona enforces seller quality guidelines and guarantees buyer protection, contracts of sale are formed directly between the buyer and the seller.\n\n"
                    . "3. Buyer Obligations & Account Security\n"
                    . "Buyers must provide accurate, truthful personal and delivery details. You are responsible for safeguarding your login credentials, OTP codes, and password. Any actions executed through your authenticated session are deemed authorized by you.\n\n"
                    . "4. Pricing, Orders, and Acceptance\n"
                    . "All listed prices are displayed in Philippine Peso (PHP) inclusive of applicable taxes unless stated otherwise. An order is confirmed once payment authorization is received or Cash on Delivery (COD) eligibility is verified.\n\n"
                    . "5. Prohibited Conduct\n"
                    . "Users must not post abusive reviews, fraudulent dispute claims, counterfeit merchandise, or manipulate ratings and inventory. Violations may result in immediate suspension or termination of marketplace privileges.\n\n"
                    . "6. Governing Law & Dispute Resolution\n"
                    . "These terms are governed by the laws of the Republic of the Philippines. Parties agree to attempt amicable resolution via the Alona Resolution Center before seeking judicial remedies.",
            ],
            [
                'title' => 'Shipping & Delivery Policy',
                'version' => '1.0',
                'is_published' => true,
                'content' => "1. Delivery Network & Coverage\n"
                    . "Alona partners with accredited logistics service providers and dedicated sorting centers to offer reliable nationwide parcel delivery across Metro Manila, Luzon, Visayas, and Mindanao.\n\n"
                    . "2. Standard Shipping Timelines\n"
                    . "• Metro Manila & Nearby Provinces: 1 to 3 business days following seller dispatch.\n"
                    . "• Major Provincial Centers: 3 to 5 business days.\n"
                    . "• Remote or Island Locations: 5 to 7 business days.\n"
                    . "Delivery estimates may be subject to minor delays during adverse weather disturbances or peak holiday shopping festivals.\n\n"
                    . "3. Tracking & Delivery Proof\n"
                    . "Every dispatched parcel is assigned a unique tracking number accessible on your Order Details page. Couriers are strictly required to capture photographic Proof of Delivery (e.g., photo of package with recipient or premises) to confirm handoff.\n\n"
                    . "4. Delivery Attempts & Failed Handoff\n"
                    . "Couriers will attempt delivery up to two (2) times. If the recipient is unreachable, the parcel will be held at the local sorting hub for 48 hours before being returned to the seller (Return to Sender / RTS).\n\n"
                    . "5. Damaged or Tampered Packages\n"
                    . "If a package arrives visibly broken, unsealed, or tampered with, buyers are advised to take photos before opening and immediately lodge a claim through Complaints & Ticket Status.",
            ],
            [
                'title' => 'Returns & Refunds Policy',
                'version' => '1.0',
                'is_published' => true,
                'content' => "1. Alona Buyer Guarantee Window\n"
                    . "Buyers may submit a Return & Refund request within seven (7) calendar days from the date of confirmed package delivery.\n\n"
                    . "2. Eligible Return Reasons\n"
                    . "• Damaged, defective, or non-functional item.\n"
                    . "• Incorrect item, size, color, or variant delivered.\n"
                    . "• Missing components, accessories, or free gifts.\n"
                    . "• Counterfeit, replica, or materially misrepresented goods.\n\n"
                    . "3. Non-Returnable Items\n"
                    . "Items that cannot be returned include perishable items, opened personal care or hygiene goods, digital codes, and custom personalized merchandise, unless defective upon arrival.\n\n"
                    . "4. Process & Resolution Timeline\n"
                    . "• Step 1: Open Order Details, select \"Request Return\", and provide photos/videos of the item.\n"
                    . "• Step 2: The seller has 48 hours to review the request, accept a return, or issue an immediate refund.\n"
                    . "• Step 3: If accepted, return the parcel using the provided prepaid return waybill.\n"
                    . "• Step 4: Upon inspection confirmation, refunds are automatically credited back to your original payment channel (GCash, Maya, Card, or COD e-wallet refund) within 24–72 hours.\n\n"
                    . "5. Escalation & Platform Mediation\n"
                    . "If a seller unjustifiably rejects an eligible claim, buyers can escalate the case to Alona Platform Support for definitive mediation.",
            ],
            [
                'title' => 'Payment Methods',
                'version' => '1.0',
                'is_published' => true,
                'content' => "1. Supported Checkout Options\n"
                    . "Alona supports convenient and flexible payment channels to suit every shopper:\n"
                    . "• Cash on Delivery (COD): Pay directly in cash to the rider upon parcel delivery.\n"
                    . "• E-Wallets: Instant checkout via GCash and Maya with QR code or mobile wallet verification.\n"
                    . "• Credit & Debit Cards: Visa, Mastercard, and JCB cards secured by 3D-Secure authentication.\n"
                    . "• Platform Vouchers: Combine official platform vouchers and seller promotional discounts for additional savings.\n\n"
                    . "2. Payment Security & Encryption\n"
                    . "All payment transactions are encrypted using TLS 1.3 protocol. Alona does not store sensitive card CVV numbers or banking PINs. Card details are tokenized through PCI-DSS Level 1 certified payment gateways.\n\n"
                    . "3. Cash on Delivery (COD) Rules\n"
                    . "Please ensure that the exact payment amount or sufficient cash is prepared for the courier upon delivery. COD is available for orders where the total amount and destination address meet courier safety guidelines.\n\n"
                    . "4. Invoices & Billing Receipts\n"
                    . "Digital order summaries and payment receipts are generated automatically upon order placement and can be viewed or printed anytime under \"My Orders\" -> \"Order Details\".\n\n"
                    . "5. Billing Inquiries & Unauthorized Transactions\n"
                    . "If you detect an unrecognized transaction or charge discrepancy, contact our billing help desk immediately at support@alona.ph or through the Complaints & Ticket Status portal.",
            ],
        ];

        foreach ($policies as $data) {
            Policy::updateOrCreate(
                ['title' => $data['title']],
                [
                    'content' => $data['content'],
                    'version' => $data['version'],
                    'is_published' => $data['is_published'],
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep policy records intact during normal rollback
    }
};

