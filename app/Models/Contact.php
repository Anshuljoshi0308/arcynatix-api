<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Contact extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'contact_id',
        'name',
        'email',
        'phone',
        'service',
        'message',
        'status',
        'priority',
        'sla_deadline',
        'admin_notes',
        'handled_by',
        'request_timestamp',
        'updation_timestamp'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'request_timestamp' => 'datetime',
        'updation_timestamp' => 'datetime',
        'sla_deadline' => 'datetime',
    ];

    protected $hidden = [
        'admin_notes'
    ];

    // Status constants
    const STATUS_NEW = 'new';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    // Priority constants
    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_URGENT = 'urgent';

    // SLA response times (in hours)
    const SLA_TIMES = [
        self::PRIORITY_URGENT => 1,    // 1 hour
        self::PRIORITY_HIGH => 4,      // 4 hours
        self::PRIORITY_MEDIUM => 24,   // 24 hours
        self::PRIORITY_LOW => 72,      // 72 hours
    ];

    public static function getStatuses()
    {
        return [
            self::STATUS_NEW,
            self::STATUS_IN_PROGRESS,
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED
        ];
    }

    public static function getPriorities()
    {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_MEDIUM,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT
        ];
    }

    public static function getSLATimes()
    {
        return self::SLA_TIMES;
    }

    // Relationships
    public function handledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPriority($query, $priority)
    {
        return $query->where('priority', $priority);
    }

    public function scopeRecent($query)
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeByService($query, $service)
    {
        return $query->where('service', $service);
    }

    public function scopeHandledBy($query, $userId)
    {
        return $query->where('handled_by', $userId);
    }

    public function scopeRequestedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('request_timestamp', [$startDate, $endDate]);
    }

    public function scopeUpdatedBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('updation_timestamp', [$startDate, $endDate]);
    }

    public function scopeOverdue($query)
    {
        return $query->where('sla_deadline', '<', now())
                    ->whereNotIn('status', [self::STATUS_RESOLVED, self::STATUS_CLOSED]);
    }

    public function scopeByContactId($query, $contactId)
    {
        return $query->where('contact_id', $contactId);
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', [self::PRIORITY_HIGH, self::PRIORITY_URGENT]);
    }

    // Accessors
    public function getFormattedPhoneAttribute()
    {
        return $this->phone;
    }

    public function getStatusLabelAttribute()
    {
        return ucfirst(str_replace('_', ' ', $this->status));
    }

    public function getPriorityLabelAttribute()
    {
        return ucfirst($this->priority);
    }

    public function getHandlerNameAttribute()
    {
        return $this->handledByUser ? $this->handledByUser->name : 'Unassigned';
    }

    public function getRequestTimeFormatAttribute()
    {
        return $this->request_timestamp ? $this->request_timestamp->format('M d, Y H:i A') : null;
    }

    public function getUpdationTimeFormatAttribute()
    {
        return $this->updation_timestamp ? $this->updation_timestamp->format('M d, Y H:i A') : null;
    }

    public function getSlaDeadlineFormatAttribute()
    {
        return $this->sla_deadline ? $this->sla_deadline->format('M d, Y H:i A') : null;
    }

    public function getIsOverdueAttribute()
    {
        if (!$this->sla_deadline || in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED])) {
            return false;
        }
        return $this->sla_deadline < now();
    }

    public function getTimeToSlaAttribute()
    {
        if (!$this->sla_deadline || in_array($this->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED])) {
            return null;
        }
        
        $diff = now()->diffInHours($this->sla_deadline, false);
        
        if ($diff < 0) {
            return 'Overdue by ' . abs($diff) . ' hours';
        } else {
            return $diff . ' hours remaining';
        }
    }

    public function getPriorityColorAttribute()
    {
        return match($this->priority) {
            self::PRIORITY_LOW => '#28a745',      // Green
            self::PRIORITY_MEDIUM => '#ffc107',   // Yellow
            self::PRIORITY_HIGH => '#fd7e14',     // Orange
            self::PRIORITY_URGENT => '#dc3545',   // Red
            default => '#6c757d'                  // Gray
        };
    }

    // Mutators
    public function setRequestTimestampAttribute($value)
    {
        $this->attributes['request_timestamp'] = $value ?? now();
    }

    public function setUpdationTimestampAttribute($value)
    {
        $this->attributes['updation_timestamp'] = $value ?? now();
    }

    public function setPriorityAttribute($value)
    {
        $this->attributes['priority'] = $value ?? self::PRIORITY_MEDIUM;
        
        // Auto-set SLA deadline when priority is set
        if ($value && !$this->sla_deadline) {
            $this->setSlaDeadlineFromPriority($value);
        }
    }

    // Helper Methods
    public function generateUniqueContactId()
    {
        do {
            $contactId = 'CT-' . date('Y') . '-' . strtoupper(Str::random(6));
        } while (self::where('contact_id', $contactId)->exists());
        
        return $contactId;
    }

    public function setSlaDeadlineFromPriority($priority = null)
    {
        $priority = $priority ?? $this->priority ?? self::PRIORITY_MEDIUM;
        $hours = self::SLA_TIMES[$priority] ?? 24;
        
        $this->attributes['sla_deadline'] = $this->request_timestamp 
            ? Carbon::parse($this->request_timestamp)->addHours($hours)
            : now()->addHours($hours);
    }

    public function calculateResponseTime()
    {
        if ($this->status === self::STATUS_NEW) {
            return null;
        }
        
        // Calculate time from request to first response (status change from 'new')
        $firstResponse = $this->updated_at;
        $requestTime = $this->request_timestamp ?? $this->created_at;
        
        return $requestTime->diffInMinutes($firstResponse);
    }

    // Auto-assign priority based on service type
    public function autoAssignPriority()
    {
        $urgentServices = ['technical_issue', 'billing_dispute', 'account_locked'];
        $highServices = ['support', 'complaint'];
        $mediumServices = ['general_inquiry', 'partnership'];
        
        if (in_array($this->service, $urgentServices)) {
            return self::PRIORITY_URGENT;
        } elseif (in_array($this->service, $highServices)) {
            return self::PRIORITY_HIGH;
        } elseif (in_array($this->service, $mediumServices)) {
            return self::PRIORITY_MEDIUM;
        }
        
        return self::PRIORITY_LOW;
    }

    // Model Events
    protected static function boot()
    {
        parent::boot();

        // Automatically set fields when creating
        static::creating(function ($contact) {
            // Generate unique contact ID
            if (!$contact->contact_id) {
                $contact->contact_id = $contact->generateUniqueContactId();
            }
            
            // Set request timestamp
            if (!$contact->request_timestamp) {
                $contact->request_timestamp = now();
            }
            
            // Auto-assign priority if not set
            if (!$contact->priority) {
                $contact->priority = $contact->autoAssignPriority();
            }
            
            // Set SLA deadline based on priority
            if (!$contact->sla_deadline) {
                $contact->setSlaDeadlineFromPriority();
            }
        });

        // Automatically update updation_timestamp when updating
        static::updating(function ($contact) {
            $contact->updation_timestamp = now();
            
            // Update SLA deadline if priority changed
            if ($contact->isDirty('priority')) {
                $contact->setSlaDeadlineFromPriority($contact->priority);
            }
        });
    }
}