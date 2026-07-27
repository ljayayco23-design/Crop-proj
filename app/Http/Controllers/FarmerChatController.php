<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class FarmerChatController extends Controller
{
    public function handleChat(Request $request)
    {
        try {
            $request->validate([
                'message' => 'required|string',
                'language' => 'nullable|string'
            ]);

            $userMessage = $request->input('message');
            $language = $request->input('language', 'tagalog');

            $systemPrompt = "You are an expert AI assistant for rice farming. Answer clearly and concisely in {$language}.";

            // Calls Groq API using the same environment key that works in your index page
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama-3.3-70b-versatile',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userMessage]
                ],
                'temperature' => 0.7,
            ]);

            if ($response->successful()) {
                $reply = $response->json('choices.0.message.content');
                return response()->json(['response' => $reply]);
            }

            return response()->json([
                'error' => 'Groq API rejected the request.',
                'details' => $response->json()
            ], 500);

        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }
}