<?php
/**
 * content_moderation.php
 * Reusable moderation functions for Connectify.
 *
 * Sign up free at: https://sightengine.com  (1000 free API calls/month)
 * Then set your API credentials below.
 *
 * Usage:
 *   require_once 'content_moderation.php';
 *
 *   // Check text
 *   $result = moderateText("some comment text");
 *   if (!$result['ok']) die($result['reason']);
 *
 *   // Check image/video file path
 *   $result = moderateFile('/path/to/uploaded/file.jpg', 'image');
 *   if (!$result['ok']) die($result['reason']);
 */

/* ── Sightengine credentials ─────────────────────────────────── */
define('SIGHT_USER',   'YOUR_SIGHTENGINE_API_USER');   // ← replace
define('SIGHT_SECRET', 'YOUR_SIGHTENGINE_API_SECRET'); // ← replace

/* ── Text moderation word list (runs locally, zero API cost) ─── */
// Add or remove words as you see fit.
// Keep this list in a separate private file if you prefer.
define('BAD_WORDS', [
    // English profanity
    'fuck','fucking','fucker','shit','shitting','asshole','ass','bitch',
    'bastard','cunt','pussy','dick','cock','penis','vagina','boobs',
    'tits','porn','porno','pornography','sex','sexy','nude','naked',
    'rape','rapist','nigger','nigga','faggot','fag','whore','slut',
    'motherfucker','mf','wtf','kys','killself','kill yourself',
    // Hindi/transliterated (common)
    'chutiya','madarchod','bhenchod','bhosdike','gandu','randi',
    'harami','kamina','saala','lund','gaand','chut','bkl','mc','bc',

    // Bengali / transliterated Bengali (common abuses)
    'magi','maagi','shala','shaalaa','sala','haramzada','haramjada',
    'khanki','khanki magi','banchod','banchot','baan','baan er bacha',
    'kuttar bacha','kuttar chele','kukur','kukurer bacha',
    'boro lojja','pagol','pagla','chagol','gadha','gadha','gadhaa',
    'tor maa','tor baap','bokachoda','boka choda','choda','chodna',
    'cudda','cuddano','cudano','chodai','khanki','khankir chele',
    'khankir bacha','maal','randibaji','randi baji','beshya',
    'beshsha','beshyaaa','khota','dhon','dhan','gudh','gud',
    'gu','gu khaa','gu kha','hencho','hechko','metho',

    // Bengali Unicode script (blocks the actual script too)
    'মাগি','শালা','হারামজাদা','খানকি','বাঞ্চোদ','কুকুর',
    'গাধা','বোকাচোদা','চোদা','চুদি','রান্ডি','বেশ্যা',
    'ধোন','গুদ','গু','পাগল','ছাগল','কুত্তার বাচ্চা',
    'খানকির ছেলে','মাদারচোদ','ভেঙ্কু','হারামি',

    // Marathi / transliterated
    'zavanya','zavtoy','zavli','jhavto','jhavnya',
    'gaandichya','aaicha ghav','madarchya','aai zav',
    'boshivat','bhikarchya','naktu','napunsak',

    // Marathi Unicode
    'झवण्या','गांडीच्या','आईचा घाव','बोशीवाट',

    // Tamil / transliterated
    'ootha','otha','thevidiya','thevdiya',
    'punda','pundamavane','sunni','koothi',
    'poolai','sootha','kena','baadu',

    // Tamil Unicode
    'ஊத','தேவடியா','புண்ட','சுன்னி','கூதி',

    // Punjabi / transliterated
    'bhain di','teri maa','kutti','kutta',
    'teri pen di','lavde','lann','phuddi',
    'maa di aankh','bhen di','randi',

    // Punjabi Unicode (Gurmukhi script)
    'ਭੈਣ ਦੀ','ਕੁੱਤੀ','ਲੰਡ','ਫੁੱਦੀ','ਰੰਡੀ',

    // Gujarati / transliterated
    'bhosdi','bhadvo','randi','gaand',
    'madar chod','bhenchod','lavaro',

    // Gujarati Unicode
    'ભોસડી','ભડવો','રાંડ','ગાંડ',

    // Urdu / transliterated  
    'harami','kameena','kanjri','maa ki aankh',
    'bhen chod','gaandu','randi','suar',

    // Urdu Unicode
    'حرامی','کمینہ','کنجری','گاندو','رنڈی',
]);

