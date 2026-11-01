<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Rider;
use App\Models\User;
use App\Notifications\RiderActivationNotification;
use Carbon\Carbon;
use App\Models\RiderDecisionAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Schema;

class RiderManagementController extends Controller
{
    /**
     * List riders filtered optionally by approval_status.
     * GET /api/sorting-center/riders?status=pending
     * GET /api/sorting-center/riders?status=approved
     */
    public function index(Request $request): JsonResponse
    {
        $query = Rider::query()
            ->with(['user:id,name,email', 'user.partnerApplication:id,user_id,documents'])
            ->where('sorting_center_id', $request->user()->sortingCenter->id);

        if ($request->filled('status')) {
            $query->where('approval_status', $request->query('status'));
        }

        $riders = $query->orderBy('created_at', 'desc')->get();
        $riders->each(function (Rider $rider): void {
            $documents = $rider->user?->partnerApplication?->documents ?? [];
            $reviewDocuments = [];
            foreach (['id_doc' => 'Government ID', 'license_doc' => "Driver's License", 'or_cr_doc' => 'OR/CR'] as $key => $label) {
                $available = is_string($documents[$key] ?? null) && $documents[$key] !== '';
                $reviewDocuments[] = [
                    'key' => $key, 'label' => $label, 'available' => $available,
                    'url' => $available ? route('partner.documents', ['user' => $rider->user_id, 'document' => $key], false) : null,
                ];
            }
            $rider->setAttribute('review_documents', $reviewDocuments);
            // Do not serialize the eagerly loaded application or its private storage paths.
            $rider->user?->unsetRelation('partnerApplication');
        });
        app(\App\Services\RiderWorkload::class)->append($riders, (int) $request->user()->sortingCenter->id);

        return response()->json([
            'message' => 'Riders retrieved successfully.',
            'data' => $riders,
        ], 200);
    }

