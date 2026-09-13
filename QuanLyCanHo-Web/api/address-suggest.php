<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');

$q = trim((string)($_GET['q'] ?? ''));

// Normalize text for fuzzy matching (remove Vietnamese tones)
if (!function_exists('removeVietnameseTones')) {
    function removeVietnameseTones(string $str): string {
        $str = mb_strtolower($str, 'UTF-8');
        $accents = [
            'a' => ['à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ'],
            'e' => ['è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ'],
            'i' => ['ì','í','ị','ỉ','ĩ'],
            'o' => ['ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ'],
            'u' => ['ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ'],
            'y' => ['ỳ','ý','ỵ','ỷ','ỹ'],
            'd' => ['đ']
        ];
        foreach ($accents as $non => $with) {
            $str = str_replace($with, $non, $str);
        }
        return preg_replace('/[^a-z0-9\s]/', '', $str);
    }
}

// Check for Google Maps API Key in config/.env
$envFile = __DIR__ . '/../config/.env';
$googleApiKey = '';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines !== false) {
        foreach ($lines as $line) {
            if (str_starts_with(trim($line), 'GOOGLE_MAPS_API_KEY=')) {
                $googleApiKey = trim(substr(trim($line), 20));
                break;
            }
        }
    }
}

$results = [];

// Parse prefix (Thôn, Ấp, Bản, Buôn, Khóm, Tổ, Hẻm, Số nhà...)
$prefix = '';
$searchBody = $q;
if (preg_match('/^((?:thôn|ấp|bản|buôn|khóm|tổ|số|hẻm|ngõ)\s*[\d\w\/\-\.]+|[\d\/\-\.]+(?:[a-zA-Z](?=\s|,|$))?)\s*[,|\-]?\s+(.*)$/ui', $q, $m)) {
    $prefix = trim($m[1]);
    $rest = trim($m[2]);
    if ($rest !== '') {
        $searchBody = $rest;
    }
}

$hasAdminKeyword = (bool)preg_match('/\b(thôn|ấp|xã|phường|thị\s+trấn|huyện|quận|thị\s+xã|tỉnh|thành\s+phố|tp)\b/ui', $q);

// 1. Official Vietnam Administrative Units (10,747 Communes, Wards, Districts & Provinces)
$divFile = __DIR__ . '/data/vietnam_divisions.json';
$adminMatches = [];
if (file_exists($divFile) && mb_strlen($searchBody) >= 2) {
    $divisions = json_decode(file_get_contents($divFile), true);
    if (is_array($divisions)) {
        $cleanQuery = removeVietnameseTones($searchBody);
        $words = array_filter(explode(' ', $cleanQuery));
        if (!empty($words)) {
            foreach ($divisions as $item) {
                // item[3] is pre-normalized string
                $norm = $item[3] ?? '';
                $matched = true;
                foreach ($words as $w) {
                    if (!str_contains($norm, $w)) {
                        $matched = false;
                        break;
                    }
                }
                if ($matched) {
                    $main = ($prefix !== '') ? ($prefix . ', ' . $item[0]) : $item[0];
                    $sec = $item[1] . ', Việt Nam';
                    $full = ($prefix !== '') ? ($prefix . ', ' . $item[2] . ', Việt Nam') : ($item[2] . ', Việt Nam');

                    $adminMatches[] = [
                        'main_text' => $main,
                        'secondary_text' => $sec,
                        'full_address' => $full,
                        'source' => 'vietnam_official'
                    ];
                    if (count($adminMatches) >= 6) {
                        break;
                    }
                }
            }
        }
    }
}

// 2. Google Places API (if API Key provided and strictly Vietnam)
$googleMatches = [];
if (!empty($googleApiKey) && mb_strlen($q) >= 2) {
    $url = 'https://maps.googleapis.com/maps/api/place/autocomplete/json?' . http_build_query([
        'input' => $q,
        'components' => 'country:vn',
        'language' => 'vi',
        'key' => $googleApiKey
    ]);
    $ctx = stream_context_create(['http' => ['timeout' => 2]]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp) {
        $json = json_decode($resp, true);
        if (!empty($json['predictions'])) {
            foreach ($json['predictions'] as $pred) {
                $googleMatches[] = [
                    'main_text' => $pred['structured_formatting']['main_text'] ?? $pred['description'],
                    'secondary_text' => $pred['structured_formatting']['secondary_text'] ?? 'Việt Nam',
                    'full_address' => $pred['description'] ?? '',
                    'source' => 'google'
                ];
            }
        }
    }
}

