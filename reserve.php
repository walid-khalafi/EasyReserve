<?php
header("Content-Type: application/json; charset=utf-8");

// CORS: allow requests from the website domain (khiec.ir)
$allowedOrigin = 'https://khouzestan.isipo.ir';

if (isset($_SERVER['HTTP_ORIGIN']) && $_SERVER['HTTP_ORIGIN'] === $allowedOrigin) {
    header("Access-Control-Allow-Origin: {$allowedOrigin}");
    header('Vary: Origin');
}

header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// If preflight, return immediately
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}



// تنظیمات اتصال به دیتابیس
$host = "YOUR_DB_HOST";
$user = "YOUR_DB_USER";
$pass = "YOUR_DB_PASSWORD";
$dbname = "YOUR_DB_NAME";

// اتصال به دیتابیس
$conn = new mysqli($host, $user, $pass, $dbname);
$conn->set_charset("utf8");

if ($conn->connect_error) {
    echo json_encode(["status" => "error", "message" => "خطا در اتصال به دیتابیس"]);
    exit;
}

// دریافت داده‌ها
$full_name   = trim($_POST['full_name'] ?? "");
$national_id = trim($_POST['national_id'] ?? "");
$phone       = trim($_POST['phone'] ?? "");
$subject     = trim($_POST['subject'] ?? "");
$visit_day   = trim($_POST['visit_day'] ?? "");
$created_at  = date("Y-m-d H:i:s");
$user_ip     = $_SERVER['REMOTE_ADDR'];

// ظرفیت مجاز
$MAX_CAPACITY = 50;

// -------------------------
// اعتبارسنجی سمت سرور
// -------------------------

if ($full_name === "" || $national_id === "" || $phone === "" || $subject === "" || $visit_day === "") {
    echo json_encode(["status" => "error", "message" => "تمامی فیلدها باید تکمیل شوند"]);
    exit;
}

// اعتبارسنجی کد ملی
function validateNationalCode($code) {
    if (!preg_match('/^\d{10}$/', $code)) return false;
    $check = $code[9];
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += $code[$i] * (10 - $i);
    }
    $rem = $sum % 11;
    return ($rem < 2 && $check == $rem) || ($rem >= 2 && $check == (11 - $rem));
}

if (!validateNationalCode($national_id)) {
    echo json_encode(["status" => "error", "message" => "کد ملی معتبر نیست"]);
    exit;
}

// اعتبارسنجی شماره موبایل
if (!preg_match('/^09\d{9}$/', $phone)) {
    echo json_encode(["status" => "error", "message" => "شماره موبایل باید با 09 شروع شده و 11 رقم باشد"]);
    exit;
}

// -------------------------
// جلوگیری از ثبت تکراری
// -------------------------
$check = $conn->prepare("SELECT id FROM reservations WHERE national_id = ? AND visit_day = ?");
$check->bind_param("ss", $national_id, $visit_day);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    echo json_encode(["status" => "error", "message" => "شما قبلاً برای این تاریخ نوبت ثبت کرده‌اید"]);
    exit;
}

// -------------------------
// بررسی ظرفیت روز
// -------------------------
$count = $conn->prepare("SELECT COUNT(*) FROM reservations WHERE visit_day = ?");
$count->bind_param("s", $visit_day);
$count->execute();
$count->bind_result($current_count);
$count->fetch();
$count->close();

if ($current_count >= $MAX_CAPACITY) {
    echo json_encode(["status" => "error", "message" => "ظرفیت این روز تکمیل شده است"]);
    exit;
}

// شماره نوبت = تعداد فعلی + 1
$queue_number = $current_count + 1;

// -------------------------
// تبدیل تاریخ میلادی به شمسی
// -------------------------
function gregorian_to_jalali($gy, $gm, $gd) {
    $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + floor(($gy2 + 3) / 4) - floor(($gy2 + 99) / 100) + floor(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * floor($days / 12053));
    $days %= 12053;
    $jy += 4 * floor($days / 1461);
    $days %= 1461;
    if ($days > 365) {
        $jy += floor(($days - 1) / 365);
        $days = ($days - 1) % 365;
    }
    $jm = ($days < 186) ? 1 + floor($days / 31) : 7 + floor(($days - 186) / 30);
    $jd = 1 + (($days < 186) ? ($days % 31) : (($days - 186) % 30));
    return [$jy, $jm, $jd];
}

$parts = explode("-", $visit_day);
list($jy, $jm, $jd) = gregorian_to_jalali($parts[0], $parts[1], $parts[2]);
$visit_day_shamsi = $jy . "/" . str_pad($jm, 2, "0", STR_PAD_LEFT) . "/" . str_pad($jd, 2, "0", STR_PAD_LEFT);

// -------------------------
// تبدیل عدد به فارسی
// -------------------------
function toPersianNumber($num) {
    $persian = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    return str_replace(range(0,9), $persian, $num);
}

$queue_number_fa = toPersianNumber($queue_number);

// -------------------------
// ثبت اطلاعات
// -------------------------
$stmt = $conn->prepare("
    INSERT INTO reservations (full_name, national_id, phone, subject, visit_day, created_at, ip_address)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param("sssssss", $full_name, $national_id, $phone, $subject, $visit_day, $created_at, $user_ip);

if ($stmt->execute()) {

    // ارسال پیامک
    sendSMS($phone, $full_name, $visit_day_shamsi, $queue_number_fa);

    echo json_encode([
        "status" => "success",
        "message" => "نوبت شما با موفقیت ثبت شد",
        "queue_number" => $queue_number_fa,
        "visit_day_shamsi" => $visit_day_shamsi
    ]);

} else {
    echo json_encode(["status" => "error", "message" => "خطا در ثبت اطلاعات"]);
}

$stmt->close();
$conn->close();


// -------------------------
// تابع ارسال پیامک
// -------------------------
function sendSMS($phone, $name, $visit_day_shamsi, $queue_number_fa) {

    $text = 
"جناب آقای/سرکار خانم $name
نوبت شما با موفقیت ثبت شد.
تاریخ مراجعه: $visit_day_shamsi
شماره نوبت: $queue_number_fa
شرکت شهرک‌های صنعتی خوزستان";

    // API واقعی را اینجا قرار بده
    $api_url = "https://sms-provider.com/api/send";

    $data = [
        "phone" => $phone,
        "message" => $text,
        "sender" => "3000XXXX"
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch);
    curl_close($ch);
}
?>
