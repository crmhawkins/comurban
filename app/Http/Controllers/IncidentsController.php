<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class IncidentsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of incidents
     */
    public function index(Request $request)
    {
        $query = Incident::with(['conversation.contact', 'call', 'contact'])
            ->orderBy('created_at', 'desc');

        // Filter by source type
        if ($request->has('source_type') && $request->source_type) {
            $query->where('source_type', $request->source_type);
        }

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by incident type
        if ($request->has('incident_type') && $request->incident_type) {
            $query->where('incident_type', $request->incident_type);
        }

        // Search by phone number or summary
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('phone_number', 'like', "%{$search}%")
                  ->orWhere('incident_summary', 'like', "%{$search}%")
                  ->orWhere('conversation_summary', 'like', "%{$search}%");
            });
        }

        $incidents = $query->paginate(20);

        // Get statistics
        $stats = [
            'total' => Incident::count(),
            'open' => Incident::where('status', 'open')->count(),
            'in_progress' => Incident::where('status', 'in_progress')->count(),
            'resolved' => Incident::where('status', 'resolved')->count(),
            'closed' => Incident::where('status', 'closed')->count(),
            'whatsapp' => Incident::where('source_type', 'whatsapp')->count(),
            'call' => Incident::where('source_type', 'call')->count(),
        ];

        return view('incidents.index', [
            'incidents' => $incidents,
            'stats' => $stats,
            'filters' => [
                'source_type' => $request->source_type,
                'status' => $request->status,
                'incident_type' => $request->incident_type,
                'search' => $request->search,
            ],
        ]);
    }

    /**
     * Show a specific incident
     */
    public function show(string $id)
    {
        $incident = Incident::with(['conversation.contact', 'call', 'contact'])
            ->findOrFail($id);

        return view('incidents.show', [
            'incident' => $incident,
        ]);
    }

    /**
     * Update incident status
     */
    public function updateStatus(Request $request, string $id)
    {
        $incident = Incident::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        $incident->update([
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Estado de la incidencia actualizado correctamente');
    }
}
