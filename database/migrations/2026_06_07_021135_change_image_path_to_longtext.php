public function up()
{
    Schema::table('user_detections', function (Blueprint $table) {
        $table->longText('image_path')->nullable()->change();
    });
}

public function down()
{
    Schema::table('user_detections', function (Blueprint $table) {
        $table->string('image_path')->nullable()->change();
    });
}