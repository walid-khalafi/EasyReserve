<?php
/**
 * helpers.php
 * Persian (Jalali/Shamsi) utilities - production ready & PHP 7.4+ compatible
 */

date_default_timezone_set('Asia/Tehran');

// -------------------------
// Gregorian -> Jalali
// -------------------------
function gregorian_to_jalali(int $gy, int $gm, int $gd): array {
    $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];

    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;

    $days =
        355666 +
        (365 * $gy) +
        (int)(($gy2 + 3) / 4) -
        (int)(($gy2 + 99) / 100) +
        (int)(($gy2 + 399) / 400) +
        $gd +
        $g_d_m[$gm - 1];

    $jy = -1595 + (33 * (int)($days / 12053));
    $days %= 12053;

    $jy += 4 * (int)($days / 1461);
    $days %= 1461;

    if ($days > 365) {
        $jy += (int)(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }

    $jm = ($days < 186)
        ? 1 + (int)($days / 31)
        : 7 + (int)(($days - 186) / 30);

    $jd = 1 + (($days < 186)
        ? ($days % 31)
        : (($days - 186) % 30));

    return [$jy, $jm, $jd];
}

// -------------------------
// Jalali -> Gregorian  (الگوریتم صحیح - jdf.ir)
// -------------------------
function jalali_to_gregorian(int $jy, int $jm, int $jd): array {
    $jy -= 979;
    $jm -= 1;
    $jd -= 1;

    $j_day_no = 365 * $jy + (int)($jy / 33) * 8 + (int)(($jy % 33 + 3) / 4);

    for ($i = 0; $i < $jm; $i++) {
        $j_day_no += ($i < 6) ? 31 : 30;
    }

    $j_day_no += $jd;

    $g_day_no = $j_day_no + 79;

    $gy = 1600 + 400 * (int)($g_day_no / 146097);
    $g_day_no %= 146097;

    $leap = true;

    if ($g_day_no >= 36525) {
        $g_day_no--;
        $gy += 100 * (int)($g_day_no / 36524);
        $g_day_no %= 36524;

        if ($g_day_no >= 365) {
            $g_day_no++;
        } else {
            $leap = false;
        }
    }

    $gy += 4 * (int)($g_day_no / 1461);
    $g_day_no %= 1461;

    if ($g_day_no >= 366) {
        $leap = false;
        $g_day_no--;
        $gy += (int)($g_day_no / 365);
        $g_day_no %= 365;
    }

    $g_days_in_month = [31, ($leap ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

    $gm = 1;
    foreach ($g_days_in_month as $i => $v) {
        if ($g_day_no < $v) {
            $gm = $i + 1;
            break;
        }
        $g_day_no -= $v;
    }

    $gd = $g_day_no + 1;

    return [$gy, $gm, $gd];
}

// -------------------------
// Format Jalali
// -------------------------
function format_jalali(int $jy, int $jm, int $jd): string {
    return $jy . '/'
        . str_pad((string)$jm, 2, '0', STR_PAD_LEFT) . '/'
        . str_pad((string)$jd, 2, '0', STR_PAD_LEFT);
}

// -------------------------
// Validate Jalali date (safe)
// -------------------------
function validate_jalali_date(string $date): bool {
    $date = trim($date);

    if (!preg_match('/^(\d{4})\/(\d{2})\/(\d{2})$/', $date, $m)) {
        return false;
    }

    $jy = (int)$m[1];
    $jm = (int)$m[2];
    $jd = (int)$m[3];

    if ($jy < 1200 || $jy > 1600) return false;
    if ($jm < 1 || $jm > 12) return false;

    $maxDay = ($jm <= 6) ? 31 : (($jm <= 11) ? 30 : 29);

    return ($jd >= 1 && $jd <= $maxDay);
}

// -------------------------
// Jalali -> Gregorian string (Y-m-d)
// -------------------------
function jalali_to_gregorian_date(string $jalaliDate): string {
    $parts = explode('/', trim($jalaliDate));

    if (count($parts) !== 3) {
        return '';
    }

    if (
        !ctype_digit($parts[0]) ||
        !ctype_digit($parts[1]) ||
        !ctype_digit($parts[2])
    ) {
        return '';
    }

    [$jy, $jm, $jd] = array_map('intval', $parts);

    [$gy, $gm, $gd] = jalali_to_gregorian($jy, $jm, $jd);

    return $gy . '-'
        . str_pad((string)$gm, 2, '0', STR_PAD_LEFT) . '-'
        . str_pad((string)$gd, 2, '0', STR_PAD_LEFT);
}

// -------------------------
// datetime -> Jalali
// -------------------------
function datetime_to_jalali(string $datetime): string {
    if (!$datetime || $datetime === '0000-00-00 00:00:00') {
        return '—';
    }

    $parts = explode(' ', trim($datetime));
    $date = $parts[0] ?? '';
    $time = $parts[1] ?? '';

    if (strpos($date, '-') === false) {
        return '—';
    }

    $dateParts = explode('-', $date);

    if (count($dateParts) !== 3) {
        return '—';
    }

    [$gy, $gm, $gd] = array_map('intval', $dateParts);
    [$jy, $jm, $jd] = gregorian_to_jalali($gy, $gm, $gd);

    return format_jalali($jy, $jm, $jd) . ' - ' . substr($time, 0, 5);
}

// -------------------------
// Persian digits
// -------------------------
function to_persian_number(string $num): string {
    return str_replace(
        range(0, 9),
        ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],
        $num
    );
}

// -------------------------
// Next Monday (safe)
// رفتار یکسان با JS: اگه امروز دوشنبه باشه، دوشنبه هفته بعد برگردون
// -------------------------
function get_next_monday(): string {
    $today = new DateTime('now', new DateTimeZone('Asia/Tehran'));

    $day = (int)$today->format('N'); // 1=Monday ... 7=Sunday

    $daysUntilMonday = (8 - $day) % 7;
    if ($daysUntilMonday === 0) $daysUntilMonday = 7;

    $today->modify('+' . $daysUntilMonday . ' days');

    return $today->format('Y-m-d');
}

// -------------------------
// Gregorian -> Jalali string
// -------------------------
function gregorian_date_to_jalali(string $gregorianDate): string {
    $parts = explode('-', $gregorianDate);

    if (count($parts) !== 3) {
        return '—';
    }

    [$gy, $gm, $gd] = array_map('intval', $parts);

    [$jy, $jm, $jd] = gregorian_to_jalali($gy, $gm, $gd);

    return format_jalali($jy, $jm, $jd);
}

// -------------------------
// Escape helper
// -------------------------
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
