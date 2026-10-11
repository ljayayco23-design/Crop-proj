<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Server-side ID photo check, same rules as the web register form
 * (quality -> face -> text, per ID type).
 *
 *  quality() : brightness / sharpness / size, done with PHP GD (no API needed)
 *  vision()  : face + text check by a vision AI (Groq). Optional: used when the
 *              phone could not check by itself (no ML on the phone / web build).
 *  judge()   : turns the vision answer into accept / reject + hints for the admin
 */
class IdPhotoChecker
{
    public const RULES = [
        'national_id'        => ['label' => 'National ID',            'face' => true],
        'voters_id'          => ['label' => "Voter's ID",             'face' => true],
        'barangay_clearance' => ['label' => 'Barangay ID / Clearance', 'face' => false],
        'farmer_id'          => ['label' => "RSBSA / Farmer's ID",    'face' => true],
        'other'              => ['label' => 'Other Valid ID',         'face' => true],
    ];

    // A little looser than the browser/phone (the photo was already resized and
    // re-compressed once), so a photo the phone accepted is not refused here.
    private const MIN_SIDE      = 400;
    private const MIN_SHARPNESS = 40;
    private const MIN_BRIGHT    = 45;
    private const MAX_BRIGHT    = 225;
    private const MAX_FACES     = 3;

