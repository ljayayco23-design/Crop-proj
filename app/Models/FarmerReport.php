<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * app/Models/FarmerReport.php
 *
 * A farmer's report about a detection result, plus the technician's review.
 * See the create_farmer_reports_table migration for why the detection data
 * is snapshotted into this row instead of joined.
 */
class FarmerReport extends Model
{
    use HasFactory;

    protected $table = 'farmer_reports';

    protected $fillable = [
        'report_code',
        'user_id',
        'technician_id',
        'class_key',
        'class_name',
        'confidence',
        'severity_label',
        'severity_percent',
        'source',
        'image_path',
        'info',
        'problem_types',
        'flagged_sections',
        'message',
        'suggested_class',
        'support_image_path',
        'status',
        'assessment',
        'corrected_class',
        'corrected_name',
        'corrected_severity',
        'corrected_sections',
        'notes',
        'advice',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'info'               => 'array',
        'problem_types'      => 'array',
        'flagged_sections'   => 'array',
        'corrected_sections' => 'array',
        'reviewed_at'        => 'datetime',
    ];

    public function farmer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function technician()
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    /** True once a technician has acted on it. */
    public function isReviewed(): bool
    {
        return $this->assessment !== null;
    }

    /**
     * Next human-facing code: RP-001, RP-002, ... Derived from the highest
     * existing id so it never collides, and zero-padded to 3 while the
     * numbers are small.
     */
    public static function nextReportCode(): string
    {
        $next = (int) (self::max('id') ?? 0) + 1;

        return 'RP-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}