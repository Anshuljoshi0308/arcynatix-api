<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class ContactController extends Controller
{
    /**
     * Store a new contact form submission.
     */
    public function store(ContactRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // Check for duplicate submission (same email and message in last 5 minutes)
            $recentSubmission = Contact::where('email', $request->email)
                ->where('message', $request->message)
                ->where('created_at', '>', now()->subMinutes(5))
                ->first();

            if ($recentSubmission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate submission detected. Please wait before submitting again.',
                    'contact_id' => $recentSubmission->contact_id,
                    'status' => 429
                ], 429);
            }

            // Create the contact (priority and SLA deadline will be auto-set by model)
            $contact = Contact::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'service' => $request->service,
                'message' => $request->message,
                'priority' => $request->priority, // Optional manual priority override
                'status' => Contact::STATUS_NEW
            ]);

            DB::commit();

            // Clear admin contacts cache
            Cache::forget('admin_contacts_count');
            Cache::forget('admin_contacts_new_count');
            Cache::forget('admin_contacts_stats');

            // Log the submission
            Log::info('New contact form submission', [
                'contact_id' => $contact->contact_id,
                'id' => $contact->id,
                'email' => $contact->email,
                'service' => $contact->service,
                'priority' => $contact->priority,
                'sla_deadline' => $contact->sla_deadline,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Thank you for contacting us! We will get back to you soon.',
                'data' => new ContactResource($contact),
                'contact_id' => $contact->contact_id,
                'priority' => $contact->priority_label,
                'sla_deadline' => $contact->sla_deadline_format,
                'status' => 201
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Contact form submission failed', [
                'error' => $e->getMessage(),
                'email' => $request->email ?? 'unknown',
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Sorry, there was an error processing your request. Please try again later.',
                'status' => 500
            ], 500);
        }
    }

    /**
     * Get all contacts for admin (with pagination and filtering).
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Validate query parameters
            $request->validate([
                'status' => 'sometimes|string|in:new,in_progress,resolved,closed',
                'priority' => 'sometimes|string|in:low,medium,high,urgent',
                'service' => 'sometimes|string|max:100',
                'search' => 'sometimes|string|max:100',
                'handled_by' => 'sometimes|integer|exists:users,id',
                'overdue_only' => 'sometimes|boolean',
                'high_priority_only' => 'sometimes|boolean',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'sort_by' => 'sometimes|string|in:created_at,updated_at,name,email,status,priority,sla_deadline,contact_id',
                'sort_order' => 'sometimes|string|in:asc,desc'
            ]);

            $query = Contact::query()->with('handledByUser');

            // Apply filters
            if ($request->filled('status')) {
                $query->byStatus($request->status);
            }

            if ($request->filled('priority')) {
                $query->byPriority($request->priority);
            }

            if ($request->filled('service')) {
                $query->byService($request->service);
            }

            if ($request->filled('handled_by')) {
                $query->handledBy($request->handled_by);
            }

            if ($request->filled('overdue_only') && in_array($request->overdue_only, ['true', '1'])) {
                $query->overdue();
            }

            if ($request->filled('high_priority_only') && in_array($request->high_priority_only, ['true', '1'])) {
                $query->highPriority();
            }

            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('email', 'LIKE', "%{$search}%")
                      ->orWhere('contact_id', 'LIKE', "%{$search}%")
                      ->orWhere('message', 'LIKE', "%{$search}%");
                });
            }

            // Apply sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            
            // Custom sorting for priority
            if ($sortBy === 'priority') {
                $query->orderByRaw("FIELD(priority, 'urgent', 'high', 'medium', 'low') " . $sortOrder);
            } else {
                $query->orderBy($sortBy, $sortOrder);
            }

            // Paginate results
            $perPage = $request->get('per_page', 15);
            $contacts = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => ContactResource::collection($contacts->items()),
                'meta' => [
                    'current_page' => $contacts->currentPage(),
                    'last_page' => $contacts->lastPage(),
                    'per_page' => $contacts->perPage(),
                    'total' => $contacts->total(),
                    'from' => $contacts->firstItem(),
                    'to' => $contacts->lastItem(),
                    'has_more_pages' => $contacts->hasMorePages(),
                ],
                'filters' => [
                    'status' => $request->status,
                    'priority' => $request->priority,
                    'service' => $request->service,
                    'search' => $request->search,
                    'handled_by' => $request->handled_by,
                    'overdue_only' => $request->boolean('overdue_only'),
                    'high_priority_only' => $request->boolean('high_priority_only'),
                ],
                'status' => 200
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid query parameters',
                'errors' => $e->errors(),
                'status' => 422
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve contacts', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve contacts',
                'status' => 500
            ], 500);
        }
    }

    /**
     * Get a specific contact by ID or contact_id.
     */
    public function show(string $identifier): JsonResponse
    {
        try {
            // Try to find by contact_id first, then by primary key
            $contact = Contact::byContactId($identifier)->first() 
                      ?? Contact::findOrFail($identifier);

            return response()->json([
                'success' => true,
                'data' => new ContactResource($contact),
                'status' => 200
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found',
                'status' => 404
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve contact', [
                'identifier' => $identifier,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve contact',
                'status' => 500
            ], 500);
        }
    }

    /**
     * Update contact status, priority, assignment, and admin notes.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'sometimes|string|in:new,in_progress,resolved,closed',
                'priority' => 'sometimes|string|in:low,medium,high,urgent',
                'handled_by' => 'sometimes|nullable|integer|exists:users,id',
                'admin_notes' => 'nullable|string|max:1000'
            ]);

            $contact = Contact::findOrFail($id);
            $oldStatus = $contact->status;
            $oldPriority = $contact->priority;

            if ($request->filled('status')) {
                $contact->status = $request->status;
            }

            if ($request->filled('priority')) {
                $contact->priority = $request->priority;
            }

            if ($request->has('handled_by')) {
                $contact->handled_by = $request->handled_by;
            }

            if ($request->has('admin_notes')) {
                $contact->admin_notes = $request->admin_notes;
            }

            $contact->save();

            // Clear cache
            Cache::forget('admin_contacts_count');
            Cache::forget('admin_contacts_new_count');
            Cache::forget('admin_contacts_stats');

            Log::info('Contact updated', [
                'contact_id' => $contact->contact_id,
                'id' => $contact->id,
                'old_status' => $oldStatus,
                'new_status' => $contact->status,
                'old_priority' => $oldPriority,
                'new_priority' => $contact->priority,
                'handled_by' => $contact->handled_by,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contact updated successfully',
                'data' => new ContactResource($contact),
                'status' => 200
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found',
                'status' => 404
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
                'status' => 422
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update contact', [
                'contact_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update contact',
                'status' => 500
            ], 500);
        }
    }

    /**
     * Assign contact to a user.
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'user_id' => 'required|integer|exists:users,id'
            ]);

            $contact = Contact::findOrFail($id);
            $contact->handled_by = $request->user_id;
            
            // Auto-update status if it's still new
            if ($contact->status === Contact::STATUS_NEW) {
                $contact->status = Contact::STATUS_IN_PROGRESS;
            }
            
            $contact->save();

            Log::info('Contact assigned', [
                'contact_id' => $contact->contact_id,
                'assigned_to' => $request->user_id,
                'assigned_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contact assigned successfully',
                'data' => new ContactResource($contact),
                'status' => 200
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to assign contact', [
                'contact_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to assign contact',
                'status' => 500
            ], 500);
        }
    }

    /**
     * Get contact statistics for admin dashboard.
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = Cache::remember('admin_contacts_stats', 300, function () {
                return [
                    // Status counts
                    'by_status' => [
                        'total' => Contact::count(),
                        'new' => Contact::byStatus(Contact::STATUS_NEW)->count(),
                        'in_progress' => Contact::byStatus(Contact::STATUS_IN_PROGRESS)->count(),
                        'resolved' => Contact::byStatus(Contact::STATUS_RESOLVED)->count(),
                        'closed' => Contact::byStatus(Contact::STATUS_CLOSED)->count(),
                    ],
                    
                    // Priority counts
                    'by_priority' => [
                        'urgent' => Contact::byPriority(Contact::PRIORITY_URGENT)->count(),
                        'high' => Contact::byPriority(Contact::PRIORITY_HIGH)->count(),
                        'medium' => Contact::byPriority(Contact::PRIORITY_MEDIUM)->count(),
                        'low' => Contact::byPriority(Contact::PRIORITY_LOW)->count(),
                    ],
                    
                    // Time-based counts
                    'by_time' => [
                        'today' => Contact::whereDate('created_at', today())->count(),
                        'this_week' => Contact::whereBetween('created_at', [
                            now()->startOfWeek(),
                            now()->endOfWeek()
                        ])->count(),
                        'this_month' => Contact::whereMonth('created_at', now()->month)
                            ->whereYear('created_at', now()->year)
                            ->count(),
                    ],
                    
                    // SLA and performance
                    'performance' => [
                        'overdue' => Contact::overdue()->count(),
                        'high_priority_pending' => Contact::highPriority()
                            ->whereIn('status', [Contact::STATUS_NEW, Contact::STATUS_IN_PROGRESS])
                            ->count(),
                        'unassigned' => Contact::whereNull('handled_by')
                            ->whereNotIn('status', [Contact::STATUS_RESOLVED, Contact::STATUS_CLOSED])
                            ->count(),
                        'avg_response_time' => Contact::whereNotNull('updation_timestamp')
                            ->avg(DB::raw('TIMESTAMPDIFF(MINUTE, request_timestamp, updation_timestamp)'))
                    ],
                    
                    // Recent activity
                    'recent' => [
                        'last_24h' => Contact::where('created_at', '>=', now()->subDay())->count(),
                        'urgent_today' => Contact::byPriority(Contact::PRIORITY_URGENT)
                            ->whereDate('created_at', today())->count(),
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $stats,
                'status' => 200
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve contact stats', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'status' => 500
            ], 500);
        }
    }

    /**
     * Get overdue contacts.
     */
    public function overdue(): JsonResponse
    {
        try {
            $overdueContacts = Contact::overdue()
                ->with('handledByUser')
                ->orderBy('sla_deadline', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => ContactResource::collection($overdueContacts),
                'count' => $overdueContacts->count(),
                'status' => 200
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to retrieve overdue contacts', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve overdue contacts',
                'status' => 500
            ], 500);
        }
    }

    /**
     * Customer tracking endpoint (by contact_id).
     */
    public function track(string $contactId): JsonResponse
    {
        try {
            $contact = Contact::byContactId($contactId)->first();

            if (!$contact) {
                return response()->json([
                    'success' => false,
                    'message' => 'Contact not found',
                    'status' => 404
                ], 404);
            }

            // Return limited information for customer tracking
            return response()->json([
                'success' => true,
                'data' => [
                    'contact_id' => $contact->contact_id,
                    'status' => $contact->status_label,
                    'priority' => $contact->priority_label,
                    'submitted_at' => $contact->request_time_format,
                    'last_updated' => $contact->updation_time_format,
                    'sla_status' => $contact->time_to_sla,
                ],
                'status' => 200
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to track contact', [
                'contact_id' => $contactId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to track contact',
                'status' => 500
            ], 500);
        }
    }

    /**
     * Delete a contact (soft delete).
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $contact = Contact::findOrFail($id);
            $contactId = $contact->contact_id;
            $contact->delete();

            // Clear cache
            Cache::forget('admin_contacts_count');
            Cache::forget('admin_contacts_stats');

            Log::info('Contact deleted', [
                'contact_id' => $contactId,
                'id' => $id,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contact deleted successfully',
                'status' => 200
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found',
                'status' => 404
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete contact', [
                'contact_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete contact',
                'status' => 500
            ], 500);
        }
    }
}