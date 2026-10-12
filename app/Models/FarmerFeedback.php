<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A farmer's "Suggest Improvement" or "General Feedback" entry
 * (Feedback & Support page, views/farmer/farmer_report.blade.php).
 *
 * One table, two kinds, told apart by `type`:
 *   - 'suggestion' -> category + message (+ optional screenshot)
 *   - 'general'    -> rating (1-5) + message + feature
 *
 * Wrong-detection reports are NOT stored here — they stay in farmer_reports.
 */
class FarmerFeedback extends Model
{
    protected $table = 'farmer_feedback';

    public const TYPE_SUGGESTION = 'suggestion';
    public const TYPE_GENERAL    = 'general';

    protected $fillable = [
        'code',
        'user_id',
        'type',
        'category',
        'rating',
        'feature',
        'message',
        'screenshot',
        'allow_contact',
        'status',
    ];

    protected $casts = [
        'rating'        => 'integer',
        'allow_contact' => 'boolean',
    ];

    public function farmer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}