    /**
     * Store a new rider created by an approved sorting center.
     * POST /sorting-center/riders or POST /api/sorting-center/riders
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $sortingCenter = $request->user()->sortingCenter;
        if (! $sortingCenter) {
            abort(403, 'Forbidden. This action is restricted to sorting centers.');
        }

        $input = $request->all();

        // Normalize aliases between test cases and form names
        if (! isset($input['birthday']) && isset($input['date_of_birth'])) {
            $input['birthday'] = $input['date_of_birth'];
        }
        if (! isset($input['date_of_birth']) && isset($input['birthday'])) {
            $input['date_of_birth'] = $input['birthday'];
        }

        if (! isset($input['contact']) && isset($input['mobile_number'])) {
            $input['contact'] = $input['mobile_number'];
        }
        if (! isset($input['mobile_number']) && isset($input['contact'])) {
            $input['mobile_number'] = $input['contact'];
        }

        if (! isset($input['license_number']) && isset($input['driver_license_number'])) {
            $input['license_number'] = $input['driver_license_number'];
        }
        if (! isset($input['license_number']) && isset($input['driver_license_no'])) {
            $input['license_number'] = $input['driver_license_no'];
        }

        if (! isset($input['license_expiry']) && isset($input['license_expiry_date'])) {
            $input['license_expiry'] = $input['license_expiry_date'];
        }

        if (! $request->hasFile('driver_license') && $request->hasFile('license_document')) {
            $input['driver_license'] = $request->file('license_document');
        } elseif ($request->hasFile('driver_license')) {
            $input['driver_license'] = $request->file('driver_license');
        }

        if (! $request->hasFile('or_cr') && $request->hasFile('or_cr_document')) {
            $input['or_cr'] = $request->file('or_cr_document');
        } elseif ($request->hasFile('or_cr')) {
            $input['or_cr'] = $request->file('or_cr');
        }

        $rules = [
            'first_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\pL\s\-\'.]+$/u'],
            'last_name' => ['required', 'string', 'min:2', 'max:50', 'regex:/^[\pL\s\-\'.]+$/u'],
            'date_of_birth' => ['required', 'date'],
            'email' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/',
                'unique:users,email',
            ],
            'mobile_number' => ['required', 'string', 'regex:/^(09|\+639)\d{9}$/'],
            'license_number' => ['required', 'string', 'regex:/^[A-Z]\d{2}-\d{2}-\d{6}$/', 'unique:riders,license_number'],
            'license_expiry' => ['required', 'date', 'after_or_equal:today'],
            'vehicle_type' => ['required', 'string'],
            'plate_number' => ['required', 'string', 'unique:riders,plate_number'],
            'driver_license' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'or_cr' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];

        $messages = [
            'first_name.required' => 'The first name is required.',
            'first_name.regex' => 'The first name may only contain letters, spaces, hyphens, and apostrophes.',
            'first_name.min' => 'The first name must be at least 2 characters.',
            'first_name.max' => 'The first name may not be greater than 50 characters.',
            'last_name.required' => 'The last name is required.',
            'last_name.regex' => 'The last name may only contain letters, spaces, hyphens, and apostrophes.',
            'last_name.min' => 'The last name must be at least 2 characters.',
            'last_name.max' => 'The last name may not be greater than 50 characters.',
            'date_of_birth.required' => 'The date of birth is required.',
            'email.required' => 'The email address is required.',
            'email.regex' => 'Please enter a valid email address.',
            'email.unique' => 'The email is already in use.',
            'mobile_number.required' => 'The mobile number is required.',
            'mobile_number.regex' => 'The mobile number must be an 11-digit Philippine number starting with 09.',
            'license_number.required' => "The driver's license number is required.",
            'license_number.regex' => "The driver's license number must follow the format A00-00-000000.",
            'license_number.unique' => "The driver's license number has already been registered.",
            'license_expiry.required' => "The license expiry date is required.",
            'license_expiry.after_or_equal' => "The driver's license is expired.",
            'vehicle_type.required' => 'The vehicle type is required.',
            'plate_number.required' => 'The plate number is required.',
            'plate_number.unique' => 'The plate number has already been registered.',
            'driver_license.required' => "The driver's license document is required.",
            'driver_license.mimes' => 'The document must be a file of type: JPG, PNG, PDF.',
            'driver_license.max' => 'The document may not be greater than 5MB.',
            'or_cr.required' => 'The OR/CR document is required.',
            'or_cr.mimes' => 'The document must be a file of type: JPG, PNG, PDF.',
            'or_cr.max' => 'The document may not be greater than 5MB.',
        ];

        $validator = Validator::make($input, $rules, $messages);

        $validator->after(function ($validator) use ($input) {
            // Check 18-year age limit
            if (! empty($input['date_of_birth'])) {
                try {
                    $dob = Carbon::parse($input['date_of_birth'])->startOfDay();
                    $minAge = Carbon::today()->subYears(18);
                    if ($dob->gt($minAge)) {
                        $validator->errors()->add('date_of_birth', 'The rider must be at least 18 years old.');
                        $validator->errors()->add('birthday', 'The rider must be at least 18 years old.');
                    }
                } catch (\Throwable) {
                    $validator->errors()->add('date_of_birth', 'The date of birth is invalid.');
                }
            }

            // Check mobile uniqueness in users and riders
            if (! empty($input['mobile_number'])) {
                $mobile = $input['mobile_number'];
                $clean = preg_replace('/^\+63/', '0', $mobile);
                if (User::where('contact_no', $mobile)->orWhere('contact_no', $clean)->exists() ||
                    Rider::where('contact', $mobile)->orWhere('contact', $clean)->exists()) {
                    $validator->errors()->add('mobile_number', 'The mobile number is already in use.');
                    $validator->errors()->add('contact', 'The mobile number is already in use.');
                }
            }
        });

        if ($validator->fails()) {
            // Duplicate errors onto aliases so both test forms and client inputs receive errors
            $errors = $validator->errors();
            $mirror = [
                'date_of_birth' => 'birthday',
                'mobile_number' => 'contact',
                'license_number' => ['driver_license_number', 'driver_license_no'],
                'license_expiry' => 'license_expiry_date',
                'driver_license' => ['license_document', 'driver_license_doc'],
                'or_cr' => ['or_cr_document', 'or_cr_doc'],
            ];
            foreach ($mirror as $orig => $aliases) {
                if ($errors->has($orig)) {
                    $msg = $errors->first($orig);
                    foreach ((array) $aliases as $alias) {
                        if (! $errors->has($alias)) {
                            $errors->add($alias, $msg);
                        }
                    }
                }
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $errors,
                ], 422);
            }

            throw new ValidationException($validator);
        }

        $validated = $validator->validated();

        $licenseFile = $input['driver_license'];
        $orCrFile = $input['or_cr'];
        $licensePath = $licenseFile->store('rider-documents', 'local');
        $orCrPath = $orCrFile->store('rider-documents', 'local');

        $mobile = preg_replace('/^\+63/', '0', $validated['mobile_number']);
        $dobFormatted = Carbon::parse($validated['date_of_birth'])->format('Y-m-d');
        $expiryFormatted = Carbon::parse($validated['license_expiry'])->format('Y-m-d');
        $activationToken = Str::random(40);

        $rider = DB::transaction(function () use (
            $validated,
            $mobile,
            $dobFormatted,
            $expiryFormatted,
            $licensePath,
            $orCrPath,
            $activationToken,
            $sortingCenter
        ) {
            $user = User::create([
                'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'email' => strtolower(trim($validated['email'])),
                'contact_no' => $mobile,
                'birthday' => $dobFormatted,
                'usertype' => 'rider',
                'status' => 'pending_activation',
                'password' => Hash::make(Str::random(32)),
            ]);

            $rider = Rider::create([
                'user_id' => $user->id,
                'sorting_center_id' => $sortingCenter->id,
                'name' => $user->name,
                'contact' => $mobile,
                'birthday' => $dobFormatted,
                'vehicle_type' => $validated['vehicle_type'],
                'plate_number' => $validated['plate_number'],
                'license_number' => $validated['license_number'],
                'license_expiry' => $expiryFormatted,
                'license_doc_url' => $licensePath,
                'or_cr_doc_url' => $orCrPath,
                'activation_token' => $activationToken,
                'activation_token_expires_at' => now()->addHours(24),
                'approval_status' => 'pending_activation',
                'is_active' => false,
            ]);

            $user->notify(new RiderActivationNotification($activationToken, $sortingCenter->business_name ?? 'Logistics'));

            return $rider->load('user:id,name,email');
        });

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => 'Rider created successfully. An activation email has been sent.',
                'data' => $rider,
            ], 201);
        }

        return redirect()->route('sorting-center.delivery-management')
            ->with('success', 'Rider created successfully. An activation email has been sent.');
    }

    /**
     * Resend the 24-hour activation link for an unactivated rider.
     * POST /sorting-center/riders/{id}/resend-activation
     * POST /api/sorting-center/riders/{id}/resend-activation
     */
    public function resendActivation(Request $request, int $id): JsonResponse
    {
        $sortingCenter = $request->user()->sortingCenter;
        $rider = Rider::query()
            ->where('sorting_center_id', $sortingCenter->id)
            ->whereKey($id)
            ->first();

        if (! $rider) {
            return response()->json(['message' => 'Rider not found for this sorting center.'], 404);
        }

        if ($rider->approval_status === 'approved' && empty($rider->activation_token)) {
            return response()->json(['message' => 'Rider is already activated.'], 422);
        }

        $token = Str::random(40);
        $rider->update([
            'activation_token' => $token,
            'activation_token_expires_at' => now()->addHours(24),
        ]);

        $rider->user->notify(new RiderActivationNotification($token, $sortingCenter->business_name ?? 'Logistics'));

        return response()->json([
            'message' => 'Activation email resent successfully.',
            'data' => [
                'rider_id' => $rider->id,
                'activation_token_expires_at' => $rider->activation_token_expires_at,
            ],
        ], 200);
    }

