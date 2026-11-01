<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\Product;
use App\Models\ProductComplianceReview;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AiProductVerificationService
{
    protected string $provider;
    protected string $geminiKey;
    protected string $geminiModel;
    protected string $openAiKey;
    protected string $openAiModel;
    protected float $confidenceThreshold;
    protected int $timeout;
    protected bool $enabled;

    public function __construct()
    {
        $this->provider = (string) config('services.ai.provider', env('AI_PROVIDER', 'gemini'));
        $this->geminiKey = (string) config('services.gemini.api_key', env('GEMINI_API_KEY', ''));
        $this->geminiModel = (string) config('services.gemini.model', env('GEMINI_MODEL', 'gemini-2.5-flash'));
        $this->openAiKey = (string) config('services.openai.api_key', env('OPENAI_API_KEY', ''));
        $this->openAiModel = (string) config('services.openai.model', env('OPENAI_MODEL', 'gpt-4o-mini'));

        $this->confidenceThreshold = (float) config(
            'services.ai.confidence_threshold',
            config('services.openai.confidence_threshold', env('AI_PRODUCT_APPROVAL_THRESHOLD', 0.85))
        );

        $this->timeout = (int) config(
            'services.ai.timeout',
            config('services.openai.timeout', env('AI_TIMEOUT', 30))
        );

        $this->enabled = (bool) config(
            'services.ai.enabled',
            config('services.openai.enabled', env('AI_PRODUCT_APPROVAL_ENABLED', true))
        );
    }

    /**
     * Determine active AI vision provider (Gemini or OpenAI).
     */
    public function getActiveProvider(): ?string
    {
        if ($this->provider === 'gemini' && ! empty($this->geminiKey)) {
            return 'gemini';
        }

        if ($this->provider === 'openai' && ! empty($this->openAiKey)) {
            return 'openai';
        }

        // Auto-detect based on available API key
        if (! empty($this->geminiKey)) {
            return 'gemini';
        }

        if (! empty($this->openAiKey)) {
            return 'openai';
        }

        return null;
    }

    /**
     * Verify a product using AI vision, apply business/policy rules,
     * and either auto-approve or keep pending with admin notification.
     *
     * @return array Verification outcome details
     */
    public function verifyAndProcess(Product $product): array
    {
        if (! app()->environment('testing') && function_exists('set_time_limit')) {
            @set_time_limit(120);
        }

        // 1. Seller Eligibility Check
        $sellerEligibility = $this->checkSellerEligibility($product);
        if (! $sellerEligibility['eligible']) {
            return $this->handleVerificationFailure($product, [
                'is_match' => false,
                'confidence' => 0.0,
                'status' => 'seller_ineligible',
                'issue_type' => 'seller_status_invalid',
                'explanation' => $sellerEligibility['reason'],
                'recommended_action' => 'Review the seller account status before considering product approval.',
            ]);
        }

        // 2. Marketplace Policy / Prohibited Item Filter
        $policyCheck = $this->checkMarketplacePolicies($product);
        if (! $policyCheck['passed']) {
            return $this->handleVerificationFailure($product, [
                'is_match' => false,
                'confidence' => 0.0,
                'status' => 'policy_violation',
                'issue_type' => 'policy_violation',
                'explanation' => $policyCheck['reason'],
                'recommended_action' => 'Inspect the product for prohibited or illegal items violating Alona marketplace policies.',
            ]);
        }

        // 3. Image Availability & Readability Check
        $imagePayload = $this->resolveProductImageData($product);
        if (! $imagePayload['valid']) {
            return $this->handleVerificationFailure($product, [
                'is_match' => false,
                'confidence' => 0.0,
                'status' => 'unclear_image',
                'issue_type' => 'missing_image',
                'explanation' => $imagePayload['reason'],
                'recommended_action' => 'Please request a clearer, valid image or review the product manually.',
            ]);
        }

        // 4. Feature Enablement & API Key Check
        $activeProvider = $this->getActiveProvider();
        if (! $this->enabled || ! $activeProvider) {
            return $this->handleVerificationFailure($product, [
                'is_match' => false,
                'confidence' => 0.0,
                'status' => 'service_error',
                'issue_type' => 'ai_service_unavailable',
                'explanation' => 'Automatic approval could not be completed because the AI verification service was unavailable. The product remains pending. Please retry verification or review it manually.',
                'recommended_action' => 'Please configure GEMINI_API_KEY or OPENAI_API_KEY in .env, or review the product manually.',
            ]);
        }

        // 5. Call AI Vision API (Google Gemini or OpenAI)
        if ($activeProvider === 'gemini') {
            $aiAnalysis = $this->analyzeWithGeminiVision($product, $imagePayload);
        } else {
            $aiAnalysis = $this->analyzeWithOpenAiVision($product, $imagePayload['data_url']);
        }

        if (! $aiAnalysis['success']) {
            return $this->handleVerificationFailure($product, [
                'is_match' => false,
                'confidence' => 0.0,
                'status' => 'service_error',
                'issue_type' => 'ai_service_unavailable',
                'explanation' => $aiAnalysis['error_message'],
                'recommended_action' => 'Automatic approval could not be completed because the AI verification service encountered a technical error. Please retry verification or review it manually.',
            ]);
        }

        $result = $aiAnalysis['data'];
        $isMatch = (bool) ($result['is_match'] ?? false);
        $confidence = (float) ($result['confidence'] ?? 0.0);
        $issueType = (string) ($result['issue_type'] ?? 'none');
        $explanation = (string) ($result['explanation'] ?? '');
        $recommendedAction = (string) ($result['recommended_action'] ?? 'Review the product details and image.');

        // 6. Confidence Threshold & Matching Evaluation
        if (! $isMatch) {
            $failureStatus = match ($issueType) {
                'unclear_image' => 'unclear_image',
                'image_mismatch', 'description_inconsistency' => 'mismatch',
                'policy_violation' => 'policy_violation',
                default => 'failed',
            };

            return $this->handleVerificationFailure($product, [
                'is_match' => false,
                'confidence' => $confidence,
                'status' => $failureStatus,
                'issue_type' => $issueType,
                'explanation' => $explanation,
                'recommended_action' => $recommendedAction,
                'raw_response' => $result,
            ]);
        }

        if ($confidence < $this->confidenceThreshold) {
            $thresholdPct = round($this->confidenceThreshold * 100);
            $confidencePct = round($confidence * 100);

            return $this->handleVerificationFailure($product, [
                'is_match' => false,
                'confidence' => $confidence,
                'status' => 'uncertain',
                'issue_type' => 'low_confidence',
                'explanation' => "Automatic approval failed because AI confidence score ({$confidencePct}%) is below the required verification threshold ({$thresholdPct}%). Please review the product manually.",
                'recommended_action' => 'Inspect the product image and description manually to ensure accuracy.',
                'raw_response' => $result,
            ]);
        }

        // 7. ALL CHECKS PASSED -> AUTOMATIC APPROVAL
        return $this->handleAutomaticApproval($product, $confidence, $result);
    }

    /**
     * Check seller eligibility for automatic product approval.
     */
    protected function checkSellerEligibility(Product $product): array
    {
        $seller = $product->seller;

        if (! $seller) {
            return [
                'eligible' => false,
                'reason' => 'Automatic approval failed because the associated seller account could not be found.',
            ];
        }

        if ($seller->status === 'suspended') {
            return [
                'eligible' => false,
                'reason' => 'Automatic approval failed because the seller store account is currently suspended.',
            ];
        }

        if ($seller->status !== 'approved' && $seller->status !== 'active') {
            return [
                'eligible' => false,
                'reason' => "Automatic approval failed because the seller account is currently '{$seller->status}' and not approved.",
            ];
        }

        return ['eligible' => true];
    }

    /**
     * Check marketplace policy and prohibited items against product metadata.
     */
    protected function checkMarketplacePolicies(Product $product): array
    {
        $prohibitedTerms = [
            'firearm', 'handgun', 'shotgun', 'rifle', 'ammunition', 'weapon',
            'explosive', 'grenade', 'narcotic', 'illicit drug', 'cocaine', 'heroin',
            'methamphetamine', 'marijuana', 'counterfeit currency', 'stolen property',
            'tobacco cigarette', 'vape liquid nicotine', 'adult pornographic',
        ];

        $contentToScan = strtolower(implode(' ', array_filter([
            $product->name,
            $product->description,
            $product->brand,
        ])));

        foreach ($prohibitedTerms as $term) {
            if (str_contains($contentToScan, $term)) {
                return [
                    'passed' => false,
                    'reason' => "Automatic approval failed because the product name or description contains prohibited terminology ('{$term}') violating marketplace safety policies.",
                ];
            }
        }

        return ['passed' => true];
    }

    /**
     * Validate and retrieve product image data as base64 and data URL.
     */
    protected function resolveProductImageData(Product $product): array
    {
        $imagePath = $product->image_path;

        if (! $imagePath) {
            $firstImg = $product->images()->first();
            $imagePath = $firstImg?->image_path;
        }

        if (! $imagePath) {
            return [
                'valid' => false,
                'reason' => 'Automatic approval failed because the uploaded product image is missing. Please upload a clear product image.',
            ];
        }

        // If local storage file exists
        if (Storage::disk('public')->exists($imagePath)) {
            $fullPath = Storage::disk('public')->path($imagePath);
            $mimeType = mime_content_type($fullPath) ?: 'image/jpeg';
            $bytes = file_get_contents($fullPath);

            if (empty($bytes)) {
                return [
                    'valid' => false,
                    'reason' => 'Automatic approval failed because the uploaded product image file is empty or unreadable.',
                ];
            }

            $base64 = base64_encode($bytes);

            return [
                'valid' => true,
                'data_url' => "data:{$mimeType};base64,{$base64}",
                'base64' => $base64,
                'mime_type' => $mimeType,
            ];
        }

        // If it's a valid remote URL (e.g. Unsplash or CDN)
        if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
            return [
                'valid' => true,
                'data_url' => $imagePath,
                'base64' => null,
                'mime_type' => 'image/jpeg',
            ];
        }

        return [
            'valid' => false,
            'reason' => 'Automatic approval failed because the uploaded product image could not be located in storage.',
        ];
    }

    /**
     * Build prompt context for product verification.
     */
    protected function buildPromptContext(Product $product): array
    {
        $variantsSummary = $product->variants()
            ->get(['color', 'size', 'sku'])
            ->map(fn ($v) => array_filter(['color' => $v->color, 'size' => $v->size, 'sku' => $v->sku]))
            ->filter()
            ->values()
            ->toArray();

        return [
            'product_name' => $product->name,
            'category' => $product->category?->name ?? 'Uncategorized',
            'brand' => $product->brand ?: 'Not specified',
            'price' => $product->price,
            'description' => $product->description ?: 'No description provided.',
            'variants' => $variantsSummary,
        ];
    }

    /**
     * Get system prompt for catalog compliance verification.
     */
    protected function getSystemPrompt(): string
    {
        return <<<PROMPT
You are an expert e-commerce catalog auditor and compliance verification officer for Alona E-Commerce platform.
Analyze the provided product image against the declared product metadata.

YOUR TASK:
1. Verify if the primary item depicted in the image matches the declared product name.
   - If the image depicts a completely different product (e.g. image shows shoes, but name says wireless headphones), this is a CRITICAL MISMATCH (issue_type: "image_mismatch").
2. Verify image and description consistency.
   - If description describes a specific color, attribute, or item that directly contradicts the image (e.g. description says "bright red dress" but image shows a blue dress), flag as "description_inconsistency".
3. Check image clarity:
   - If image is excessively blurry, corrupted, unrecognizable, or cannot be verified, flag as "unclear_image".
4. Check for prohibited/illegal/dangerous goods (weapons, drugs, adult explicit, etc.). If detected, flag as "policy_violation".

STRICT OUTPUT FORMAT:
You MUST respond with a valid JSON object only, adhering to this structure:
{
  "is_match": true or false,
  "confidence": float between 0.00 and 1.00,
  "issue_type": "none" | "image_mismatch" | "description_inconsistency" | "unclear_image" | "policy_violation" | "missing_information" | "other",
  "explanation": "Clear explanation. If false, start with 'Automatic approval failed because...'. If true, state 'Product verified: Image and product details match.'",
  "detected_item": "A concise 2-5 word description of what is actually depicted in the image",
  "recommended_action": "Action recommendation for marketplace administrator"
}
PROMPT;
    }

    /**
     * Execute Google Gemini Vision API call.
     */
    protected function analyzeWithGeminiVision(Product $product, array $imagePayload): array
    {
        $promptContext = $this->buildPromptContext($product);
        $systemPrompt = $this->getSystemPrompt();
        $userText = "Product metadata for audit:\n" . json_encode($promptContext, JSON_PRETTY_PRINT);

        // Resolve base64 and mime type
        $mimeType = $imagePayload['mime_type'] ?? 'image/jpeg';
        $base64Data = $imagePayload['base64'] ?? '';

        if (empty($base64Data) && ! empty($imagePayload['data_url'])) {
            if (preg_match('/^data:(image\/[a-zA-Z0-9\+\-]+);base64,(.+)$/', $imagePayload['data_url'], $matches)) {
                $mimeType = $matches[1];
                $base64Data = $matches[2];
            }
        }

        $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$this->geminiModel}:generateContent?key={$this->geminiKey}";

        try {
            $client = Http::timeout($this->timeout);

            // Optional SSL CA bundle if configured
            $caBundle = config('services.google.ca_bundle');
            if (! empty($caBundle) && file_exists($caBundle)) {
                $client = $client->withOptions(['verify' => $caBundle]);
            }

            $response = $client->post($endpoint, [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $systemPrompt],
                    ],
                ],
                'contents' => [
                    [
                        'role' => 'user',
                        'parts' => [
                            ['text' => $userText],
                            [
                                'inlineData' => [
                                    'mimeType' => $mimeType,
                                    'data' => $base64Data,
                                ],
                            ],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'responseMimeType' => 'application/json',
                    'temperature' => 0.1,
                    'maxOutputTokens' => 800,
                    'thinkingConfig' => [
                        'thinkingBudget' => 0,
                    ],
                ],
            ]);

            if ($response->failed()) {
                Log::error('Gemini API error during product verification', [
                    'product_id' => $product->id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return [
                    'success' => false,
                    'error_message' => 'Automatic approval could not be completed because the AI verification service was unavailable. The product remains pending. Please retry verification or review it manually.',
                ];
            }

            $body = $response->json();
            $content = $body['candidates'][0]['content']['parts'][0]['text'] ?? null;
            $parsed = $this->parseJsonSafely((string) $content);

            if (! is_array($parsed) || ! isset($parsed['is_match'])) {
                Log::warning('Gemini response was not valid JSON', [
                    'product_id' => $product->id,
                    'content' => $content,
                ]);

                return [
                    'success' => false,
                    'error_message' => 'Automatic approval could not be completed because the AI verification service returned an invalid response. The product remains pending.',
                ];
            }

            return [
                'success' => true,
                'data' => $parsed,
            ];
        } catch (\Throwable $e) {
            Log::error('Gemini Vision exception during product verification', [
                'product_id' => $product->id,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => 'Automatic approval could not be completed because the AI verification service was unavailable. The product remains pending. Please retry verification or review it manually.',
            ];
        }
    }

    /**
     * Execute OpenAI Vision API call with structured JSON prompt.
     */
    protected function analyzeWithOpenAiVision(Product $product, string $imageDataUrl): array
    {
        $promptContext = $this->buildPromptContext($product);
        $systemPrompt = $this->getSystemPrompt();

        try {
            $response = Http::withToken($this->openAiKey)
                ->timeout($this->timeout)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $this->openAiModel,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $systemPrompt,
                        ],
                        [
                            'role' => 'user',
                            'content' => [
                                [
                                    'type' => 'text',
                                    'text' => "Product metadata for audit:\n" . json_encode($promptContext, JSON_PRETTY_PRINT),
                                ],
                                [
                                    'type' => 'image_url',
                                    'image_url' => [
                                        'url' => $imageDataUrl,
                                        'detail' => 'auto',
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.1,
                    'max_tokens' => 500,
                ]);

            if ($response->failed()) {
                Log::error('OpenAI Vision API error during product verification', [
                    'product_id' => $product->id,
                    'status' => $response->status(),
                    'body' => $response->json(),
                ]);

                return [
                    'success' => false,
                    'error_message' => 'Automatic approval could not be completed because the AI verification service was unavailable. The product remains pending. Please retry verification or review it manually.',
                ];
            }

            $body = $response->json();
            $content = $body['choices'][0]['message']['content'] ?? null;
            $parsed = $this->parseJsonSafely((string) $content);

            if (! is_array($parsed) || ! isset($parsed['is_match'])) {
                Log::warning('OpenAI Vision response was not valid JSON', [
                    'product_id' => $product->id,
                    'content' => $content,
                ]);

                return [
                    'success' => false,
                    'error_message' => 'Automatic approval could not be completed because the AI verification service returned an invalid response. The product remains pending.',
                ];
            }

            return [
                'success' => true,
                'data' => $parsed,
            ];
        } catch (\Throwable $e) {
            Log::error('OpenAI Vision exception during product verification', [
                'product_id' => $product->id,
                'message' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error_message' => 'Automatic approval could not be completed because the AI verification service was unavailable. The product remains pending. Please retry verification or review it manually.',
            ];
        }
    }

    /**
     * Safely parse JSON strings returned by LLM models, stripping any markdown backticks.
     */
    protected function parseJsonSafely(string $content): ?array
    {
        $trimmed = trim($content);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $trimmed, $matches)) {
            $trimmed = trim($matches[1]);
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Handle approved product state transition.
     */
    protected function handleAutomaticApproval(Product $product, float $confidence, array $rawResponse): array
    {
        $confidencePct = round($confidence * 100, 1);
        $explanation = "Automatically approved: Product image, name, and description verified with {$confidencePct}% confidence.";

        $product->update([
            'status' => 'approved',
            'approval_method' => 'automatic',
            'ai_verification_status' => 'passed',
            'ai_verification_explanation' => $explanation,
            'ai_confidence_score' => $confidence,
            'ai_verified_at' => now(),
            'ai_raw_response' => $rawResponse,
            'rejection_reason' => null,
        ]);

        // Compliance Review audit entry
        ProductComplianceReview::create([
            'product_id' => $product->id,
            'admin_id' => null, // Automated system approval
            'action' => 'approved',
            'note' => "Automatically approved by AI verification (Confidence: {$confidencePct}%)",
            'reviewed_at' => now(),
        ]);

        // Platform Admin Audit Log
        AdminAuditLog::record(
            auth()->id() ?? 1,
            'product_auto_approved',
            "Product '{$product->name}' (ID: #{$product->id}) was automatically approved via AI verification (Confidence: {$confidencePct}%).",
            $product,
            ['confidence' => $confidence, 'method' => 'automatic']
        );

        // Resolve any previous unread notifications for this product
        app(AdminNotificationService::class)->resolveProductNotifications($product);

        return [
            'approved' => true,
            'status' => 'passed',
            'confidence' => $confidence,
            'approval_method' => 'automatic',
            'explanation' => $explanation,
        ];
    }

    /**
     * Handle failed/uncertain product state transition and admin notification.
     */
    protected function handleVerificationFailure(Product $product, array $result): array
    {
        $status = $result['status'] ?? 'pending';
        $explanation = $result['explanation'] ?? 'Automatic approval could not be completed.';
        $confidence = isset($result['confidence']) ? (float) $result['confidence'] : null;

        $product->update([
            'status' => 'pending',
            'approval_method' => 'manual',
            'ai_verification_status' => $status,
            'ai_verification_explanation' => $explanation,
            'ai_confidence_score' => $confidence,
            'ai_verified_at' => now(),
            'ai_raw_response' => $result['raw_response'] ?? null,
        ]);

        // Trigger Admin Notification
        app(AdminNotificationService::class)->notifyProductReviewRequired($product, $result);

        return [
            'approved' => false,
            'status' => $status,
            'confidence' => $confidence,
            'approval_method' => 'manual',
            'explanation' => $explanation,
        ];
    }
}
