<?php

namespace App\Http\Controllers;

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
        // (Or just return view('live_com'); if it's placed directly in the views folder)
        return view('technician.live_com'); 
    }

    // ==================== API METHODS ====================

    // Replaces: get_users_for_chat.php
    public function getUsers()
    {
        $users = DB::table('users')
            ->where('status', 'approved')
            ->where('id', '!=', Auth::id())
            ->where('role', '!=', 'admin')
            ->select('id', 'full_name', 'role')
            ->get();

        return response()->json($users);
    }

    // Replaces: get_messages.php AND get_group_messages.php
    public function getMessages(Request $request)
    {
        $from_id = Auth::id();
        $to_id = (int)$request->query('to_user', 0);

        $query = DB::table('messages')
            ->leftJoin('users', 'messages.from_user_id', '=', 'users.id')
            ->select('messages.id', 'messages.from_user_id', 'messages.message', 'messages.created_at', 'users.full_name as sender_name');

        if ($to_id === 0) {
            // Group Chat
            $query->where('messages.to_user_id', 0);
        } else {
            // Private Chat
            $query->where(function($q) use ($from_id, $to_id) {
                $q->where('messages.from_user_id', $from_id)->where('messages.to_user_id', $to_id);
            })->orWhere(function($q) use ($from_id, $to_id) {
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
        $to_id = (int)$request->input('to_user', 0);
        $is_group = $request->input('is_group') == '1';
        $edit_id = (int)$request->input('edit_id', 0);

        if ($message) {
            if ($edit_id > 0) {
                // Edit
                DB::table('messages')
                    ->where('id', $edit_id)
                    ->where('from_user_id', $from_id)
                    ->update(['message' => $message]);
            } else if ($is_group) {
                // Group message
                DB::table('messages')->insert([
                    'from_user_id' => $from_id,
                    'to_user_id' => 0,
                    'message' => $message,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } else {
                // Private message
                DB::table('messages')->insert([
                    'from_user_id' => $from_id,
                    'to_user_id' => $to_id,
                    'message' => $message,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
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