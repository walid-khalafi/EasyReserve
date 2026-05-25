<?php
declare(strict_types=1);

// Fail-safe: never exit before HTML.
// Any backend problem should result in empty rows while UI still renders.

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

date_default_timezone_set('Asia/Tehran');

require_once __DIR__ . '/helpers.php';

// -------------------------
// 1) Input parsing (Jalali -> Gregorian)
// -------------------------
$selectedJalali = isset($_GET['date']) ? trim((string)$_GET['date']) : '';

// Normalize Persian digits to ASCII digits
if ($selectedJalali !== '') {
    $selectedJalali = str_replace(
        ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'],
        ['0','1','2','3','4','5','6','7','8','9'],
        $selectedJalali
    );
    $selectedJalali = preg_replace('/\s+/', '', $selectedJalali);
}

$todayGregorian = date('Y-m-d');
$todayJalali    = gregorian_date_to_jalali($todayGregorian);

$jalaliDate    = $todayJalali;
$gregorianDate = $todayGregorian;
$error         = '';

try {
    if ($selectedJalali !== '' && validate_jalali_date($selectedJalali)) {
        $converted = jalali_to_gregorian_date($selectedJalali);
        if ($converted !== '') {
            $jalaliDate    = $selectedJalali;
            $gregorianDate = $converted;
        }
    }
} catch (Throwable $t) {
    $error         = 'Date error';
    $jalaliDate    = $todayJalali;
    $gregorianDate = $todayGregorian;
}

// -------------------------
// 2) DB attempt (defensive)
// -------------------------
$rows = [];

$host   = 'YOUR_DB_HOST';
$user   = 'YOUR_DB_USER';
$pass   = 'YOUR_DB_PASSWORD';
$dbname = 'YOUR_DB_NAME';

try {
    $conn = @new mysqli($host, $user, $pass, $dbname);

    if ($conn->connect_errno) {
        $error = 'DB connection failed';
    } else {
        $conn->set_charset('utf8mb4');

        $sql = "
            SELECT id, full_name, national_id, phone, subject, visit_day, created_at, ip_address
            FROM reservations
            WHERE visit_day = ?
            ORDER BY created_at ASC
        ";

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            $error = 'DB prepare failed';
        } else {
            $stmt->bind_param('s', $gregorianDate);
            $ok = $stmt->execute();

            if (!$ok) {
                $error = 'DB execute failed';
            } else {
                $result = $stmt->get_result();

                if ($result) {
                    $queue = 1;
                    while ($row = $result->fetch_assoc()) {
                        $row['queue_number']     = $queue++;
                        $row['created_at_jalali'] = datetime_to_jalali((string)$row['created_at']);
                        $rows[] = $row;
                    }
                } else {
                    // Fallback: bind_result (no mysqlnd)
                    $stmt->bind_result($id, $full_name, $national_id, $phone, $subject, $visit_day, $created_at, $ip_address);
                    $queue = 1;
                    while ($stmt->fetch()) {
                        $rows[] = [
                            'id'               => $id,
                            'full_name'        => $full_name,
                            'national_id'      => $national_id,
                            'phone'            => $phone,
                            'subject'          => $subject,
                            'visit_day'        => $visit_day,
                            'created_at'       => $created_at,
                            'ip_address'       => $ip_address,
                            'queue_number'     => $queue++,
                            'created_at_jalali' => datetime_to_jalali((string)$created_at),
                        ];
                    }
                }
            }

            $stmt->close();
        }

        $conn->close();
    }
} catch (Throwable $t) {
    $error = 'DB error';
    $rows  = [];
}

