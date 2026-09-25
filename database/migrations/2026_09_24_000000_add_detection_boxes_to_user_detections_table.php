<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stores the raw YOLO11n bounding-box data for a scan (one row per
     * detected box: class, confidence, x/y/width/height in the ORIGINAL
     * photo's pixel coordinates) plus the source image width/height those
     * coordinates were measured against, as a single JSON blob:
     *   {"boxes":[{"className":"...","label":"...","confidence":0.87,
     *              "box":{"x":10,"y":20,"width":100,"height":80}}, ...],
     *    "src_w":1280,"src_h":960}
     *
     * Deliberately kept separate from `image_path` instead of baking the
     * boxes into the saved photo's pixels — see the comments in
     * FarmerHistoryController::saveDetection() / index_blade.php's
     * buildYoloDetectionSnapshot() about the canvas-goes-solid-black
     * failure mode that baking-into-pixels was already causing. Storing
     * the coordinates instead lets history.blade.php redraw them as an
     * overlay on top of the plain photo, which can never corrupt the
     * underlying image.
     *
     * Nullable/absent for every non-YOLO11n scan (model/groq), and for
     * YOLO11n scans saved before this migration ran.
     */
    public function up(): void
    {
        Schema::table('user_detections', function (Blueprint $table) {
            $table->json('detection_boxes')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('user_detections', function (Blueprint $table) {
            $table->dropColumn('detection_boxes');
        });
    }
};