    /**
     * Approve a pending rider application.
     * POST /api/sorting-center/riders/{id}/approve
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $sortingCenterId = $request->user()->sortingCenter->id;
        $rider = DB::transaction(function () use ($request, $id, $sortingCenterId): ?Rider {
            $rider = $this->lockedRider($sortingCenterId, $id);

            if (! $rider || $rider->approval_status !== 'pending') {
                return null;
            }

            $this->requireAuditStorage();
            $before = $this->decisionState($rider);
            $rider->update([
                'approval_status' => 'approved',
                'is_active' => true,
            ]);

            $rider->user->update(['status' => $rider->approval_status]);
            $this->recordDecision($request, $rider, $before, 'approve');
            return $rider->load('user:id,name,email');
        });

        if (! $rider) {
            return response()->json([
                'message' => 'Pending rider application not found for this sorting center.',
            ], 404);
        }

        app(\App\Services\RiderWorkload::class)->append(new \Illuminate\Database\Eloquent\Collection([$rider]), (int) $sortingCenterId);
        return response()->json([
            'message' => 'Rider approved successfully.',
            'data' => $rider,
        ], 200);
    }

    /**
     * Reject a pending rider application.
     * POST /api/sorting-center/riders/{id}/reject
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $sortingCenterId = $request->user()->sortingCenter->id;
        $rider = DB::transaction(function () use ($request, $id, $sortingCenterId): ?Rider {
            $rider = $this->lockedRider($sortingCenterId, $id);

            if (! $rider || $rider->approval_status !== 'pending') {
                return null;
            }

            $validated = $request->validate([
                'reason' => ['bail', 'required', 'string', 'min:10', 'max:300'],
            ]);

            $this->requireAuditStorage();
            $before = $this->decisionState($rider);
            $rider->update(['approval_status' => 'rejected']);
            $rider->user->update(['status' => 'rejected', 'rejection_reason' => $validated['reason']]);
            $this->recordDecision($request, $rider, $before, 'reject', $validated['reason']);

            return $rider->load('user:id,name,email');
        });

        if (! $rider) {
            return response()->json([
                'message' => 'Pending rider application not found for this sorting center.',
            ], 404);
        }

        app(\App\Services\RiderWorkload::class)->append(new \Illuminate\Database\Eloquent\Collection([$rider]), (int) $sortingCenterId);
        return response()->json([
            'message' => 'Rider rejected successfully.',
            'data' => $rider,
        ], 200);
    }

    public function audits(Request $request, int $id): JsonResponse
    {
        $sortingCenterId = $request->user()->sortingCenter->id;
        abort_unless(Rider::where('sorting_center_id', $sortingCenterId)->whereKey($id)->exists(), 404, 'Rider not found.');
        $this->requireAuditStorage();
        $request->validate(['page' => ['nullable', 'integer', 'min:1']]);
        $history = RiderDecisionAudit::where('sorting_center_id', $sortingCenterId)->where('rider_id', $id)
            ->orderByDesc('occurred_at')->orderByDesc('id')->paginate(10);

        return response()->json(['message' => 'Rider decision history retrieved successfully.', 'data' => $history]);
    }

    private function requireAuditStorage(): void
    {
        abort_unless(Schema::hasTable('rider_decision_audits'), 503, 'Rider decision auditing is unavailable. Contact your administrator.');
    }

    private function decisionState(Rider $rider): array
    {
        return ['approval_status' => $rider->approval_status, 'user_status' => $rider->user->status, 'is_active' => (bool) $rider->is_active];
    }

    private function recordDecision(Request $request, Rider $rider, array $before, string $action, ?string $reason = null): void
    {
        $audit = RiderDecisionAudit::create([
            'sorting_center_id' => $rider->sorting_center_id, 'rider_id' => $rider->id,
            'actor_user_id' => $request->user()->id, 'actor_name' => $request->user()->name,
            'action' => $action, 'old_state' => $before, 'new_state' => $this->decisionState($rider),
            'reason' => $reason, 'occurred_at' => now('UTC'),
        ]);
        abort_unless($audit->exists, 500, 'The Rider decision could not be recorded. Please try again.');
    }

    /**
     * Activate an approved rider.
     * POST /api/sorting-center/riders/{id}/activate
     */
    public function activate(Request $request, int $id): JsonResponse
    {
        return $this->changeAvailability($request, $id, true);
    }

