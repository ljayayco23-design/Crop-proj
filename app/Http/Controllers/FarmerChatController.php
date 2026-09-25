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

            // Calls Groq API using the same environment key that works in your index page.
            //
            // NOTE: 'llama-3.3-70b-versatile' was decommissioned by Groq on
            // 2026-08-16 (see https://console.groq.com/docs/deprecations) —
            // every request using it now gets rejected with a 400
            // model_decommissioned error, which is exactly the
            // "Groq API rejected the request" failure this endpoint was
            // throwing. Groq's own deprecation notice recommends
            // 'openai/gpt-oss-120b' as the replacement, so that's what
            // this plain-text assistant now uses (kept separate from the
            // vision model 'qwen/qwen3.8-27b' used by the image-detection
            // engine in FarmerHistoryController — this chat never sends
            // an image, so it doesn't need a vision-capable model).
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('GROQ_API_KEY'),
                'Content-Type' => 'application/json',
            ])->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'openai/gpt-oss-120b',
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