<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Using an anonymous class prevents the "Class Not Found" error
return new class extends Migration
{
    public function up()
    {
        // Using raw SQL bypasses the need for the doctrine/dbal composer package
        DB::statement('ALTER TABLE user_detections MODIFY image_path LONGTEXT');
    }

    public function down()
    {
        DB::statement('ALTER TABLE user_detections MODIFY image_path VARCHAR(255)');
    }
};