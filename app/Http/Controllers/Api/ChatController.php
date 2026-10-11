<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\ChatController as WebChatController;
use App\Http\Controllers\FarmerChatController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mobile (Flutter) side of "Messenger" (live_com) and the AI Assistant.
 *
 * Nothing is re-implemented that the website already does: the "who can I
 * talk to" rules, reading a thread, sending / editing / deleting and the AI
 * answer all call the SAME methods as the website
 * (App\Http\Controllers\ChatController / FarmerChatController), so the app
 * and the website always see the same people and the same messages.
 *
 * What this adds on top, for phones:
 *   - Sanctum token auth (same Auth::setUser() trick as the other API controllers)
 *   - {success: true, ...} wrappers the app expects
 *   - offline queue support: a message written offline carries `captured_at`
 *     (used as its created_at) and a retry of one that already arrived is ignored
 */
class ChatController extends Controller
{
    private function denyIfNotFarmer(Request $request)
    {
        $user = $request->user();
        if (!$user || strtolower(trim((string) $user->role)) !== 'farmer') {
            return response()->json(['success' => false, 'message' => 'Farmers only.'], 403);
        }
        // The web controllers read Auth::id() / Auth::user(); make it the token's user.
        Auth::setUser($user);
        return null;
    }

    private function web(): WebChatController
    {
        return app(WebChatController::class);
    }

    /** GET /chat/users -> people this farmer may message (same rule as the website). */
    public function users(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }

        return response()->json([
            'success' => true,
            'users'   => $this->web()->getUsers()->getData(true),
        ]);
    }

    /** GET /chat/messages            -> Global Group Chat
     *  GET /chat/messages?to_user=ID -> private thread (404 if not allowed) */
    public function messages(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }

        return response()->json([
            'success'  => true,
            'messages' => $this->web()->getMessages($request)->getData(true),
        ]);
    }

    /**
     * POST /chat/send
     *   new group message : {message, is_group: "1"}
     *   new private       : {message, to_user: ID}
     *   edit              : {message, edit_id: ID}
     * Optional {captured_at}: when the message was really written offline.
     */
    public function send(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        $user = $request->user();

        $message = trim((string) $request->input('message', ''));
        if ($message === '') {
            return response()->json(['success' => false, 'message' => 'Message is empty.'], 422);
        }

        $editId  = (int) $request->input('edit_id', 0);
        $isGroup = in_array($request->input('is_group'), [1, '1', true, 'true'], true);
        $toId    = $isGroup ? 0 : (int) $request->input('to_user', 0);

        // Offline helpers (new messages only)
        $createdAt = null;
        if ($editId === 0 && $request->filled('captured_at')) {
            try {
                $cap = Carbon::parse($request->input('captured_at'))->setTimezone(config('app.timezone'));
                if ($cap->lte(now()->addMinutes(5)) && $cap->gte(now()->subDays(7))) {
                    $createdAt = $cap;
                    $dup = DB::table('messages')
                        ->where('from_user_id', $user->id)
                        ->where('to_user_id', $toId)
                        ->where('message', $message)
                        ->where('created_at', $cap->format('Y-m-d H:i:s'))
                        ->first();
                    if ($dup) {
                        return response()->json(['success' => true, 'duplicate' => true, 'id' => $dup->id]);
                    }
                }
            } catch (\Throwable $e) {
                // bad timestamp -> just use "now"
            }
        }

        // The real work: identical rules (area check, edit only own message...) to the website.
        $response = $this->web()->sendMessage($request);
        $payload  = method_exists($response, 'getData') ? $response->getData(true) : [];

        if (!empty($payload['success']) && $editId === 0) {
            $row = DB::table('messages')
                ->where('from_user_id', $user->id)
                ->where('to_user_id', $toId)
                ->where('message', $message)
                ->orderByDesc('id')
                ->first();

            if ($row) {
                $payload['id'] = $row->id;
                if ($createdAt) {
                    DB::table('messages')->where('id', $row->id)->update([
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);
                }
            }
        }

        return response()->json($payload);
    }

    /** POST /chat/delete {id} -> deletes ONE of MY messages (same as the website). */
    public function destroy(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }
        $id = (int) $request->input('id');
        if ($id <= 0) {
            return response()->json(['success' => false, 'message' => 'Missing id.'], 422);
        }
        return $this->web()->deleteMessage($id);
    }

    /** POST /ai-chat {message, language} -> same Groq answer as the website's AI Assistant. */
    public function ai(Request $request)
    {
        if ($deny = $this->denyIfNotFarmer($request)) {
            return $deny;
        }

        $res  = app(FarmerChatController::class)->handleChat($request);
        $data = $res->getData(true);

        if ($res->getStatusCode() === 200 && !empty($data['response'])) {
            return response()->json(['success' => true, 'response' => $data['response']]);
        }

        return response()->json([
            'success' => false,
            'message' => $data['error'] ?? 'The AI Assistant is not available right now.',
        ], 502);
    }
}