/* ═══════════════════════════════════════════════════════════════
   moderateText()
   Checks a string against the bad-word list.
   Returns: ['ok'=>true] or ['ok'=>false, 'reason'=>'...']
═══════════════════════════════════════════════════════════════ */
function moderateText(string $text): array
{
    $lower = mb_strtolower($text, 'UTF-8');

    // Strip common leet-speak substitutions so "sh1t" still matches
    $normalized = strtr($lower, [
        '0'=>'o','1'=>'i','3'=>'e','4'=>'a','5'=>'s','@'=>'a','$'=>'s',
    ]);

    foreach (BAD_WORDS as $word) {
        $isAscii = mb_detect_encoding($word, 'ASCII', true);
        if ($isAscii) {
            // ASCII word: check normalized (handles leet-speak), use word boundary
            $pattern = '/\b' . preg_quote($word, '/') . '\b/iu';
            $haystack = $normalized;
        } else {
            // Unicode/Bengali: check original lowercase (normalization strips nothing),
            // no word boundary needed — these are distinct Bengali words
            $pattern  = '/' . preg_quote($word, '/') . '/u';
            $haystack = $lower;
        }

        if (preg_match($pattern, $haystack)) {
            return [
                'ok'     => false,
                'reason' => 'Your message contains inappropriate language and cannot be posted.',
            ];
        }
    }

    return ['ok' => true];
}

/* ═══════════════════════════════════════════════════════════════
   moderateFile()
   Sends an image or video to Sightengine for AI moderation.

   $filePath = absolute server path to the uploaded file
   $type     = 'image' | 'video'

   Returns: ['ok'=>true] or ['ok'=>false, 'reason'=>'...']
═══════════════════════════════════════════════════════════════ */
function moderateFile(string $filePath, string $type = 'image'): array
{
    // Skip API call if credentials not set yet
    if (SIGHT_USER === 'YOUR_SIGHTENGINE_API_USER') {
        return ['ok' => true, 'note' => 'Sightengine not configured — skipping media check'];
    }

    if (!file_exists($filePath)) {
        return ['ok' => false, 'reason' => 'File not found for moderation check.'];
    }

    if ($type === 'image') {
        return _moderateImage($filePath);
    } elseif ($type === 'video') {
        return _moderateVideo($filePath);
    }

    return ['ok' => true];
}

/* ── Internal: image moderation ── */
function _moderateImage(string $filePath): array
{
    $postFields = [
        'media'   => new CURLFile($filePath),
        'models'  => 'nudity,offensive,violence,gore',
        'api_user'=> SIGHT_USER,
        'api_secret' => SIGHT_SECRET,
    ];

    $ch = curl_init('https://api.sightengine.com/1.0/check.json');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) return ['ok' => false, 'reason' => 'Moderation service unreachable.'];

    $data = json_decode($raw, true);
    if (!$data || ($data['status'] ?? '') !== 'success') {
        return ['ok' => false, 'reason' => 'Could not verify image safety.'];
    }

    return _evaluateSightengineResult($data);
}

/* ── Internal: video moderation (async — Sightengine queues it) ── */
function _moderateVideo(string $filePath): array
{
    // For video, Sightengine processes frames asynchronously.
    // Here we do a synchronous check on the first frame via the sync endpoint.
    // For full video moderation you'd use their webhook — this covers the
    // most common case (explicit thumbnail / first scene).
    $postFields = [
        'media'      => new CURLFile($filePath),
        'models'     => 'nudity,offensive,violence',
        'api_user'   => SIGHT_USER,
        'api_secret' => SIGHT_SECRET,
    ];

    $ch = curl_init('https://api.sightengine.com/1.0/video/check-sync.json');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $postFields,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) return ['ok' => false, 'reason' => 'Video moderation service unreachable.'];

    $data = json_decode($raw, true);
    if (!$data || ($data['status'] ?? '') !== 'success') {
        return ['ok' => false, 'reason' => 'Could not verify video safety.'];
    }

    // Check frames summary
    $frames = $data['data']['frames'] ?? [];
    foreach ($frames as $frame) {
        $check = _evaluateSightengineResult($frame);
        if (!$check['ok']) return $check;
    }

    return ['ok' => true];
}

/* ── Evaluate Sightengine scores ── */
function _evaluateSightengineResult(array $data): array
{
    $THRESHOLD = 0.5; // block if any score >= 50%

    // Nudity
    $nudity = $data['nudity'] ?? [];
    $nudityScore = max(
        (float)($nudity['raw']      ?? 0),
        (float)($nudity['partial']  ?? 0),
        (float)($nudity['safe']     ?? 0) < 0.5 ? 0.6 : 0  // if not safe, flag it
    );
    // Simpler: just check raw + partial
    $nudityMax = max((float)($nudity['raw'] ?? 0), (float)($nudity['partial'] ?? 0));
    if ($nudityMax >= $THRESHOLD) {
        return ['ok' => false, 'reason' => 'This image contains nudity and cannot be posted.'];
    }

    // Offensive / hate symbols
    $offensive = $data['offensive'] ?? [];
    if ((float)($offensive['prob'] ?? 0) >= $THRESHOLD) {
        return ['ok' => false, 'reason' => 'This image contains offensive content and cannot be posted.'];
    }

    // Violence / gore
    $violence = (float)($data['violence']['prob'] ?? 0);
    if ($violence >= $THRESHOLD) {
        return ['ok' => false, 'reason' => 'This image contains violent content and cannot be posted.'];
    }

    $gore = (float)($data['gore']['prob'] ?? 0);
    if ($gore >= $THRESHOLD) {
        return ['ok' => false, 'reason' => 'This image contains graphic content and cannot be posted.'];
    }

    return ['ok' => true];
}