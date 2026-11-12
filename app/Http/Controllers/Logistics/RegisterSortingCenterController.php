<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;

use App\Http\Requests\RegisterSortingCenterRequest;
use App\Models\SortingCenter;
use App\Models\User;
use App\Services\RegistrationDocumentStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RegisterSortingCenterController extends Controller
{
    public function register(
        RegisterSortingCenterRequest $request,
        RegistrationDocumentStorage $documents,
    ): JsonResponse {
        try {
            $idDocUrl = $documents->store($request->file('id_doc'), 'id-documents');
        } catch (\Exception $e) {
            Log::error('ID document upload exception: ' . $e->getMessage());
            return response()->json([
                'message' => 'ID document upload failed: ' . $e->getMessage(),
            ], 400);
        }
        try {
            $permitDocUrl = $documents->store($request->file('permit_doc'), 'permit-documents');
        } catch (\Exception $e) {
            Log::error('Permit document upload exception: ' . $e->getMessage());
            return response()->json([
                'message' => 'Permit document upload failed: ' . $e->getMessage(),
            ], 400);
        }

        // If any upload failed, abort registration
        if (!$idDocUrl || !$permitDocUrl) {
            return response()->json([
                'message' => 'Document upload failed. Please ensure files are valid and try again.',
            ], 400);
        }

        $user = DB::transaction(function () use ($request, $idDocUrl, $permitDocUrl): User {
            $user = User::create([
                'name' => $request->business_name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'status' => 'pending',
                'usertype' => 'sorting_center',
            ]);

            SortingCenter::create([
                'user_id' => $user->id,
                'business_name' => $request->business_name,
                'permit_doc_url' => $permitDocUrl,
                'id_doc_url' => $idDocUrl,
                'approval_status' => 'pending',
            ]);
            return $user;
        });

        return response()->json([
            'message' => 'Registration submitted successfully. Awaiting admin approval.',
            'data' => [
                'user_id' => $user->id,
                'business_name' => $request->business_name,
                'approval_status' => 'pending',
            ],
        ], 201);
    }
}
