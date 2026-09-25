<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the YOLO11n bounding boxes of a reported photo, next to the plain
 * photo in image_path — same JSON shape as user_detections.detection_boxes:
 *   {"boxes":[{className,label,confidence,box:{x,y,width,height}}], "src_w":..., "src_h":...}
 * The report pages draw them on the photo when it is clicked to enlarge.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('farmer_reports', 'detection_boxes')) {
            Schema::table('farmer_reports', function (Blueprint $table) {
                $table->longText('detection_boxes')->nullable()->after('image_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('farmer_reports', 'detection_boxes')) {
            Schema::table('farmer_reports', function (Blueprint $table) {
                $table->dropColumn('detection_boxes');
            });
        }
    }
};