    /** @return array{0:bool,1:string,2:array} [ok, message, meta] */
    public function quality(string $binary): array
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagescale')) {
            return [true, '', ['status' => 'unverified', 'note' => 'GD not installed']];
        }

        $img = @imagecreatefromstring($binary);
        if (!$img) {
            return [false, 'Hindi mabuksan ang larawan. Gumamit ng JPG, PNG o WEBP.', []];
        }
        if (function_exists('imagepalettetotruecolor')) {
            @imagepalettetotruecolor($img);
        }

        $w = imagesx($img);
        $h = imagesy($img);
        if (min($w, $h) < self::MIN_SIDE) {
            imagedestroy($img);
            return [false, 'Masyadong maliit ang larawan. Lumapit nang kaunti at kunan ulit.', []];
        }

        $k  = min(1, 640 / max($w, $h));
        $nw = max(3, (int) round($w * $k));
        $nh = max(3, (int) round($h * $k));
        $small = imagescale($img, $nw, $nh);
        imagedestroy($img);
        if (!$small) {
            return [true, '', ['status' => 'unverified']];
        }

        $gray = [];
        $sum  = 0.0;
        for ($y = 0; $y < $nh; $y++) {
            for ($x = 0; $x < $nw; $x++) {
                $c = imagecolorat($small, $x, $y);
                $v = 0.299 * (($c >> 16) & 255) + 0.587 * (($c >> 8) & 255) + 0.114 * ($c & 255);
                $gray[$y * $nw + $x] = $v;
                $sum += $v;
            }
        }
        imagedestroy($small);

        $bright = $sum / max(1, $nw * $nh);

        // Laplacian variance (lower = blurry)
        $s = 0.0; $sq = 0.0; $n = 0;
        for ($y = 1; $y < $nh - 1; $y++) {
            for ($x = 1; $x < $nw - 1; $x++) {
                $i = $y * $nw + $x;
                $l = 4 * $gray[$i] - $gray[$i - $nw] - $gray[$i + $nw] - $gray[$i - 1] - $gray[$i + 1];
                $s += $l; $sq += $l * $l; $n++;
            }
        }
        $mean  = $n ? $s / $n : 0;
        $sharp = $n ? $sq / $n - $mean * $mean : 0;

        $meta = ['status' => 'passed', 'sharpness' => (int) round($sharp), 'brightness' => (int) round($bright)];

        if ($bright < self::MIN_BRIGHT) {
            return [false, 'Masyadong madilim ang larawan. Humanap ng mas maliwanag na lugar.', $meta];
        }
        if ($bright > self::MAX_BRIGHT) {
            return [false, 'Masyadong maliwanag o may glare. Iwasan ang flash at direktang ilaw.', $meta];
        }
        if ($sharp < self::MIN_SHARPNESS) {
            return [false, 'Malabo ang larawan. Hawakan nang steady at kunan ulit.', $meta];
        }

        return [true, '', $meta];
    }

    /**
     * Asks a vision AI (Groq) about the photo. Returns null when no key is set
     * or the call fails -> the caller marks the photo "unverified" instead of
     * blocking the farmer.
     */
    public function vision(string $dataUri, string $idType, string $fullName): ?array
    {
        $key = env('GROQ_API_KEY');
        if (empty($key)) {
            return null;
        }
        $model = env('GROQ_VISION_MODEL', 'meta-llama/llama-4-scout-17b-16e-instruct');
        $label = self::RULES[$idType]['label'] ?? 'ID';

        $prompt = "A farmer in the Philippines uploaded this photo as their ID. Selected ID type: {$label}. "
            . 'Typed name: "' . mb_substr($fullName, 0, 100) . '". '
            . 'Answer ONLY with one JSON object, no other text: '
            . '{"is_id_document":true|false,"face_count":number,"face_is_selfie_closeup":true|false,'
            . '"readable_text":true|false,"matches_selected_type":true|false,"name_found":true|false}. '
            . 'is_id_document = the photo shows a real ID card or document. '
            . 'face_is_selfie_closeup = the photo is mostly a face (a selfie), not a whole ID card.';

        try {
            $res = Http::withToken($key)->timeout(25)->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => $model,
                'temperature' => 0,
                'max_tokens'  => 200,
                'messages'    => [[
                    'role'    => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
                    ],
                ]],
            ]);
            if (!$res->ok()) {
                Log::warning('ID vision check HTTP ' . $res->status());
                return null;
            }
            $text = (string) data_get($res->json(), 'choices.0.message.content', '');
            if (!preg_match('/\{.*\}/s', $text, $m)) {
                return null;
            }
            $j = json_decode($m[0], true);
            return is_array($j) ? $j : null;
        } catch (\Throwable $e) {
            Log::warning('ID vision check failed: ' . $e->getMessage());
            return null;
        }
    }

    /** @return array{0:bool,1:string,2:array} [ok, message, meta] */
    public function judge(array $v, string $idType): array
    {
        $rule  = self::RULES[$idType] ?? self::RULES['other'];
        $faces = (int) ($v['face_count'] ?? 0);

        $meta = [
            'status'             => 'passed',
            'faces'              => $faces,
            'type_keyword_match' => (bool) ($v['matches_selected_type'] ?? false),
            'name_match'         => !empty($v['name_found']) ? 1 : 0,
            'checked_by'         => 'server-ai',
        ];

        if (array_key_exists('is_id_document', $v) && !$v['is_id_document']) {
            return [false, 'Hindi mukhang ID ang larawan. Kunan ang buong ID card.', $meta];
        }
        if ($rule['face'] && $faces === 0) {
            return [false, 'Walang nakitang mukha sa ID. Kunan ang harap ng ID kung nasaan ang larawan.', $meta];
        }
        if ($faces > self::MAX_FACES) {
            return [false, 'Maraming mukha ang nakita. Isang ID lang ang dapat nasa larawan.', $meta];
        }
        if (!empty($v['face_is_selfie_closeup'])) {
            return [false, 'Mukhang selfie o close-up ng mukha ito, hindi ID. Kunan ang buong ID card.', $meta];
        }
        if (array_key_exists('readable_text', $v) && !$v['readable_text']) {
            return [false, 'Walang mabasang text. Siguraduhing kita ang buong ID at malinaw ang sulat.', $meta];
        }

        // Same as the web form: the photo must look like the ID type the farmer selected.
        if ($idType !== 'other'
            && array_key_exists('matches_selected_type', $v)
            && $v['matches_selected_type'] === false) {
            return [false, "Hindi mukhang {$rule['label']} ang larawan. Piliin ang tamang uri ng ID o kunan ulit.", $meta];
        }

        // The typed name is only a note for the admin: an AI guess is not a
        // good enough reason to refuse a farmer.
        return [true, '', $meta];
    }
}