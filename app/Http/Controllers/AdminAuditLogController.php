<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminAuditLogController extends Controller
{
    /**
     * Display a chronological feed of administrative activities.
     */
    public function index(Request $request): Response
    {
        $query = AdminAuditLog::query()
            ->with(['admin:id,name,email,usertype'])
            ->latest();

        if ($request->filled('action') && $request->input('action') !== 'all') {
            $query->where('action', $request->input('action'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhereHas('admin', function ($adminQ) use ($search) {
                      $adminQ->where('name', 'like', "%{$search}%")
                             ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $logs = $query->paginate(20)->withQueryString();

        $actionTypes = AdminAuditLog::query()
            ->select('action')
            ->distinct()
            ->pluck('action');

        return Inertia::render('Admin/AuditLogs', [
            'title' => 'Admin Activity Audit Logs',
            'eyebrow' => 'Security & Compliance',
            'description' => 'Chronological tracking of administrative decisions, account suspensions, payouts, policies, and catalog changes.',
            'active' => 'audit_logs',
            'logs' => $logs,
            'actionTypes' => $actionTypes,
            'filters' => [
                'search' => (string) $request->input('search', ''),
                'action' => (string) $request->input('action', 'all'),
            ],
        ]);
    }
}