// -------------------------
// 3) HTML always renders
// -------------------------
$rowsCount = count($rows);
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>لیست رزروها</title>

    <!-- Bootstrap 5 RTL -->
    <link href="assets/vendor/bootstrap/bootstrap-5.3.3.rtl.min.css" rel="stylesheet">

    <!-- DataTables -->
    <link href="assets/vendor/datatables/datatables-1.13.6.min.css" rel="stylesheet">

    <!-- DataTables Buttons -->
    <link href="assets/vendor/datatables-buttons/buttons.dataTables-2.4.2.min.css" rel="stylesheet">

    <style>
        body { background: #f6f7fb; font-family: Tahoma, Arial, sans-serif; }
        .rtl-table th,
        .rtl-table td { white-space: nowrap; vertical-align: middle; }
        .dt-buttons { margin-bottom: 8px; }
        .dt-button {
            border-radius: 6px !important;
            font-size: 13px !important;
            padding: 5px 12px !important;
        }
        /* Fix DataTables search/length alignment in RTL */
        .dataTables_filter { text-align: right; }
        .dataTables_length { text-align: left; }
    </style>
</head>
<body>
<div class="container py-4">

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
        <h4 class="m-0">📋 لیست رزروها</h4>
        <?php if ($error !== ''): ?>
            <div class="alert alert-warning m-0 py-2" role="alert">
                ⚠️ <?php echo htmlspecialchars($error, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>
                — جدول خالی نمایش داده می‌شود.
            </div>
        <?php endif; ?>
    </div>

    <!-- Date filter -->
    <form class="row g-2 align-items-end mb-4" method="get" action="">
        <div class="col-12 col-md-4">
            <label class="form-label fw-bold">تاریخ (شمسی)</label>
            <input
                type="text"
                class="form-control"
                name="date"
                value="<?php echo htmlspecialchars($jalaliDate, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>"
                placeholder="مثال: 1403/02/15"
                dir="ltr"
            >
        </div>
        <div class="col-6 col-md-2">
            <button class="btn btn-primary w-100" type="submit">فیلتر</button>
        </div>
        <div class="col-6 col-md-2">
            <a class="btn btn-outline-secondary w-100" href="?">امروز</a>
        </div>
        <div class="col-12 col-md-4 small text-muted" style="direction:ltr; text-align:left;">
            Gregorian: <strong><?php echo htmlspecialchars($gregorianDate, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>
            &nbsp;|&nbsp; تعداد: <strong><?php echo $rowsCount; ?></strong> نفر
        </div>
    </form>

    <!-- Table card -->
    <div class="card shadow-sm">
        <div class="card-body p-3">
            <div class="table-responsive">
                <table
                    id="reservationsTable"
                    class="table table-striped table-hover table-bordered mb-0 rtl-table"
                    style="width:100%"
                >
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>نام و نام خانوادگی</th>
                            <th>کد ملی</th>
                            <th>تلفن</th>
                            <th>موضوع</th>
                            <th>تاریخ بازدید (شمسی)</th>
                            <th>تاریخ ثبت</th>
                            <th>آی‌پی</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?php echo e((string)$r['queue_number']); ?></td>
                                <td><?php echo e((string)$r['full_name']); ?></td>
                                <td dir="ltr"><?php echo e((string)$r['national_id']); ?></td>
                                <td dir="ltr"><?php echo e((string)$r['phone']); ?></td>
                                <td><?php echo e((string)$r['subject']); ?></td>
                                <td><?php echo e(gregorian_date_to_jalali((string)$r['visit_day'])); ?></td>
                                <td><?php echo e((string)$r['created_at_jalali']); ?></td>
                                <td dir="ltr"><?php echo e((string)$r['ip_address']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- jQuery -->
<script src="assets/vendor/jquery/jquery-3.7.1.min.js"></script>

<!-- Bootstrap Bundle (JS + Popper) -->
<script src="assets/vendor/bootstrap/bootstrap-5.3.3.bundle.min.js"></script>

<!-- JSZip (required for Excel export) -->
<script src="assets/vendor/jszip/jszip-3.10.1.min.js"></script>

<!-- DataTables core -->
<script src="assets/vendor/datatables/jquery.dataTables-1.13.6.min.js"></script>

<!-- DataTables Buttons -->
<script src="assets/vendor/datatables-buttons/dataTables.buttons-2.4.2.min.js"></script>
<script src="assets/vendor/datatables-buttons/buttons.html5-2.4.2.min.js"></script>
<script src="assets/vendor/datatables-buttons/buttons.print-2.4.2.min.js"></script>

<script>
(function () {
    if (!window.jQuery || !window.jQuery.fn.DataTable) {
        return; // کتابخانه‌ها موجود نیستند - جدول همچنان کاربردی است
    }

    window.jQuery(function ($) {
        $('#reservationsTable').DataTable({
            language: {
                emptyTable:    'هیچ رکوردی وجود ندارد',
                zeroRecords:   'رکوردی با این فیلتر یافت نشد',
                info:          'نمایش _START_ تا _END_ از _TOTAL_ رکورد',
                infoEmpty:     'نمایش 0 تا 0 از 0 رکورد',
                infoFiltered:  '(فیلتر شده از _MAX_ رکورد)',
                lengthMenu:    'نمایش _MENU_ رکورد',
                search:        'جستجو:',
                paginate: {
                    first:    'ابتدا',
                    last:     'انتها',
                    next:     'بعدی',
                    previous: 'قبلی'
                }
            },

            pageLength: 25,
            lengthMenu: [10, 25, 50, 100],
            order: [],   // حفظ ترتیب اصلی (شماره نوبت)

            // دکمه‌های خروجی
            dom: '<"d-flex justify-content-between align-items-center flex-wrap mb-2"Bf>rtip<"mt-2"l>',

            buttons: [
                {
                    extend:    'excelHtml5',
                    text:      '📥 خروجی Excel',
                    className: 'btn btn-success btn-sm',
                    title:     'رزروها - <?php echo htmlspecialchars($jalaliDate, ENT_QUOTES, 'UTF-8'); ?>',
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend:    'csvHtml5',
                    text:      '📄 خروجی CSV',
                    className: 'btn btn-outline-secondary btn-sm',
                    title:     'رزروها - <?php echo htmlspecialchars($jalaliDate, ENT_QUOTES, 'UTF-8'); ?>',
                    exportOptions: { columns: ':visible' }
                },
                {
                    extend:    'print',
                    text:      '🖨️ چاپ',
                    className: 'btn btn-outline-primary btn-sm',
                    title:     'لیست رزروها - <?php echo htmlspecialchars($jalaliDate, ENT_QUOTES, 'UTF-8'); ?>',
                    exportOptions: { columns: ':visible' }
                }
            ]
        });
    });
})();
</script>
</body>
</html>