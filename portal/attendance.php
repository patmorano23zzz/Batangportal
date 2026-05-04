<?php
$pageTitle = 'Attendance';
require_once __DIR__ . '/../includes/header.php';
requireRole(['parent']);

$db = getDB();
$userId = $_SESSION['user_id'];

$children = $db->prepare("SELECT s.*, sec.section_name, gl.grade_name FROM students s LEFT JOIN sections sec ON s.section_id=sec.id LEFT JOIN grade_levels gl ON sec.grade_level_id=gl.id WHERE s.parent_user_id=?");
$children->execute([$userId]);
$children = $children->fetchAll();

$studentId = (int)($_GET['student_id'] ?? ($children[0]['id'] ?? 0));
$month     = (int)($_GET['month'] ?? date('n'));
$year      = (int)($_GET['year'] ?? date('Y'));

$selectedStudent = null;
foreach ($children as $c) {
    if ($c['id'] == $studentId) { $selectedStudent = $c; break; }
}

$attendance = [];
$summary    = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];

if ($selectedStudent) {
    $startDate = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
    $endDate   = date('Y-m-t', strtotime($startDate));

    $stmt = $db->prepare("SELECT * FROM attendance WHERE student_id=? AND date BETWEEN ? AND ? ORDER BY date");
    $stmt->execute([$studentId, $startDate, $endDate]);
    foreach ($stmt->fetchAll() as $a) {
        $attendance[$a['date']] = $a;
        $summary[$a['status']] = ($summary[$a['status']] ?? 0) + 1;
    }
}

$monthName = date('F Y', mktime(0,0,0,$month,1,$year));
?>

<div class="page-header">
    <div>
        <h1><i class="bi bi-calendar-check me-2 text-primary"></i>Attendance</h1>
    </div>
</div>

<!-- Selector -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="student_id" class="form-select form-select-sm" onchange="this.form.submit()">
                    <?php foreach ($children as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $studentId==$c['id']?'selected':'' ?>>
                            <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="month" class="form-select form-select-sm">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $month==$m?'selected':'' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="number" name="year" class="form-control form-control-sm" value="<?= $year ?>" min="2020" max="2030">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm">View</button>
            </div>
        </form>
    </div>
</div>

<?php if ($selectedStudent): ?>
<div class="row g-3">
    <!-- Summary -->
    <div class="col-md-3">
        <div class="card">
            <div class="card-header"><?= htmlspecialchars($selectedStudent['first_name']) ?>'s Summary</div>
            <div class="card-body">
                <div class="text-muted small mb-2"><?= $monthName ?></div>
                <?php
                $colors = ['present'=>'success','absent'=>'danger','late'=>'warning','excused'=>'info'];
                foreach ($summary as $s => $cnt):
                ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-<?= $colors[$s] ?> text-capitalize"><?= $s ?></span>
                    <span class="fw-700"><?= $cnt ?> day(s)</span>
                </div>
                <?php endforeach; ?>
                <hr>
                <div class="small text-muted">Total recorded: <?= array_sum($summary) ?> day(s)</div>
            </div>
        </div>
    </div>

    <!-- Calendar -->
    <div class="col-md-9">
        <div class="card">
            <div class="card-header"><?= $monthName ?></div>
            <div class="card-body">
                <?php
                $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
                $firstDay    = date('N', mktime(0,0,0,$month,1,$year)); // 1=Mon, 7=Sun
                $statusColors = ['present'=>'#d4edda','absent'=>'#f8d7da','late'=>'#fff3cd','excused'=>'#d1ecf1'];
                $statusText   = ['present'=>'#155724','absent'=>'#721c24','late'=>'#856404','excused'=>'#0c5460'];
                ?>
                <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px">
                    <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day): ?>
                        <div class="text-center small fw-700 text-muted py-1"><?= $day ?></div>
                    <?php endforeach; ?>

                    <?php for ($i = 1; $i < $firstDay; $i++): ?>
                        <div></div>
                    <?php endfor; ?>

                    <?php for ($d = 1; $d <= $daysInMonth; $d++):
                        $dateStr = "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($d, 2, '0', STR_PAD_LEFT);
                        $att = $attendance[$dateStr] ?? null;
                        $bg  = $att ? ($statusColors[$att['status']] ?? '#f8f9fa') : '#f8f9fa';
                        $tc  = $att ? ($statusText[$att['status']] ?? '#333') : '#333';
                        $dayOfWeek = date('N', strtotime($dateStr));
                        $isWeekend = $dayOfWeek >= 6;
                    ?>
                    <div class="text-center rounded p-1" style="background:<?= $isWeekend ? '#f0f0f0' : $bg ?>;color:<?= $isWeekend ? '#ccc' : $tc ?>;min-height:40px;font-size:.8rem">
                        <div class="fw-700"><?= $d ?></div>
                        <?php if ($att && !$isWeekend): ?>
                            <div style="font-size:.6rem"><?= ucfirst($att['status']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>
                </div>

                <!-- Legend -->
                <div class="d-flex gap-3 mt-3 flex-wrap">
                    <?php foreach ($statusColors as $s => $bg): ?>
                    <div class="d-flex align-items-center gap-1 small">
                        <div class="rounded" style="width:14px;height:14px;background:<?= $bg ?>;border:1px solid #ddd"></div>
                        <span class="text-capitalize"><?= $s ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