    /**
     * Deactivate an approved rider.
     * POST /api/sorting-center/riders/{id}/deactivate
     */
    public function deactivate(Request $request, int $id): JsonResponse
    {
        return $this->changeAvailability($request, $id, false);
    }

    private function changeAvailability(Request $request, int $id, bool $active): JsonResponse
    {
        $centerId = $request->user()->sortingCenter->id;
        $rider = app(\App\Services\RiderAllocation::class)->run([$id], fn () => DB::transaction(function () use ($request, $id, $active, $centerId): Rider {
            $rider = $this->lockedRider($centerId, $id);
            abort_unless($rider, 404, 'Rider not found.');
            $user = $rider->user;
            abort_unless($rider->approval_status === 'approved', 422, 'Only approved Riders can be suspended or reactivated.');
            abort_if((bool) $rider->is_active === $active, 409, $active ? 'Rider is already active.' : 'Rider is already suspended.');
            if ($active) {
                abort_unless($user->usertype === 'rider' && $user->status === 'approved'
                    && (!$user->partnerApplication()->exists() || $user->hasVerifiedEmail()), 422,
                    'This account must be approved and eligible before reactivation.');
            }
            $reason = $active ? null : $request->validate([
                'reason' => ['bail', 'required', 'string', 'min:10', 'max:300'],
            ])['reason'];
            $this->requireAuditStorage();
            $rider->setRelation('user', $user);
            $before = $this->decisionState($rider);
            $rider->update(['is_active' => $active]);
            $this->recordDecision($request, $rider, $before, $active ? 'reactivate' : 'suspend', $reason);
            if (!$active) app(\App\Services\ReleaseUncollectedPickups::class)->release($rider, $request->user(), $reason);
            return $rider->fresh(['user:id,name,email']);
        }, 5));
        app(\App\Services\RiderPresence::class)->offline($rider);
        app(\App\Services\RiderWorkload::class)->append(new \Illuminate\Database\Eloquent\Collection([$rider]), (int) $centerId);
        return response()->json(['message' => $active ? 'Rider reactivated. Existing jobs are unchanged.'
            : 'Rider suspended. Uncollected pickup claims were released when safe; collected shipments stay with the Rider until a supervised handover.', 'data' => $rider]);
    }

    private function lockedRider(int $centerId, int $id): ?Rider
    {
        $userId = Rider::where('sorting_center_id', $centerId)->whereKey($id)->value('user_id');
        if (!$userId) return null;
        // All management decisions use the same User -> Rider order as coverage/profile changes.
        $user = User::whereKey($userId)->lockForUpdate()->firstOrFail();
        $rider = Rider::where('sorting_center_id', $centerId)->where('user_id', $userId)->whereKey($id)->lockForUpdate()->first();
        return $rider?->setRelation('user', $user);
    }
}
