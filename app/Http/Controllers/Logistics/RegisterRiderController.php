<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;

use App\Http\Requests\RegisterRiderRequest;
use App\Models\Rider;
use App\Models\User;
use App\Services\RegistrationDocumentStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class RegisterRiderController extends Controller
{
    public function register(
        RegisterRiderRequest $request,
        RegistrationDocumentStorage $documents,
    ): JsonResponse {
        $fullName = trim(implode(' ', array_filter([
            $request->first_name,
            $request->middle_initial,
            $request->last_name,
        ])));

        try {
            $orCrDocUrl = $documents->store($request->file('or_cr_doc'), 'or-cr-documents');
        } catch (\Exception $e) {
            Log::error('OR/CR upload exception: ' . $e->getMessage());
            return response()->json([
                'message' => 'OR/CR document upload failed: ' . $e->getMessage(),
            ], 400);
        }

        try {
            $licenseDocUrl = $documents->store($request->file('license_doc'), 'license-documents');
        } catch (\Exception $e) {
            Log::error('License upload exception: ' . $e->getMessage());
            return response()->json([
                'message' => 'License document upload failed: ' . $e->getMessage(),
            ], 400);
        }

        if (!$orCrDocUrl || !$licenseDocUrl) {
            return response()->json([
                'message' => 'Document upload failed. Please ensure files are valid and try again.',
            ], 400);
        }

        [$user, $rider] = DB::transaction(function () use ($request, $fullName, $orCrDocUrl, $licenseDocUrl): array {
            $user = User::create([
                'name' => $fullName,
                'first_name' => $request->first_name,
                'middle_initial' => $request->middle_initial,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'status' => 'pending',
                'usertype' => 'rider',
            ]);

            $rider = Rider::create([
                'user_id' => $user->id,
                'sorting_center_id' => $request->validated('sorting_center_id'),
                'name' => $fullName,
                'contact' => $request->contact,
                'sex' => $request->sex,
                'birthday' => $request->birthday,
                'province' => $request->province,
                'municipality' => $request->municipality,
                'barangay' => $request->barangay,
                'street' => $request->street,
                'house_number' => $request->house_number,
                'vehicle_type' => $request->vehicle_type,
                'plate_number' => $request->plate_number,
                'or_cr_doc_url' => $orCrDocUrl,
                'license_doc_url' => $licenseDocUrl,
                'approval_status' => 'pending',
                'is_active' => true,
            ]);

            return [$user, $rider];
        });

        return response()->json([
            'message' => 'Rider registration submitted successfully. Awaiting sorting center approval.',
            'data' => [
                'id' => $rider->id,
                'user_id' => $user->id,
                'sorting_center_id' => $rider->sorting_center_id,
                'name' => $rider->name,
                'first_name' => $user->first_name,
                'middle_initial' => $user->middle_initial,
                'last_name' => $user->last_name,
                'contact' => $rider->contact,
                'sex' => $rider->sex,
                'birthday' => $rider->birthday?->toDateString(),
                'age' => $rider->age,
                'province' => $rider->province,
                'municipality' => $rider->municipality,
                'barangay' => $rider->barangay,
                'street' => $rider->street,
                'house_number' => $rider->house_number,
                'vehicle_type' => $rider->vehicle_type,
                'plate_number' => $rider->plate_number,
                'or_cr_doc_url' => $rider->or_cr_doc_url,
                'license_doc_url' => $rider->license_doc_url,
                'approval_status' => $rider->approval_status,
                'is_active' => $rider->is_active,
            ],
        ], 201);
    }
}