// 3. Photon Geocoding (OpenStreetMap street & alley level, strictly Vietnam)
$photonMatches = [];
if (mb_strlen($q) >= 2) {
    $photonUrl = 'https://photon.komoot.io/api/?' . http_build_query([
        'q' => $q,
        'lat' => 14.0583,
        'lon' => 108.2772,
        'limit' => 8
    ]);
    $ctx = stream_context_create([
        'http' => [
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) QuanLyCanHo/2.0\r\n",
            'timeout' => 2,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    $resp = @file_get_contents($photonUrl, false, $ctx);
    if ($resp) {
        $json = json_decode($resp, true);
        if (!empty($json['features'])) {
            foreach ($json['features'] as $f) {
                $p = $f['properties'] ?? [];
                
                // STRICT CHECK: Discard any result that is NOT in Vietnam
                $countryCode = strtoupper(trim((string)($p['countrycode'] ?? '')));
                $countryName = mb_strtolower(trim((string)($p['country'] ?? '')));
                if ($countryCode !== 'VN' && $countryName !== 'việt nam' && $countryName !== 'vietnam') {
                    continue;
                }

                $name = $p['name'] ?? '';
                $street = $p['street'] ?? '';
                $district = $p['district'] ?? $p['county'] ?? '';
                $city = $p['city'] ?? '';
                $state = $p['state'] ?? '';

                $main = $name !== '' ? $name : $street;
                if ($main === '') {
                    $main = $city !== '' ? $city : $state;
                }

                $secParts = [];
                if ($street !== '' && $street !== $main) $secParts[] = $street;
                if ($district !== '' && $district !== $main) $secParts[] = $district;
                if ($city !== '' && $city !== $main) $secParts[] = $city;
                if ($state !== '' && $state !== $main && $state !== $city) $secParts[] = $state;
                $secParts[] = 'Việt Nam';

                $sec = implode(', ', array_unique(array_filter($secParts)));
                $full = $main . ($sec !== '' ? ', ' . $sec : '');

                $photonMatches[] = [
                    'main_text' => $main,
                    'secondary_text' => $sec,
                    'full_address' => $full,
                    'source' => 'map'
                ];
            }
        }
    }
}

// 4. Merge results intelligently based on user query intent
$orderedSources = [];
if (!empty($googleMatches)) {
    $orderedSources = array_merge($orderedSources, $googleMatches);
}

if ($hasAdminKeyword) {
    // User specifically searched for Thôn, Xã, Phường, Huyện -> Prioritize official administrative units
    $orderedSources = array_merge($orderedSources, $adminMatches, $photonMatches);
} else {
    // User typed a street, alley, building, or general query -> Prioritize street-level results
    $orderedSources = array_merge($orderedSources, $photonMatches, $adminMatches);
}

// Filter duplicates
foreach ($orderedSources as $item) {
    $fullNorm = mb_strtolower(trim($item['full_address']));
    $exists = false;
    foreach ($results as $r) {
        if (mb_strtolower(trim($r['full_address'])) === $fullNorm) {
            $exists = true;
            break;
        }
    }
    if (!$exists) {
        $results[] = $item;
    }
    if (count($results) >= 7) {
        break;
    }
}

// 5. Default quick suggestions if query is empty
if (empty($results)) {
    if ($q === '') {
        $results = [
            [
                'main_text' => 'Quận 1',
                'secondary_text' => 'Thành phố Hồ Chí Minh, Việt Nam',
                'full_address' => 'Quận 1, Thành phố Hồ Chí Minh, Việt Nam',
                'source' => 'popular'
            ],
            [
                'main_text' => 'Quận Bình Thạnh',
                'secondary_text' => 'Thành phố Hồ Chí Minh, Việt Nam',
                'full_address' => 'Quận Bình Thạnh, Thành phố Hồ Chí Minh, Việt Nam',
                'source' => 'popular'
            ],
            [
                'main_text' => 'Thành phố Phan Thiết',
                'secondary_text' => 'Tỉnh Bình Thuận, Việt Nam',
                'full_address' => 'Thành phố Phan Thiết, Tỉnh Bình Thuận, Việt Nam',
                'source' => 'popular'
            ],
            [
                'main_text' => 'Quận Cầu Giấy',
                'secondary_text' => 'Thành phố Hà Nội, Việt Nam',
                'full_address' => 'Quận Cầu Giấy, Thành phố Hà Nội, Việt Nam',
                'source' => 'popular'
            ]
        ];
    } else {
        // Construct Vietnam address based on user's exact input
        $full = $q;
        if (!str_contains($full, 'Việt Nam')) {
            $full .= ', Việt Nam';
        }
        $results[] = [
            'main_text' => $q,
            'secondary_text' => 'Địa chỉ tại Việt Nam (Bấm để chọn)',
            'full_address' => $full,
            'source' => 'custom'
        ];
    }
}

// Ensure all addresses strictly end with ", Việt Nam"
foreach ($results as &$res) {
    if (!str_contains($res['full_address'], 'Việt Nam')) {
        $res['full_address'] .= ', Việt Nam';
    }
}
unset($res);

echo json_encode(array_slice($results, 0, 7), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
