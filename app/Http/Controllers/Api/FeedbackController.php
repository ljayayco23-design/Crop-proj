<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\FarmerReportController;
use App\Models\FarmerFeedback;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile (Flutter) side of "Suggest Improvement" + "General Feedback".
 *
 * Like ReportController, nothing is re-implemented: saving and presenting an
 * entry call the SAME methods the website uses (FarmerReportController
 * @storeFeedback / @presentFeedback), so the admin and technician see an entry
 * from the app exactly like one from the website, and the farmer sees the
 * admin / technician response (support_* + assigned_*) in the same shape.
 *
 * Phone extras: Sanctum auth, light list (the screenshot is NOT inside the
 * list - GET /feedback/{id} returns it), and offline retry protection
 * (captured_at -> created_at + duplicate guard).
 */
class FeedbackController extends Controller
{
    private function denyIfNotFarmer(Request $request)
    {
        $user = $request->user();
        if (!$user || strtolower(trim((string) $user->role)) !== 'farmer') {
            return response()->json(['success' => false, 'message' => 'Farmers only.'], 403);
        }
        // FarmerReportController reads auth()->user(); make sure it is the token's user.
        Auth::setUser($user);
        return null;
    }

    private function web(): FarmerReportController
    {
        return app(FarmerReportController::class);
    }

    /** My suggestions + my general feedback, with the team's response on each. */
    public function index(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        $user = $request->user();

        if (!Schema::hasTable('farmer_feedback')) {
            return response()->json(['success' => true, 'suggestions' => [], 'feedbacks' => []]);
        }

        $entries = FarmerFeedback::where('user_id', $user->id)->latest()->limit(200)->get();
        $names   = $this->web()->techNamesFor($entries);

        $present = function (FarmerFeedback $f) use ($user, $names) {
            $hasShot = !empty($f->screenshot);
            // Not saved - only stops presentFeedback() from building the data URI.
            $f->screenshot = null;
            $p = $this->web()->presentFeedback($f, 'farmer', $user->id, $names);
            $p['has_screenshot'] = $hasShot;
            return $p;
        };

        return response()->json([
            'success'     => true,
            'suggestions' => $entries->where('type', FarmerFeedback::TYPE_SUGGESTION)->map($present)->values()->all(),
            'feedbacks'   => $entries->where('type', FarmerFeedback::TYPE_GENERAL)->map($present)->values()->all(),
        ]);
    }

    /** One of MY entries, with its screenshot (404 for anyone else's id). */
    public function show(Request $request, $id)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        $user = $request->user();

        $entry = FarmerFeedback::where('user_id', $user->id)->findOrFail($id);
        $names = $this->web()->techNamesFor([$entry]);

        $p = $this->web()->presentFeedback($entry, 'farmer', $user->id, $names);
        $p['has_screenshot'] = !empty($p['screenshot']);

        return response()->json(['success' => true, 'entry' => $p]);
    }

    /** Same validation + insert as the website, plus the offline helpers. */
    public function store(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        $user = $request->user();

        // Entries written OFFLINE carry the moment they were really written.
        // Use it as created_at and ignore a retry that already reached the server.
        $createdAt   = null;
        $capturedRaw = $request->input('captured_at');
        if ($capturedRaw && Schema::hasTable('farmer_feedback')) {
            try {
                $cap = Carbon::parse($capturedRaw)->setTimezone(config('app.timezone'));
                if ($cap->lte(now()->addMinutes(5)) && $cap->gte(now()->subYear())) {
                    $createdAt = $cap;
                    $dup = DB::table('farmer_feedback')
                        ->where('user_id', $user->id)
                        ->where('type', $request->input('type'))
                        ->where('message', $request->input('message'))
                        ->where('created_at', $cap->format('Y-m-d H:i:s'))
                        ->first();
                    if ($dup) {
                        return response()->json([
                            'success'   => true,
                            'duplicate' => true,
                            'id'        => $dup->id,
                            'code'      => $dup->code,
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                // bad timestamp -> just use "now"
            }
        }

        $response = $this->web()->storeFeedback($request);
        $payload  = method_exists($response, 'getData') ? $response->getData(true) : [];

        if (!empty($payload['success']) && !empty($payload['code'])) {
            $entry = FarmerFeedback::where('code', $payload['code'])->first();
            if ($entry) {
                $payload['id'] = $entry->id;
                if ($createdAt) {
                    DB::table('farmer_feedback')->where('id', $entry->id)->update([
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }
            }
            return response()->json($payload);
        }

        return $response;
    }

    /** Delete one of MY entries (same rule as the website: own entries only). */
    public function destroy(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        $user = $request->user();

        $entry = FarmerFeedback::where('user_id', $user->id)->find((int) $request->input('id'));
        if (!$entry) {
            return response()->json(['success' => false, 'message' => 'Entry not found.'], 404);
        }
        $entry->delete();

        return response()->json(['success' => true]);
    }
}