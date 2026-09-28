<?php

/**
 * Add Client Page
 * Location: frontend/clients/add.php
 */

session_start();
if (empty($_SESSION['logged_in'])) {
    header('Location: ../../index.php');
    exit;
}

$currentPage = 'clients_add';
$assetPath   = '../../assets';
$rootPath    = '../../';
$pageTitle   = 'Add Client';

$userName  = $_SESSION['user_name']  ?? 'User';
$userEmail = $_SESSION['user_email'] ?? '';
$userRole  = $_SESSION['user_role']  ?? 'user';
$initials  = '';
foreach (explode(' ', trim($userName)) as $part) {
    if ($part !== '') $initials .= mb_strtoupper(mb_substr($part, 0, 1));
}
$initials = mb_substr($initials, 0, 2) ?: 'U';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — Aqibsb Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?= $assetPath ?>/css/sidebar.css">
    <link rel="stylesheet" href="<?= $assetPath ?>/css/dashboard.css">
    <link rel="stylesheet" href="<?= $assetPath ?>/css/add-client.css">
</head>

<body>

    <?php include __DIR__ . '/../../includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include __DIR__ . '/../../includes/topbar.php'; ?>

        <main class="content">

            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb small mb-0">
                    <li class="breadcrumb-item"><a href="../dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item text-muted">Clients</li>
                    <li class="breadcrumb-item active" aria-current="page">Add Client</li>
                </ol>
            </nav>

            <div id="alertContainer"></div>

            <form id="addClientForm"
                action="../../backend/clients/add.php"
                method="POST"
                enctype="multipart/form-data"
                novalidate>

                <!-- ============ UPLOADS ROW ============ -->
                <div class="panel mb-4">
                    <div class="panel-header">
                        <h3 class="panel-title">
                            <i class="bi bi-images me-2 text-primary"></i>Photos & Documents
                        </h3>
                        <span class="text-muted small">Optional but recommended</span>
                    </div>
                    <div class="panel-body">
                        <div class="row g-3">

                            <!-- Profile Picture -->
                            <div class="col-12 col-md-4">
                                <label class="upload-caption">Profile Picture</label>
                                <div class="upload-box" data-upload="profile">
                                    <input type="file" id="profile_pic" name="profile_pic"
                                        accept="image/jpeg,image/png,image/jpg,image/webp" hidden>

                                    <label for="profile_pic"
                                        class="upload-label"
                                        style="display:flex;align-items:center;justify-content:center;
                                              width:100%;height:200px;padding:12px;
                                              border:2px dashed #cbd5e1;border-radius:12px;
                                              background:#fafbfd;cursor:pointer;overflow:hidden;
                                              position:relative;text-align:center;
                                              transition:all .25s ease;">

                                        <div class="upload-preview" id="profilePreview"
                                            style="display:flex;flex-direction:column;align-items:center;
                                                justify-content:center;gap:4px;color:#6b7280;">
                                            <div class="upload-icon"
                                                style="width:46px;height:46px;border-radius:50%;
                                                    background:linear-gradient(135deg,rgba(78,115,223,.12),rgba(118,75,162,.12));
                                                    display:flex;align-items:center;justify-content:center;
                                                    margin-bottom:6px;">
                                                <i class="bi bi-person-bounding-box" style="font-size:22px;color:#4e73df;"></i>
                                            </div>
                                            <p class="mb-1 fw-semibold small">Click to upload</p>
                                            <span class="text-muted small">JPG, PNG, WEBP · max 2MB</span>
                                        </div>

                                        <img id="profilePreviewImg" class="d-none" alt="Preview"
                                            style="position:absolute;top:0;left:0;
                                                width:100%;height:100%;
                                                object-fit:cover;border-radius:10px;">
                                    </label>

                                    <button type="button"
                                        class="btn btn-sm btn-outline-danger w-100 mt-2 d-none"
                                        id="profileClear">
                                        <i class="bi bi-x-lg"></i> Remove
                                    </button>
                                </div>
                            </div>

                            <!-- CNIC Front -->
                            <div class="col-12 col-md-4">
                                <label class="upload-caption">CNIC Front Side</label>
                                <div class="upload-box" data-upload="cnic_front">
                                    <input type="file" id="cnic_front_pic" name="cnic_front_pic"
                                        accept="image/jpeg,image/png,image/jpg,image/webp" hidden>

                                    <label for="cnic_front_pic"
                                        class="upload-label"
                                        style="display:flex;align-items:center;justify-content:center;
                                              width:100%;height:200px;padding:12px;
                                              border:2px dashed #cbd5e1;border-radius:12px;
                                              background:#fafbfd;cursor:pointer;overflow:hidden;
                                              position:relative;text-align:center;
                                              transition:all .25s ease;">

                                        <div class="upload-preview" id="cnicFrontPreview"
                                            style="display:flex;flex-direction:column;align-items:center;
                                                justify-content:center;gap:4px;color:#6b7280;">
                                            <div class="upload-icon"
                                                style="width:46px;height:46px;border-radius:50%;
                                                    background:linear-gradient(135deg,rgba(78,115,223,.12),rgba(118,75,162,.12));
                                                    display:flex;align-items:center;justify-content:center;
                                                    margin-bottom:6px;">
                                                <i class="bi bi-card-image" style="font-size:22px;color:#4e73df;"></i>
                                            </div>
                                            <p class="mb-1 fw-semibold small">Click to upload</p>
                                            <span class="text-muted small">JPG, PNG, WEBP · max 3MB</span>
                                        </div>

                                        <img id="cnicFrontPreviewImg" class="d-none" alt="Preview"
                                            style="position:absolute;top:0;left:0;
                                                width:100%;height:100%;
                                                object-fit:cover;border-radius:10px;">
                                    </label>

                                    <button type="button"
                                        class="btn btn-sm btn-outline-danger w-100 mt-2 d-none"
                                        id="cnicFrontClear">
                                        <i class="bi bi-x-lg"></i> Remove
                                    </button>
                                </div>
                            </div>

                            <!-- CNIC Back -->
                            <div class="col-12 col-md-4">
                                <label class="upload-caption">CNIC Back Side</label>
                                <div class="upload-box" data-upload="cnic_back">
                                    <input type="file" id="cnic_back_pic" name="cnic_back_pic"
                                        accept="image/jpeg,image/png,image/jpg,image/webp" hidden>

                                    <label for="cnic_back_pic"
                                        class="upload-label"
                                        style="display:flex;align-items:center;justify-content:center;
                                              width:100%;height:200px;padding:12px;
                                              border:2px dashed #cbd5e1;border-radius:12px;
                                              background:#fafbfd;cursor:pointer;overflow:hidden;
                                              position:relative;text-align:center;
                                              transition:all .25s ease;">

                                        <div class="upload-preview" id="cnicBackPreview"
                                            style="display:flex;flex-direction:column;align-items:center;
                                                justify-content:center;gap:4px;color:#6b7280;">
                                            <div class="upload-icon"
                                                style="width:46px;height:46px;border-radius:50%;
                                                    background:linear-gradient(135deg,rgba(78,115,223,.12),rgba(118,75,162,.12));
                                                    display:flex;align-items:center;justify-content:center;
                                                    margin-bottom:6px;">
                                                <i class="bi bi-card-image" style="font-size:22px;color:#4e73df;"></i>
                                            </div>
                                            <p class="mb-1 fw-semibold small">Click to upload</p>
                                            <span class="text-muted small">JPG, PNG, WEBP · max 3MB</span>
                                        </div>

                                        <img id="cnicBackPreviewImg" class="d-none" alt="Preview"
                                            style="position:absolute;top:0;left:0;
                                                width:100%;height:100%;
                                                object-fit:cover;border-radius:10px;">
                                    </label>

                                    <button type="button"
                                        class="btn btn-sm btn-outline-danger w-100 mt-2 d-none"
                                        id="cnicBackClear">
                                        <i class="bi bi-x-lg"></i> Remove
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ============ PERSONAL INFORMATION ============ -->
                <div class="panel mb-4">
                    <div class="panel-header">
                        <h3 class="panel-title">
                            <i class="bi bi-person-vcard me-2 text-primary"></i>Personal Information
                        </h3>
                    </div>
                    <div class="panel-body">
                        <div class="row g-3">

                            <div class="col-12 col-md-6 col-lg-4">
                                <label for="name" class="form-label">
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="name" name="name"
                                    placeholder="e.g., Muhammad Ali" required maxlength="150">
                                <div class="invalid-feedback">Please enter the client's full name.</div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <label for="father_name" class="form-label">
                                    Father Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control" id="father_name" name="father_name"
                                    placeholder="e.g., Abdul Rahman" required maxlength="150">
                                <div class="invalid-feedback">Please enter the father's name.</div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <label for="cnic" class="form-label">
                                    CNIC Number <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-credit-card-2-front"></i></span>
                                    <input type="text" class="form-control" id="cnic" name="cnic"
                                        placeholder="12345-1234567-1"
                                        inputmode="numeric" maxlength="15" required>
                                </div>
                                <small class="form-hint">Format: 12345-1234567-1</small>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <label for="whatsapp" class="form-label">
                                    WhatsApp Number <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-whatsapp"></i></span>
                                    <input type="tel" class="form-control" id="whatsapp" name="whatsapp"
                                        placeholder="03001234567" maxlength="13" required>
                                </div>
                                <small class="form-hint">e.g., 03001234567 or +923001234567</small>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <label for="entry_year" class="form-label">
                                    Entry Year <span class="text-danger">*</span>
                                </label>
                                <select class="form-select" id="entry_year" name="entry_year" required>
                                    <option value="">-- Select Year --</option>
                                    <?php for ($y = date('Y'); $y >= 2000; $y--): ?>
                                        <option value="<?= $y ?>"><?= $y ?></option>
                                    <?php endfor; ?>
                                </select>
                                <div class="invalid-feedback">Select the entry year.</div>
                            </div>

                            <div class="col-12 col-md-6 col-lg-4">
                                <label for="amount" class="form-label">
                                    Amount (PKR) <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">Rs</span>
                                    <input type="number" class="form-control" id="amount" name="amount"
                                        placeholder="0.00" min="0" step="0.01" required>
                                </div>
                                <div class="invalid-feedback">Enter the amount.</div>
                            </div>

                            <div class="col-12 col-lg-8">
                                <label class="form-label d-block">
                                    Status <span class="text-danger">*</span>
                                </label>
                                <div class="status-toggle" role="group">
                                    <input type="radio" class="btn-check" name="status"
                                        id="status_active" value="active" checked>
                                    <label class="status-option status-active" for="status_active">
                                        <i class="bi bi-check-circle-fill"></i>
                                        <span>Active</span>
                                    </label>

                                    <input type="radio" class="btn-check" name="status"
                                        id="status_inactive" value="inactive">
                                    <label class="status-option status-inactive" for="status_inactive">
                                        <i class="bi bi-x-circle-fill"></i>
                                        <span>Not Active</span>
                                    </label>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ============ IRIS CREDENTIALS ============ -->
                <div class="panel mb-4">
                    <div class="panel-header">
                        <h3 class="panel-title">
                            <i class="bi bi-shield-lock me-2 text-primary"></i>IRIS Credentials
                        </h3>
                    </div>
                    <div class="panel-body">
                        <div class="row g-3">

                            <div class="col-12 col-md-6">
                                <label for="iris_email" class="form-label">
                                    IRIS Email <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                                    <input type="email" class="form-control" id="iris_email" name="iris_email"
                                        placeholder="client@iris.gov.pk" required maxlength="190">
                                </div>
                                <div class="invalid-feedback">Enter a valid email address.</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="iris_password" class="form-label">
                                    IRIS Password <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                                    <input type="password" class="form-control" id="iris_password" name="iris_password"
                                        placeholder="Enter password" required minlength="6">
                                    <button class="btn btn-outline-secondary toggle-password" type="button"
                                        data-target="iris_password">
                                        <i class="bi bi-eye-fill"></i>
                                    </button>
                                </div>
                                <small class="form-hint">Min 6 characters. Stored encrypted (AES-256).</small>
                                <div class="invalid-feedback">Password must be at least 6 characters.</div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ============ ACTION BAR ============ -->
                <div class="action-bar">
                    <div class="action-bar-info d-none d-md-flex">
                        <i class="bi bi-info-circle"></i>
                        <span>Fields marked <span class="text-danger">*</span> are required</span>
                    </div>

                    <div class="action-bar-actions">
                        <a href="list.php" class="btn btn-light">
                            <i class="bi bi-x-lg"></i> Cancel
                        </a>
                        <button type="reset" class="btn btn-light">
                            <i class="bi bi-arrow-clockwise"></i> Reset
                        </button>
                        <button type="submit" class="btn btn-primary px-4" id="submitBtn">
                            <span class="spinner-border spinner-border-sm d-none" id="submitSpinner"></span>
                            <i class="bi bi-check-lg" id="submitIcon"></i>
                            <span id="submitText">Save Client</span>
                        </button>
                    </div>
                </div>

            </form>

        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= $assetPath ?>/js/sidebar.js"></script>
    <script src="<?= $assetPath ?>/js/add_client.js"></script>

</body>

</html>