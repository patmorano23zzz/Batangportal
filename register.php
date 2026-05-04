<?php
require_once __DIR__ . '/config/functions.php';
startSession();

// Already logged in — redirect away
if (isLoggedIn()) {
    redirect(APP_URL . '/' . ($_SESSION['role'] === 'parent' ? 'portal' : 'admin') . '/dashboard.php');
}

$db = getDB();
$schoolInfo = $db->query("SELECT * FROM school_info LIMIT 1")->fetch();

// ============================================================
// STEP MANAGEMENT
// step 1 — verify student via LRN
// step 2 — fill in account details
// ============================================================
$step     = (int)($_SESSION['reg_step'] ?? 1);
$student  = $_SESSION['reg_student'] ?? null;
$errors   = [];
$success  = false;

// ── STEP 1: Verify LRN ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_lrn'])) {
    $lrn       = sanitize($_POST['lrn'] ?? '');
    $lastName  = sanitize($_POST['last_name'] ?? '');
    $firstName = sanitize($_POST['first_name'] ?? '');

    if (empty($lrn) && (empty($lastName) || empty($firstName))) {
        $errors[] = 'Please enter the LRN, or both the first name and last name of the student.';
    } else {
        // Build query — match by LRN or by full name
        if (!empty($lrn)) {
            $stmt = $db->prepare("
                SELECT s.*, sec.section_name, gl.grade_name
                FROM students s
                LEFT JOIN sections sec ON s.section_id = sec.id
                LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
                WHERE s.lrn = ? AND s.enrollment_status = 'enrolled'
            ");
            $stmt->execute([$lrn]);
        } else {
            $stmt = $db->prepare("
                SELECT s.*, sec.section_name, gl.grade_name
                FROM students s
                LEFT JOIN sections sec ON s.section_id = sec.id
                LEFT JOIN grade_levels gl ON sec.grade_level_id = gl.id
                WHERE s.first_name LIKE ? AND s.last_name LIKE ? AND s.enrollment_status = 'enrolled'
            ");
            $stmt->execute([$firstName . '%', $lastName]);
        }

        $found = $stmt->fetch();

        if (!$found) {
            $errors[] = 'No enrolled student found with the provided details. Please check the information and try again, or contact the school.';
        } else {
            // Check if this student already has a linked parent account
            if (!empty($found['parent_user_id'])) {
                $errors[] = 'This student already has a registered portal account. Please log in or contact the school administrator.';
            } else {
                // Store verified student in session and advance to step 2
                $_SESSION['reg_student'] = $found;
                $_SESSION['reg_step']    = 2;
                $step    = 2;
                $student = $found;
            }
        }
    }
}

// ── STEP 2: Create Account ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_account'])) {
    // Re-validate session student
    if (empty($_SESSION['reg_student'])) {
        $_SESSION['reg_step'] = 1;
        redirect(APP_URL . '/register.php');
    }

    $student  = $_SESSION['reg_student'];
    $fullName = sanitize($_POST['full_name'] ?? '');
    $username = sanitize($_POST['username'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $contact  = sanitize($_POST['contact_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';
    $relation = sanitize($_POST['relationship'] ?? '');

    // Validation
    if (empty($fullName))   $errors[] = 'Full name is required.';
    if (empty($username))   $errors[] = 'Username is required.';
    if (empty($password))   $errors[] = 'Password is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (!empty($email) && !validateEmail($email)) $errors[] = 'Invalid email address.';
    if (empty($_POST['agree_terms']))  $errors[] = 'You must agree to the Terms and Conditions to register.';

    // Username uniqueness
    if (!empty($username)) {
        $check = $db->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) $errors[] = 'Username is already taken. Please choose another.';
    }

    if (empty($errors)) {
        // Create the parent account
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO users (username, password, role, full_name, email, contact_number, is_active)
            VALUES (?, ?, 'parent', ?, ?, ?, 1)
        ");
        $stmt->execute([$username, $hash, $fullName, $email, $contact]);
        $newUserId = $db->lastInsertId();

        // Link the student to this parent account
        $db->prepare("UPDATE students SET parent_user_id = ? WHERE id = ?")
           ->execute([$newUserId, $student['id']]);

        // Notify admins
        $admins = $db->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1")->fetchAll();
        foreach ($admins as $admin) {
            sendNotification(
                $admin['id'],
                'New Parent Registration',
                "$fullName registered as parent/guardian of {$student['first_name']} {$student['last_name']} (LRN: {$student['lrn']}).",
                'system'
            );
        }

        auditLog('REGISTER', 'users', $newUserId, "Self-registered parent for student ID: {$student['id']}");

        // Clear registration session data
        unset($_SESSION['reg_step'], $_SESSION['reg_student']);

        $success = true;
        $registeredUsername = $username;
    }
}

// Back button — reset to step 1
if (isset($_GET['back'])) {
    unset($_SESSION['reg_step'], $_SESSION['reg_student']);
    redirect(APP_URL . '/register.php');
}

// Sync step/student from session if not set by POST above
if (!$student && !empty($_SESSION['reg_student'])) {
    $student = $_SESSION['reg_student'];
    $step    = $_SESSION['reg_step'] ?? 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register — <?= htmlspecialchars($schoolInfo['school_name'] ?? 'BatangPortal') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="<?= APP_URL ?>/assets/css/style.css" rel="stylesheet">
    <style>
        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0;
            margin-bottom: 1.75rem;
        }
        .step-dot {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: .8rem;
            font-weight: 800;
            flex-shrink: 0;
            transition: background .2s;
        }
        .step-dot.done    { background: #1a73e8; color: #fff; }
        .step-dot.active  { background: #1a73e8; color: #fff; box-shadow: 0 0 0 4px rgba(26,115,232,.2); }
        .step-dot.pending { background: #e0e0e0; color: #999; }
        .step-line {
            flex: 1;
            height: 3px;
            background: #e0e0e0;
            max-width: 60px;
        }
        .step-line.done { background: #1a73e8; }
        .step-label {
            font-size: .7rem;
            font-weight: 700;
            margin-top: .3rem;
            text-align: center;
        }
        .student-card {
            background: #e8f0fe;
            border: 1.5px solid #c5d8fb;
            border-radius: 10px;
            padding: 1rem 1.25rem;
        }
        .register-card { max-width: 480px; }
    </style>
</head>
<body>
<div class="login-wrapper">
    <div class="register-card card p-4 p-md-5 mx-3 w-100">

        <!-- Header -->
        <div class="text-center mb-3">
            <?php if (!empty($schoolInfo['logo'])): ?>
                <img src="<?= APP_URL . '/' . $schoolInfo['logo'] ?>" alt="Logo" height="60" class="mb-2">
            <?php else: ?>
                <i class="bi bi-mortarboard-fill text-primary" style="font-size:2.8rem"></i>
            <?php endif; ?>
            <h5 class="fw-800 mb-0 mt-1"><?= htmlspecialchars($schoolInfo['school_name'] ?? 'BatangPortal') ?></h5>
            <p class="text-muted small mb-0">Parent / Guardian Portal Registration</p>
        </div>

        <?php if ($success): ?>
        <!-- ── SUCCESS ── -->
        <div class="text-center py-3">
            <div class="mb-3" style="font-size:3.5rem">🎉</div>
            <h5 class="fw-800 text-success">Registration Successful!</h5>
            <p class="text-muted small mb-3">
                Your account has been created. You can now log in to the parent portal.
            </p>
            <a href="<?= APP_URL ?>/login.php" class="btn btn-primary w-100 py-2 fw-700">
                <i class="bi bi-box-arrow-in-right me-2"></i>Go to Login
            </a>
        </div>

        <?php else: ?>

        <!-- Step Indicator -->
        <div class="step-indicator">
            <div>
                <div class="step-dot <?= $step >= 1 ? 'done' : 'pending' ?>">
                    <?= $step > 1 ? '<i class="bi bi-check"></i>' : '1' ?>
                </div>
                <div class="step-label text-<?= $step >= 1 ? 'primary' : 'muted' ?>">Verify</div>
            </div>
            <div class="step-line <?= $step >= 2 ? 'done' : '' ?>" style="margin-bottom:1rem"></div>
            <div>
                <div class="step-dot <?= $step >= 2 ? 'active' : 'pending' ?>">2</div>
                <div class="step-label text-<?= $step >= 2 ? 'primary' : 'muted' ?>">Register</div>
            </div>
        </div>

        <!-- Errors -->
        <?php if ($errors): ?>
        <div class="alert alert-danger py-2 small">
            <?php foreach ($errors as $e): ?>
                <div><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
        <!-- ── STEP 1: Verify Student ── -->
        <p class="small text-muted mb-3">
            Enter your child's <strong>LRN</strong> (Learner Reference Number) to verify enrollment.
            If you don't have the LRN, you may search by name instead.
        </p>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="verify_lrn" value="1">

            <div class="mb-3">
                <label class="form-label">LRN (Learner Reference Number)</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                    <input type="text" name="lrn" id="lrn" class="form-control"
                           placeholder="12-digit LRN" maxlength="12"
                           value="<?= htmlspecialchars($_POST['lrn'] ?? '') ?>">
                </div>
            </div>

            <div class="text-center text-muted small my-2 fw-600">— OR search by name —</div>

            <div class="row g-2 mb-4">
                <div class="col-6">
                    <label class="form-label">First Name</label>
                    <input type="text" name="first_name" class="form-control"
                           placeholder="e.g. Juan"
                           value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                </div>
                <div class="col-6">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control"
                           placeholder="e.g. Dela Cruz"
                           value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-700">
                <i class="bi bi-search me-2"></i>Verify Enrollment
            </button>
        </form>

        <?php elseif ($step === 2 && $student): ?>
        <!-- ── STEP 2: Create Account ── -->

        <!-- Verified student card -->
        <div class="student-card mb-4">
            <div class="d-flex align-items-center gap-3">
                <div class="avatar" style="width:44px;height:44px;font-size:1.2rem;background:#c5d8fb;color:#1a73e8">
                    <?= strtoupper(substr($student['first_name'], 0, 1)) ?>
                </div>
                <div>
                    <div class="fw-700"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></div>
                    <div class="small text-muted">
                        <?= htmlspecialchars($student['grade_name'] ?? '—') ?>
                        <?= $student['section_name'] ? '— ' . htmlspecialchars($student['section_name']) : '' ?>
                        <?php if ($student['lrn']): ?>
                            &nbsp;·&nbsp; LRN: <?= htmlspecialchars($student['lrn']) ?>
                        <?php endif; ?>
                    </div>
                    <span class="badge bg-success" style="font-size:.65rem">Enrolled ✓</span>
                </div>
            </div>
        </div>

        <p class="small text-muted mb-3">Create your parent/guardian account to access the portal.</p>

        <form method="POST" autocomplete="off">
            <input type="hidden" name="create_account" value="1">

            <div class="mb-3">
                <label class="form-label">Your Full Name <span class="text-danger">*</span></label>
                <input type="text" name="full_name" class="form-control"
                       placeholder="e.g. Maria Dela Cruz"
                       value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Relationship to Student</label>
                <select name="relationship" class="form-select">
                    <option value="">— Select —</option>
                    <option value="Father"  <?= ($_POST['relationship'] ?? '') === 'Father'  ? 'selected' : '' ?>>Father</option>
                    <option value="Mother"  <?= ($_POST['relationship'] ?? '') === 'Mother'  ? 'selected' : '' ?>>Mother</option>
                    <option value="Guardian"<?= ($_POST['relationship'] ?? '') === 'Guardian'? 'selected' : '' ?>>Guardian</option>
                    <option value="Sibling" <?= ($_POST['relationship'] ?? '') === 'Sibling' ? 'selected' : '' ?>>Sibling</option>
                    <option value="Other"   <?= ($_POST['relationship'] ?? '') === 'Other'   ? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label">Contact Number</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-phone"></i></span>
                    <input type="text" name="contact_number" class="form-control"
                           placeholder="09XXXXXXXXX"
                           value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control"
                           placeholder="Optional"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
            </div>

            <hr class="my-3">

            <div class="mb-3">
                <label class="form-label">Username <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                    <input type="text" name="username" class="form-control"
                           placeholder="Choose a username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocomplete="off">
                </div>
                <div class="form-text">Letters, numbers, and underscores only. No spaces.</div>
            </div>

            <div class="mb-3">
                <label class="form-label">Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" id="pw1" class="form-control"
                           placeholder="Minimum 8 characters" required minlength="8" autocomplete="new-password">
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePw('pw1','eye1')">
                        <i class="bi bi-eye" id="eye1"></i>
                    </button>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                    <input type="password" name="confirm_password" id="pw2" class="form-control"
                           placeholder="Re-enter password" required autocomplete="new-password">
                    <button type="button" class="btn btn-outline-secondary" onclick="togglePw('pw2','eye2')">
                        <i class="bi bi-eye" id="eye2"></i>
                    </button>
                </div>
                <div id="pwMatch" class="form-text"></div>
            </div>

            <!-- Terms & Conditions -->
            <div class="border rounded p-3 mb-4 bg-light">
                <div class="form-check">
                    <input type="checkbox" name="agree_terms" id="agreeTerms"
                           class="form-check-input <?= isset($_POST['create_account']) && empty($_POST['agree_terms']) ? 'is-invalid' : '' ?>"
                           required <?= !empty($_POST['agree_terms']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="agreeTerms">
                        I have read and agree to the
                        <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal" class="fw-700">
                            Terms and Conditions
                        </a>
                        and
                        <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal" class="fw-700">
                            Privacy Policy
                        </a>
                        of <?= htmlspecialchars($schoolInfo['school_name'] ?? 'BatangPortal') ?>.
                        <span class="text-danger">*</span>
                    </label>
                    <div class="invalid-feedback">You must agree to the Terms and Conditions to register.</div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 py-2 fw-700 mb-2">
                <i class="bi bi-person-check me-2"></i>Create Account
            </button>
            <a href="register.php?back=1" class="btn btn-outline-secondary w-100">
                <i class="bi bi-arrow-left me-1"></i>Back
            </a>
        </form>
        <?php endif; ?>

        <div class="text-center mt-4 small text-muted">
            Already have an account?
            <a href="<?= APP_URL ?>/login.php" class="fw-700 text-decoration-none">Sign in</a>
        </div>

        <?php endif; // end !$success ?>

        <div class="text-center mt-3 small text-muted">
            &copy; <?= date('Y') ?> BatangPortal — DepEd Philippines
        </div>
    </div>
</div>

<!-- ── TERMS & CONDITIONS MODAL ── -->
<div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-700" id="termsModalLabel">
                    <i class="bi bi-file-earmark-text me-2"></i>Terms and Conditions &amp; Privacy Policy
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" style="font-size:.88rem;line-height:1.7">

                <p class="text-muted small">
                    Effective Date: <?= date('F d, Y') ?> &nbsp;·&nbsp;
                    <?= htmlspecialchars($schoolInfo['school_name'] ?? 'BatangPortal') ?>,
                    <?= htmlspecialchars($schoolInfo['address'] ?? 'Philippines') ?>
                </p>

                <h6 class="fw-700 mt-3">1. Acceptance of Terms</h6>
                <p>
                    By registering for an account on the <strong><?= htmlspecialchars($schoolInfo['school_name'] ?? 'BatangPortal') ?> Parent Portal</strong>
                    ("the Portal"), you confirm that you are the parent or legal guardian of an enrolled learner at this school,
                    and that you agree to be bound by these Terms and Conditions. If you do not agree, please do not proceed with registration.
                </p>

                <h6 class="fw-700 mt-3">2. Eligibility</h6>
                <ul>
                    <li>You must be the parent, legal guardian, or authorized representative of a currently enrolled learner.</li>
                    <li>You must provide accurate and truthful information during registration.</li>
                    <li>Only one (1) portal account may be linked per enrolled learner.</li>
                    <li>You must be at least 18 years of age to register.</li>
                </ul>

                <h6 class="fw-700 mt-3">3. Account Responsibilities</h6>
                <ul>
                    <li>You are responsible for maintaining the confidentiality of your username and password.</li>
                    <li>You must not share your account credentials with any other person.</li>
                    <li>You must notify the school immediately if you suspect unauthorized access to your account.</li>
                    <li>The school reserves the right to suspend or deactivate accounts that violate these terms.</li>
                </ul>

                <h6 class="fw-700 mt-3">4. Use of the Portal</h6>
                <p>The Portal is provided solely for the following purposes:</p>
                <ul>
                    <li>Viewing your child's academic records, grades, and attendance</li>
                    <li>Requesting official school documents (Form 137, Good Moral Certificate, Certificate of Enrollment, etc.)</li>
                    <li>Receiving school announcements and notifications</li>
                    <li>Downloading publicly available school documents</li>
                </ul>
                <p>
                    Any use of the Portal for unauthorized, illegal, or harmful purposes is strictly prohibited
                    and may result in account termination and referral to appropriate authorities.
                </p>

                <h6 class="fw-700 mt-3">5. Privacy Policy &amp; Data Collection</h6>
                <p>
                    In compliance with the <strong>Data Privacy Act of 2012 (Republic Act No. 10173)</strong>
                    and the National Privacy Commission's guidelines, we collect and process the following personal data:
                </p>
                <ul>
                    <li><strong>Parent/Guardian:</strong> Full name, contact number, email address, relationship to learner</li>
                    <li><strong>Learner:</strong> LRN, name, date of birth, grade level, section, academic records, attendance</li>
                </ul>
                <p>This data is collected for the following purposes:</p>
                <ul>
                    <li>To provide access to your child's academic information</li>
                    <li>To process document requests</li>
                    <li>To send school-related notifications and announcements</li>
                    <li>To comply with DepEd reporting requirements</li>
                </ul>
                <p>
                    Your personal data will <strong>not</strong> be sold, rented, or shared with third parties
                    outside of the school and the Department of Education (DepEd) without your consent,
                    except as required by law.
                </p>

                <h6 class="fw-700 mt-3">6. Data Retention</h6>
                <p>
                    Personal data will be retained for the duration of the learner's enrollment and for a period
                    required by DepEd regulations thereafter. You may request correction or deletion of your
                    personal data by contacting the school registrar.
                </p>

                <h6 class="fw-700 mt-3">7. Document Requests</h6>
                <ul>
                    <li>Document requests are subject to the school's processing schedule and requirements.</li>
                    <li>Processing times are estimates and may vary depending on school workload.</li>
                    <li>The school reserves the right to require additional verification before releasing documents.</li>
                    <li>Fees, if applicable, must be settled before documents are released.</li>
                </ul>

                <h6 class="fw-700 mt-3">8. Accuracy of Information</h6>
                <p>
                    The school endeavors to keep academic records accurate and up to date.
                    If you believe there is an error in your child's records, please contact the class adviser
                    or school registrar directly. The Portal displays data as encoded by school personnel.
                </p>

                <h6 class="fw-700 mt-3">9. Limitation of Liability</h6>
                <p>
                    The school shall not be liable for any loss or damage arising from your use of the Portal,
                    including but not limited to unauthorized access to your account due to your failure to
                    maintain the security of your credentials.
                </p>

                <h6 class="fw-700 mt-3">10. Changes to These Terms</h6>
                <p>
                    The school reserves the right to update these Terms and Conditions at any time.
                    Continued use of the Portal after changes are posted constitutes acceptance of the revised terms.
                    You will be notified of significant changes through the Portal's announcement system.
                </p>

                <h6 class="fw-700 mt-3">11. Contact</h6>
                <p>
                    For questions, concerns, or data privacy requests, please contact:
                </p>
                <ul>
                    <li><strong>School:</strong> <?= htmlspecialchars($schoolInfo['school_name'] ?? 'BatangPortal') ?></li>
                    <?php if (!empty($schoolInfo['address'])): ?>
                    <li><strong>Address:</strong> <?= htmlspecialchars($schoolInfo['address']) ?></li>
                    <?php endif; ?>
                    <?php if (!empty($schoolInfo['contact_number'])): ?>
                    <li><strong>Contact:</strong> <?= htmlspecialchars($schoolInfo['contact_number']) ?></li>
                    <?php endif; ?>
                    <?php if (!empty($schoolInfo['email'])): ?>
                    <li><strong>Email:</strong> <?= htmlspecialchars($schoolInfo['email']) ?></li>
                    <?php endif; ?>
                    <?php if (!empty($schoolInfo['principal_name'])): ?>
                    <li><strong>School Head:</strong> <?= htmlspecialchars($schoolInfo['principal_name']) ?></li>
                    <?php endif; ?>
                </ul>

                <div class="alert alert-info small mt-3 mb-0">
                    <i class="bi bi-shield-check me-1"></i>
                    This portal is operated in accordance with <strong>DepEd Order No. 32, s. 2023</strong>
                    (Basic Education Data Privacy Policy) and the <strong>Data Privacy Act of 2012 (RA 10173)</strong>.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal"
                        onclick="document.getElementById('agreeTerms').checked=true">
                    <i class="bi bi-check-circle me-1"></i>I Understand &amp; Agree
                </button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Toggle password visibility
function togglePw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    input.type  = input.type === 'password' ? 'text' : 'password';
    icon.className = input.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

// Live password match indicator
const pw1 = document.getElementById('pw1');
const pw2 = document.getElementById('pw2');
const msg = document.getElementById('pwMatch');
if (pw2) {
    pw2.addEventListener('input', function () {
        if (!this.value) { msg.textContent = ''; return; }
        if (this.value === pw1.value) {
            msg.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Passwords match</span>';
        } else {
            msg.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>Passwords do not match</span>';
        }
    });
}

// LRN — digits only
const lrnInput = document.getElementById('lrn');
if (lrnInput) {
    lrnInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 12);
    });
}
</script>
</body>
</html>
