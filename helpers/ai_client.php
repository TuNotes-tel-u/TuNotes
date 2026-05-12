<?php
/**
 * Helper untuk panggil AI provider (Gemini).
 *
 *   $result = call_ai($prompt, [
 *       'mode' => 'auto'|'research'|'all_sources',
 *       'note_context' => string|null,    // hanya untuk mock mode
 *       'temperature' => float,
 *       'max_tokens' => int,
 *   ]);
 *   // $result = ['text' => string, 'tokens_used' => int, 'provider' => 'gemini'|'mock'|'error', 'error' => ?string]
 *
 * Kalau api_key kosong di config → fallback ke mock_ai_response() (no network).
 * Kalau curl gagal / Gemini balas 4xx-5xx → return text error yang aman (tidak
 * bocor API key atau detail internal ke client).
 */

require_once __DIR__ . '/../config/constants.php';

if (!function_exists('load_ai_config')) {
    function load_ai_config(): array
    {
        static $cfg = null;
        if ($cfg === null) {
            $path = __DIR__ . '/../config/ai_config.php';
            $cfg = is_file($path) ? (require $path) : [];
        }
        return $cfg;
    }
}

if (!function_exists('call_ai')) {
    function call_ai(string $prompt, array $opts = []): array
    {
        $cfg     = load_ai_config();
        $api_key = $cfg['api_key'] ?? '';

        // Tidak ada key → mock mode.
        if ($api_key === '') {
            return [
                'text'        => mock_ai_response($prompt, $opts),
                'tokens_used' => 0,
                'provider'    => 'mock',
            ];
        }

        $model       = $opts['model']       ?? ($cfg['model']       ?? 'gemini-1.5-flash');
        $temperature = $opts['temperature'] ?? ($cfg['temperature'] ?? 0.7);
        $max_tokens  = $opts['max_tokens']  ?? ($cfg['max_tokens']  ?? 1024);

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . rawurlencode($model) . ':generateContent?key=' . urlencode($api_key);

        $body = [
            'contents' => [[
                'parts' => [['text' => $prompt]],
            ]],
            'generationConfig' => [
                'temperature'     => (float) $temperature,
                'maxOutputTokens' => (int)   $max_tokens,
            ],
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        $resp = curl_exec($ch);
        $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            // Log error tapi JANGAN log api_key. $err sudah dari curl, aman.
            error_log('[TuNotes AI] curl error: ' . $err);
            return [
                'text'        => 'Maaf, koneksi ke AI provider gagal. Coba lagi nanti.',
                'tokens_used' => 0,
                'provider'    => 'gemini',
                'error'       => 'NETWORK',
            ];
        }

        if ($http >= 400) {
            // Log respons body dengan truncate; tetap tidak boleh bocor api_key.
            // (api_key cuma di URL query string yang gak kita log.)
            error_log('[TuNotes AI] Gemini HTTP ' . $http . ': ' . substr((string) $resp, 0, 500));
            return [
                'text'        => 'AI provider menolak permintaan (HTTP ' . $http . '). Periksa konfigurasi API key atau coba lagi.',
                'tokens_used' => 0,
                'provider'    => 'gemini',
                'error'       => 'PROVIDER_HTTP_' . $http,
            ];
        }

        $data = json_decode((string) $resp, true);
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        if ($text === '') {
            // Bisa karena safety filter atau response kosong.
            $reason = $data['candidates'][0]['finishReason'] ?? '';
            return [
                'text'        => 'Respons AI kosong' . ($reason ? ' (alasan: ' . $reason . ')' : '') . '. Coba ubah prompt.',
                'tokens_used' => 0,
                'provider'    => 'gemini',
                'error'       => 'EMPTY',
            ];
        }

        $tokens = (int) ($data['usageMetadata']['totalTokenCount'] ?? 0);
        return [
            'text'        => $text,
            'tokens_used' => $tokens,
            'provider'    => 'gemini',
        ];
    }
}

if (!function_exists('mock_ai_response')) {
    function mock_ai_response(string $prompt, array $opts = []): string
    {
        $short = trim(mb_substr($prompt, 0, 200));
        $mode  = $opts['mode']         ?? 'auto';
        $ctx   = $opts['note_context'] ?? null;

        $out  = "Halo! Saya **TuNotes AI**, asisten belajar kamu.\n\n";
        $out .= "**Mode aktif:** `$mode`\n\n";
        if ($ctx) {
            $out .= "**Konteks catatan yang dirujuk:**\n> " . mb_substr($ctx, 0, 400) . "\n\n";
        }
        $out .= "**Tanggapan untuk prompt:**\n> " . $short . "\n\n";
        $out .= "**Saran:**\n";

        $lp = mb_strtolower($prompt);
        $hasAny = function ($keywords) use ($lp) {
            foreach ($keywords as $k) {
                if (strpos($lp, $k) !== false) return true;
            }
            return false;
        };

        if ($hasAny(['agenda', 'jadwal', 'schedule', 'planner'])) {
            $out .= "- Pakai blok belajar 90 menit per sesi (fokus murni, tanpa hp)\n";
            $out .= "- Rotasi mata kuliah tiap hari untuk variasi otak\n";
            $out .= "- Sisakan 1 hari (mis. Sabtu) untuk review mingguan\n";
            $out .= "- Tambah buffer 30 menit antara sesi untuk transisi\n";
        } else if ($hasAny(['analisis', 'pdf', 'ringkas', 'summary', 'document'])) {
            $out .= "- Identifikasi tesis utama di paragraf pembuka\n";
            $out .= "- Cari kata kunci di tiap subjudul untuk peta mental\n";
            $out .= "- Catat 3 argumen pendukung + 1 counter-argument\n";
            $out .= "- Tulis ringkasan maksimal 5 poin singkat\n";
        } else if ($hasAny(['tugas', 'deadline', 'tracker', 'todo', 'task'])) {
            $out .= "- Kolom wajib: judul · deadline · prioritas (P1/P2/P3) · status · mata kuliah\n";
            $out .= "- Plan weekly setiap Senin pagi, review daily 5 menit\n";
            $out .= "- Sisakan buffer 1 hari sebelum deadline\n";
            $out .= "- Pakai notasi P1 (hari ini), P2 (minggu ini), P3 (kapan saja)\n";
        } else if ($hasAny(['rekomendasi', 'sarankan', 'pilih', 'prioritas'])) {
            $out .= "- Prioritaskan catatan yang minggu ini ada deadline tugas terkait\n";
            $out .= "- Pilih topik fundamental sebelum advanced\n";
            $out .= "- 1 catatan fokus minggu ini lebih efektif daripada 5 catatan dangkal\n";
        } else {
            $out .= "- Pecah prompt jadi pertanyaan-pertanyaan kecil yang spesifik\n";
            $out .= "- Sebutkan konteks: mata kuliah, semester, topik, tingkat kesulitan\n";
            $out .= "- Pakai mode 'Penelitian' kalau butuh referensi/sumber\n";
            $out .= "- Pakai mode 'Semua sumber' untuk dapat insight dari catatan publik\n";
        }

        $out .= "\n_(Ini respons **MOCK**. Set GEMINI_API_KEY di environment atau `config/ai_config.php` untuk respons AI sungguhan.)_";
        return $out;
    }
}
