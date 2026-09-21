<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    // ==================== VIEWS ====================

    public function farmerIndex()
    {
        // Points to resources/views/farmer/live_com.blade.php
        return view('farmer.live_com');
    }

    public function technicianIndex()
    {
        // Points to resources/views/technician/live_com.blade.php
        return view('technician.live_com');
    }

    // ==================== API METHODS ====================

    /**
     * The messenger's "who can I see/talk to" — mirrors the exact same
     * area rules AdminUserController already enforces for the User
     * Accounts pages, so a technician's chat list lines up with what
     * they're actually assigned to manage:
     *
     *   - developer  -> everyone (super admin, unrestricted)
     *   - admin      -> technicians + farmers in the admin's own
     *                   province + city
     *   - technician -> other technicians in the same province + city,
     *                   PLUS farmers in whichever barangay(s) an
     *                   admin/developer has actively assigned them to
     *                   via Assignment Management (never their own
     *                   registration province/city — same rule as
     *                   AdminUserController@userLog)
     *   - farmer     -> the technician(s) actively assigned to the
     *                   farmer's own barangay, plus fellow farmers in
     *                   that same barangay
     *
     * Admin accounts never appear in the messenger for anyone, same as
     * the original behavior.
     */
    private function visibleUsersQuery()
    {
        $actor = Auth::user();
        $actorRole = $actor->role;

        $base = fn () => DB::table('users')
            ->where('status', 'approved')
            ->where('id', '!=', $actor->id);

        if ($actorRole === 'developer') {
            return $base()->where('role', '!=', 'admin');
        }

        if ($actorRole === 'admin') {
            return $base()
                ->whereIn('role', ['technician', 'farmer'])
                ->where('province_id', $actor->province_id)
                ->where('city_id', $actor->city_id);
        }

        if ($actorRole === 'technician') {
            $assignedBarangayIds = Assignment::active()
                ->where('user_id', $actor->id)
                ->pluck('barangay_id');

            return $base()->where(function ($q) use ($actor, $assignedBarangayIds) {
                $q->where(function ($qq) use ($actor) {
                    $qq->where('role', 'technician')
                       ->where('province_id', $actor->province_id)
                       ->where('city_id', $actor->city_id);
                })->orWhere(function ($qq) use ($assignedBarangayIds) {
                    $qq->where('role', 'farmer')
                       ->whereIn('barangay_id', $assignedBarangayIds);
                });
            });
        }

        if ($actorRole === 'farmer') {
            $assignedTechnicianIds = Assignment::active()
                ->where('barangay_id', $actor->barangay_id)
                ->pluck('user_id');

            return $base()->where(function ($q) use ($actor, $assignedTechnicianIds) {
                $q->where(function ($qq) use ($assignedTechnicianIds) {
                    $qq->where('role', 'technician')
                       ->whereIn('id', $assignedTechnicianIds);
                })->orWhere(function ($qq) use ($actor) {
                    $qq->where('role', 'farmer')
                       ->where('barangay_id', $actor->barangay_id);
                });
            });
        }

        // Unknown/unhandled role — show nobody rather than leak everyone.
        return $base()->whereRaw('1 = 0');
    }

    // Replaces: get_users_for_chat.php
    public function getUsers()
    {
        $users = $this->visibleUsersQuery()
            ->select('id', 'full_name', 'role')
            ->orderBy('full_name')
            ->get();

        return response()->json($users);
    }

    // Replaces: get_messages.php AND get_group_messages.php
    public function getMessages(Request $request)
    {
        $from_id = Auth::id();
        $to_id = (int) $request->query('to_user', 0);

        $query = DB::table('messages')
            ->leftJoin('users', 'messages.from_user_id', '=', 'users.id')
            ->select('messages.id', 'messages.from_user_id', 'messages.message', 'messages.created_at', 'users.full_name as sender_name');

        if ($to_id === 0) {
            // Group Chat — intentionally NOT area-scoped; it's the one
            // shared space everyone (minus admins) can post in.
            $query->where('messages.to_user_id', 0);
        } else {
            // Defense in depth: even though the sidebar only ever renders
            // in-area users, block reading a private thread with someone
            // outside the actor's area if the id is guessed/crafted.
            $allowed = $this->visibleUsersQuery()->where('id', $to_id)->exists();
            if (!$allowed) {
                abort(404);
            }

            $query->where(function ($q) use ($from_id, $to_id) {
                $q->where('messages.from_user_id', $from_id)->where('messages.to_user_id', $to_id);
            })->orWhere(function ($q) use ($from_id, $to_id) {
                $q->where('messages.from_user_id', $to_id)->where('messages.to_user_id', $from_id);
            });
        }

        $messages = $query->orderBy('messages.created_at', 'asc')->get();
        return response()->json($messages);
    }

    // Replaces: send_message.php
    public function sendMessage(Request $request)
    {
        $from_id = Auth::id();
        $message = trim($request->input('message', ''));
        $to_id = (int) $request->input('to_user', 0);
        $is_group = $request->input('is_group') == '1';
        $edit_id = (int) $request->input('edit_id', 0);

        if (!$message) {
            return response()->json(['success' => true]);
        }

        if ($edit_id > 0) {
            // Edit — already scoped to the author via from_user_id.
            DB::table('messages')
                ->where('id', $edit_id)
                ->where('from_user_id', $from_id)
                ->update(['message' => $message]);
        } elseif ($is_group) {
            DB::table('messages')->insert([
                'from_user_id' => $from_id,
                'to_user_id' => 0,
                'message' => $message,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            // Defense in depth: block sending a DM to someone outside
            // the actor's area, same reasoning as getMessages() above.
            $allowed = $this->visibleUsersQuery()->where('id', $to_id)->exists();
            if (!$allowed) {
                abort(403, 'You are not allowed to message this user.');
            }

            DB::table('messages')->insert([
                'from_user_id' => $from_id,
                'to_user_id' => $to_id,
                'message' => $message,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json(['success' => true]);
    }

    // Replaces: delete_message.php
    public function deleteMessage($id)
    {
        DB::table('messages')
            ->where('id', $id)
            ->where('from_user_id', Auth::id())
            ->delete();

        return response()->json(['success' => true]);
    }
}