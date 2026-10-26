<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One-row switch + last dry-run preview. Starts in dry_run: nothing is
        // emailed until an admin presses "Go Live".
        Schema::create('weather_alert_settings', function (Blueprint $t) {
            $t->id();
            $t->string('mode', 16)->default('dry_run');          // off | dry_run | live
            $t->timestamp('live_since')->nullable();
            $t->timestamp('last_run_at')->nullable();
            $t->string('last_run_mode', 16)->nullable();
            $t->timestamp('last_dry_run_ok_at')->nullable();      // gate for "Go Live"
            $t->longText('last_preview')->nullable();             // JSON
            $t->text('last_error')->nullable();
            $t->unsignedBigInteger('changed_by')->nullable();
            $t->timestamp('changed_at')->nullable();
            $t->timestamps();
        });

        // Every alert a farmer was told about: email + in-app "Recent Alerts"
        // card + the memory that prevents duplicate emails.
        Schema::create('weather_alerts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('source', 16);                             // pagasa | forecast
            $t->string('dedupe_key', 191);                        // CAP identifier, or fc:YYYY-MM-DD
            $t->string('throttle_group', 120)->nullable();
            $t->string('label', 40);                              // Official PAGASA Advisory | Forecast-based
            $t->string('level', 40)->nullable();                  // Signal #2 | Moderate | High
            $t->string('event', 191)->nullable();
            $t->string('headline', 255)->nullable();
            $t->text('body')->nullable();
            $t->text('instruction')->nullable();
            $t->text('fields')->nullable();                       // JSON: [{key,name,method,area}]
            $t->string('status', 16)->index();                    // pending | sent | failed | no_email | baselined
            $t->string('status_reason', 255)->nullable();
            $t->text('error')->nullable();
            $t->timestamp('issued_at')->nullable()->index();
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('sent_at')->nullable();
            $t->timestamps();

            $t->unique(['user_id', 'source', 'dedupe_key'], 'weather_alerts_dedupe');
        });

        // Parsed PAGASA CAP files, so repeat runs don't re-download ~30 files every time.
        Schema::create('weather_alert_cap_cache', function (Blueprint $t) {
            $t->string('identifier', 64)->primary();
            $t->longText('payload');
            $t->timestamp('fetched_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_alert_cap_cache');
        Schema::dropIfExists('weather_alerts');
        Schema::dropIfExists('weather_alert_settings');
    }
};