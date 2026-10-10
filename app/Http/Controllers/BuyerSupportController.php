<?php

namespace App\Http\Controllers;

use App\Models\BuyerNotification;
use App\Models\Complaint;
use App\Models\Faq;
use App\Models\Message;
use App\Models\Order;
use App\Models\PlatformSetting;
use App\Models\Policy;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class BuyerSupportController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | BUYER-37: Help Center
    |--------------------------------------------------------------------------
    */
    public function help(Request $request): Response
    {
        $user = $request->user();

        $settings = Schema::hasTable('platform_settings')
            ? PlatformSetting::query()
                ->whereIn('key', ['support.email', 'support.phone', 'support.hours'])
                ->where('is_active', true)
                ->pluck('value', 'key')
            : collect();

        return Inertia::render('Buyer/HelpCenter', [
            'isGuest' => ! $user,
            'recentOrders' => $user
                ? $user->orders()->latest()->limit(3)->get(['id', 'order_number', 'status', 'total', 'created_at'])
                : [],
            'contactSettings' => [
                'email' => $settings->get('support.email') ?: 'support@alona.ph',
                'phone' => $settings->get('support.phone') ?: '+63 (02) 8888-ALONA',
                'hours' => $settings->get('support.hours') ?: 'Mon – Sat: 8:00 AM – 8:00 PM PHT',
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BUYER-36: FAQs
    |--------------------------------------------------------------------------
    */
    public function faq(Request $request): Response
    {
        $user = $request->user();

        $faqs = Schema::hasTable('faqs')
            ? Faq::query()
                ->where('is_published', true)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : collect();

        return Inertia::render('Buyer/Faq', [
            'isGuest' => ! $user,
            'faqs' => $faqs,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BUYER-38: Buyer Policies
    |--------------------------------------------------------------------------
    */
    public function policies(Request $request): Response
    {
        $user = $request->user();

        $databasePolicies = Schema::hasTable('policies')
            ? Policy::query()->where('is_published', true)->get()
            : collect();

        return Inertia::render('Buyer/Policies', [
            'isGuest' => ! $user,
            'databasePolicies' => $databasePolicies,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BUYER-33: Complaint Tracking
    |--------------------------------------------------------------------------
    */
    public function complaints(Request $request): Response
    {
        $user = $request->user();
        $statusTab = strtolower((string) $request->input('status', 'all'));
        $search = trim((string) $request->input('search', ''));

        $query = Complaint::query()
            ->where('buyer_id', $user->id)
            ->with([
                'seller:id,name,store_name',
                'courier:id,name',
                'order:id,order_number',
                'product:id,name,image_path',
                'reviewer:id,name',
            ]);

        $counts = [
            'all' => Complaint::where('buyer_id', $user->id)->count(),
            'pending' => Complaint::where('buyer_id', $user->id)->where('status', 'pending')->count(),
            'reviewing' => Complaint::where('buyer_id', $user->id)->where('status', 'reviewing')->count(),
            'resolved' => Complaint::where('buyer_id', $user->id)->where('status', 'resolved')->count(),
            'rejected' => Complaint::where('buyer_id', $user->id)->where('status', 'rejected')->count(),
        ];

        if ($statusTab !== 'all' && in_array($statusTab, ['pending', 'reviewing', 'resolved', 'rejected'])) {
            $query->where('status', $statusTab);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $complaints = $query->latest()->paginate(10)->withQueryString();

        // Recent orders for the complaint submission modal
        $orders = $user->orders()->latest()->limit(20)->get(['id', 'order_number', 'created_at']);

        return Inertia::render('Buyer/Complaints', [
            'complaints' => $complaints,
            'current_status' => $statusTab,
            'counts' => $counts,
            'orders' => $orders,
            'filters' => [
                'status' => $statusTab,
                'search' => $search,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | BUYER-32 & BUYER-33: Submit Complaint or Support Ticket
    |--------------------------------------------------------------------------
    */
    public function storeComplaint(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'type' => ['nullable', 'string', 'max:50'],
            'description' => ['required', 'string', 'max:5000'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'seller_id' => ['nullable', 'integer', 'exists:users,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'evidence_images' => ['nullable', 'array', 'max:5'],
            'evidence_images.*' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        $evidencePaths = [];
        if ($request->hasFile('evidence_images')) {
            foreach ($request->file('evidence_images') as $file) {
                $evidencePaths[] = $file->store('complaints', 'public');
            }
        }

        $complaint = Complaint::create([
            'buyer_id' => $request->user()->id,
            'seller_id' => $data['seller_id'] ?? null,
            'order_id' => $data['order_id'] ?? null,
            'product_id' => $data['product_id'] ?? null,
            'subject' => $data['subject'],
            'type' => $data['type'] ?? 'complaint',
            'description' => $data['description'],
            'contact_email' => $data['contact_email'] ?? $request->user()->email,
            'evidence' => $evidencePaths,
            'status' => 'pending',
        ]);

        if (Schema::hasTable('buyer_notifications')) {
            try {
                BuyerNotification::create([
                    'user_id' => $request->user()->id,
                    'title' => 'Complaint / Support Ticket Received',
                    'type' => 'complaint',
                    'message' => "Your ticket '{$complaint->subject}' has been submitted and assigned ID #{$complaint->id}.",
                    'action_url' => route('buyer.complaints'),
                ]);
            } catch (\Throwable $e) {
                // Ignore notification error
            }
        }

        return back()->with('status', 'Your complaint/support ticket has been submitted to the platform administration.');
    }

    /*
    |--------------------------------------------------------------------------
    | BUYER-34: Report a Seller or Product
    |--------------------------------------------------------------------------
    */
    public function storeReport(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'report_type' => ['required', 'in:product,seller'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'seller_id' => ['nullable', 'integer', 'exists:users,id'],
            'reason' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:3000'],
            'evidence_images' => ['nullable', 'array', 'max:4'],
            'evidence_images.*' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:5120'],
        ]);

        $evidencePaths = [];
        if ($request->hasFile('evidence_images')) {
            foreach ($request->file('evidence_images') as $file) {
                $evidencePaths[] = $file->store('reports', 'public');
            }
        }

        $targetName = 'Target';
        if ($data['report_type'] === 'product' && ! empty($data['product_id'])) {
            $product = Product::find($data['product_id']);
            $targetName = $product ? $product->name : "Product #{$data['product_id']}";
            $sellerId = $data['seller_id'] ?? ($product ? $product->seller_id : null);
        } else {
            $seller = ! empty($data['seller_id']) ? User::find($data['seller_id']) : null;
            $targetName = $seller ? ($seller->store_name ?: $seller->name) : 'Seller';
            $sellerId = $data['seller_id'] ?? null;
        }

        $subject = "Report: {$data['reason']} - {$targetName}";

        Complaint::create([
            'buyer_id' => $request->user()->id,
            'seller_id' => $sellerId,
            'product_id' => $data['product_id'] ?? null,
            'subject' => $subject,
            'type' => $data['report_type'] === 'product' ? 'product_report' : 'seller_report',
            'description' => "Reason: {$data['reason']}\n\nDetails: {$data['description']}",
            'evidence' => $evidencePaths,
            'status' => 'pending',
        ]);

        if (Schema::hasTable('buyer_notifications')) {
            try {
                BuyerNotification::create([
                    'user_id' => $request->user()->id,
                    'title' => 'Report Submitted',
                    'type' => 'complaint',
                    'message' => "Thank you for helping keep Alona safe. Your report regarding {$targetName} has been submitted for review.",
                    'action_url' => route('buyer.complaints'),
                ]);
            } catch (\Throwable $e) {
                // Ignore notification error
            }
        }

        return back()->with('status', 'Report submitted successfully. Our compliance team will review it shortly.');
    }

    /*
    |--------------------------------------------------------------------------
    | BUYER-32: Direct Customer Support Contact Message
    |--------------------------------------------------------------------------
    */
    public function contactSupport(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:4000'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $request->user();

        // Create a platform Message
        Message::create([
            'user_id' => $user->id,
            'subject' => $data['category'] ? "[{$data['category']}] {$data['subject']}" : $data['subject'],
            'message' => $data['message'],
            'is_read' => false,
        ]);

        // Also create a Complaint record for tracking
        Complaint::create([
            'buyer_id' => $user->id,
            'subject' => $data['category'] ? "[Support Inquiry - {$data['category']}] {$data['subject']}" : "[Support Inquiry] {$data['subject']}",
            'type' => 'support_ticket',
            'description' => $data['message'],
            'status' => 'pending',
        ]);

        if (Schema::hasTable('buyer_notifications')) {
            try {
                BuyerNotification::create([
                    'user_id' => $user->id,
                    'title' => 'Support Inquiry Submitted',
                    'type' => 'message',
                    'message' => 'Our customer support team has received your message and will respond within 24 hours.',
                    'action_url' => route('buyer.complaints'),
                ]);
            } catch (\Throwable $e) {
                // Ignore notification error
            }
        }

        return back()->with('status', 'Your message has been sent to Alona Customer Support. We will get back to you shortly.');
    }
}

