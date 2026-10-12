<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of the farmer's "Suggest Improvement" (type = suggestion) or
 * "General Feedback" (type = general) form. Table: farmer_feedback.
 *
 * IMPORTANT: every column must be listed in $fillable. A column that is
 * missing here is silently dropped by ::create() / ->update(), which is the
 * classic reason "the form says sent but nothing is stored".
 */
class FarmerFeedback extends Model
{
    public const TYPE_SUGGESTION = 'suggestion';
    public const TYPE_GENERAL    = 'general';

    protected $table = 'farmer_feedback';

    protected $fillable = [
        'code', 'user_id', 'type', 'category', 'rating', 'feature',
        'message', 'screenshot', 'allow_contact', 'status',
        // Admin assigns a technician to a suggestion
        'assigned_technician_id', 'assigned_at', 'assigned_priority', 'assigned_notes',
        // Technician OR admin resolves it (support_by says who)
        'support_status', 'support_notes', 'support_updated_at', 'support_by',
    ];

    protected $casts = [
        'allow_contact'      => 'boolean',
        'rating'             => 'integer',
        'assigned_at'        => 'datetime',
        'support_updated_at' => 'datetime',
    ];

    public function farmer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}