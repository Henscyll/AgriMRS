<?php
include('dastaff_header.php');
require_once '../includes/config.php';

$error_message   = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = trim($_POST['name'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $password     = '123456';
    $province     = trim($_POST['province'] ?? 'Zamboanga del Sur');
    $municipality = trim($_POST['municipality'] ?? '');
    $barangay     = trim($_POST['barangay'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');

    $pres_first   = trim($_POST['president_first_name']  ?? '');
    $pres_mid     = trim($_POST['president_middle_name']  ?? '');
    $pres_last    = trim($_POST['president_last_name']    ?? '');
    $pres_sex     = trim($_POST['president_sex']          ?? '');
    $pres_dob     = trim($_POST['president_dob']          ?? '');
    $pres_age     = intval($_POST['president_age']        ?? 0);
    $pres_prov    = trim($_POST['president_province']     ?? 'Zamboanga del Sur');
    $pres_muni    = trim($_POST['president_municipality'] ?? '');
    $pres_brgy    = trim($_POST['president_barangay']     ?? '');
    $pres_phone   = trim($_POST['president_phone']        ?? '');
    $pres_email   = trim($_POST['president_email']        ?? '');

    // Server-side duplicate checks
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $error_message = 'This association email is already registered.';
        $check->close();
    } else {
        $check->close();

        $check_pres = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_pres->bind_param("s", $pres_email);
        $check_pres->execute();
        $check_pres->store_result();

        if ($check_pres->num_rows > 0) {
            $error_message = 'President email is already registered.';
            $check_pres->close();
        } else {
            $check_pres->close();

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $pres_dob_mysql = null;
            if (!empty($pres_dob)) {
                $dobObj = DateTime::createFromFormat('m/d/Y', $pres_dob);
                if ($dobObj) {
                    $pres_dob_mysql = $dobObj->format('Y-m-d');
                }
            }

            $conn->begin_transaction();

            try {
                // 1. Insert association user account
                $stmt_assoc_user = $conn->prepare(
                    "INSERT INTO users (name, email, password, user_role) VALUES (?, ?, ?, 'associations')"
                );
                $stmt_assoc_user->bind_param("sss", $name, $email, $hashed_password);
                $stmt_assoc_user->execute();
                $assoc_user_id = $conn->insert_id;
                $stmt_assoc_user->close();

                // 2. Insert president user account
                $pres_full_name = trim("$pres_first $pres_mid $pres_last");
                $pres_role      = 'associations';

                $stmt_pres_user = $conn->prepare(
                    "INSERT INTO users (name, email, password, user_role) VALUES (?, ?, ?, ?)"
                );
                $stmt_pres_user->bind_param("ssss", $pres_full_name, $pres_email, $hashed_password, $pres_role);
                $stmt_pres_user->execute();
                $pres_user_id = $conn->insert_id;
                $stmt_pres_user->close();

                // 3. Insert association record
                $stmt_assoc = $conn->prepare(
                    "INSERT INTO associations 
                     (name, email, password, province, municipality, barangay, phone, user_id)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt_assoc->bind_param(
                    "sssssssi",
                    $name, $email, $hashed_password,
                    $province, $municipality, $barangay,
                    $phone, $assoc_user_id
                );
                $stmt_assoc->execute();
                $assoc_id = $conn->insert_id;
                $stmt_assoc->close();

                // 4. Insert president record
                $stmt_pres = $conn->prepare(
                    "INSERT INTO presidents 
                     (user_id, association_id, first_name, middle_name, last_name,
                      sex, date_of_birth, age, province, municipality, barangay, phone, email)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt_pres->bind_param(
                    "iisssssisssss",
                    $pres_user_id, $assoc_id,
                    $pres_first, $pres_mid, $pres_last,
                    $pres_sex, $pres_dob_mysql, $pres_age,
                    $pres_prov, $pres_muni, $pres_brgy,
                    $pres_phone, $pres_email
                );
                $stmt_pres->execute();
                $pres_id = $conn->insert_id;
                $stmt_pres->close();

                // 5. Link president ID to association
                $stmt_update = $conn->prepare(
                    "UPDATE associations SET president_id = ? WHERE id = ?"
                );
                $stmt_update->bind_param("ii", $pres_id, $assoc_id);
                $stmt_update->execute();
                $stmt_update->close();

                $conn->commit();
                $_SESSION['flash_success'] = 'Association added successfully!';
                echo "<script>window.location.href='staff_associations.php';</script>";
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                $error_message = 'Error adding association: ' . $e->getMessage();
            }
        }
    }
}

// Data for real-time validation checks
$existing_assoc_query = $conn->query("SELECT name, email, phone FROM associations");
$existing_assocs = [];
if ($existing_assoc_query) {
    while ($r = $existing_assoc_query->fetch_assoc()) $existing_assocs[] = $r;
}

$existing_pres_query = $conn->query("SELECT first_name, middle_name, last_name, email, phone FROM presidents");
$existing_pres = [];
if ($existing_pres_query) {
    while ($r = $existing_pres_query->fetch_assoc()) $existing_pres[] = $r;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Association | AMRMS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.css">
  <style>
    .main-content {
      height: auto !important;
      padding: 30px 20px;
    }

    .form-card {
      background: #ffffff;
      border-radius: 14px;
      max-width: 920px;
      margin: 0 auto;
      box-shadow: 0 10px 25px rgba(0,0,0,0.08);
      border: 1px solid #e5e7eb;
      overflow: hidden;
    }

    .form-head {
      background: linear-gradient(135deg, #2d7a2d, #1a5c1a);
      padding: 20px 28px;
      color: white;
    }

    .form-head h2 {
      font-size: 22px;
      font-weight: 700;
      margin: 0;
      color: white;
      text-shadow: none;
    }

    .form-head p {
      color: rgba(255,255,255,0.85);
      font-size: 13px;
      margin: 4px 0 0 0;
    }

    .form-body {
      padding: 28px 32px;
    }

    .m-sec {
      font-size: 12px;
      font-weight: 700;
      color: #2d7a2d;
      text-transform: uppercase;
      letter-spacing: .5px;
      margin: 20px 0 12px;
      display: flex;
      align-items: center;
      gap: 8px;
      padding-bottom: 8px;
      border-bottom: 2px solid #e8f5e9;
    }

    .m-sec:first-child { margin-top: 0; }

    .m-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px; }
    .m-row-4 { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 12px; margin-bottom: 12px; }

    .m-grp { display: flex; flex-direction: column; gap: 5px; }
    .m-grp label { font-size: 13px; font-weight: 600; color: #374151; }
    .m-grp label .req { color: #dc2626; }

    .m-wrap { position: relative; }
    .m-wrap .m-ico {
      position: absolute; left: 11px; top: 50%;
      transform: translateY(-50%); color: #9ca3af; font-size: 13px; pointer-events: none;
    }

    .m-grp input, .m-grp select {
      width: 100%; padding: 9px 12px 9px 34px;
      border: 2px solid #e5e7eb; border-radius: 7px;
      font-size: 14px; font-family: inherit; color: #1f2937;
      transition: border-color .2s, background .2s; background: white; box-sizing: border-box;
    }

    .m-grp select { padding-left: 34px; appearance: none; cursor: pointer; }
    .m-grp input:focus, .m-grp select:focus { outline: none; border-color: #2d7a2d; box-shadow: 0 0 0 3px rgba(45,122,45,.1); }

    .field-hint {
      font-size: 0.75rem;
      margin-top: 3px;
      display: none;
      align-items: center;
      gap: 4px;
      font-weight: 500;
    }
    .field-hint.error   { color: #dc2626; display: flex; }
    .field-hint.success { color: #16a34a; display: flex; }

    .form-foot {
      display: flex;
      gap: 12px;
      justify-content: flex-end;
      padding-top: 16px;
      border-top: 1px solid #e5e7eb;
      margin-top: 20px;
    }

    .btn-submit {
      padding: 10px 24px;
      background: #2d7a2d;
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
    }

    .btn-submit:hover:not(:disabled) { background: #256725; }
    .btn-submit:disabled { background-color: #a5d6a5 !important; cursor: not-allowed !important; opacity: 0.6; }

    .btn-cancel {
      padding: 10px 20px;
      background: #f5f5f5;
      color: #666;
      border: 1px solid #e0e0e0;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
    }

    .btn-cancel:hover { background: #e8e8e8; }
    .alert-error {
      background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b;
      padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;
    }
  </style>
</head>
<body>
<div class="main-content">
  <div class="form-card">
    <div class="form-head">
      <h2>Add New Association</h2>
      <p>Fill out the form below to register a new association and president profile</p>
    </div>

    <div class="form-body">
      <?php if (!empty($error_message)): ?>
        <div class="alert-error">
          <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error_message) ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="" id="addAssocForm">
        <div class="m-sec">🏢 Association Information</div>
        <div class="m-row-3">
          <div class="m-grp">
            <label>Association Name <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-users m-ico"></i>
              <input type="text" name="name" id="add_assoc_name" placeholder="Enter association name"
                     value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required oninput="validateAddFormState()">
            </div>
            <span class="field-hint" id="add_assoc_name_hint"></span>
          </div>

          <div class="m-grp">
            <label>Association Email <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-envelope m-ico"></i>
              <input type="email" name="email" id="add_assoc_email" placeholder="email@example.com"
                     value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required oninput="validateAddFormState()">
            </div>
            <span class="field-hint" id="add_assoc_email_hint"></span>
          </div>

          <div class="m-grp">
            <label>Association Phone <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-phone m-ico"></i>
              <input type="text" name="phone" id="add_assoc_phone" placeholder="09XXXXXXXXX" maxlength="11"
                     value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required oninput="validateAddFormState()">
            </div>
            <span class="field-hint" id="add_assoc_phone_hint"></span>
          </div>
        </div>

        <div class="m-row-3">
          <div class="m-grp">
            <label>Province</label>
            <div class="m-wrap">
              <i class="fas fa-map m-ico"></i>
              <input type="text" name="province" value="Zamboanga del Sur" readonly style="background:#f0fdf4; color:#166534; cursor:default; border-color:#86efac;">
            </div>
          </div>

          <div class="m-grp">
            <label>Municipality <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-city m-ico"></i>
              <select name="municipality" id="add_assoc_municipality" required onchange="updateAddBarangays(); validateAddFormState();">
                <option value="" disabled selected hidden>Select Municipality</option>
              </select>
            </div>
          </div>

          <div class="m-grp">
            <label>Barangay <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-home m-ico"></i>
              <select name="barangay" id="add_assoc_barangay" required disabled onchange="validateAddFormState();">
                <option value="" disabled selected hidden>Select Barangay</option>
              </select>
            </div>
          </div>
        </div>

        <div class="m-sec"><i class="fas fa-user-tie"></i> Association President Details</div>
        <div class="m-row-4">
          <div class="m-grp">
            <label>First Name <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-user m-ico"></i>
              <input type="text" name="president_first_name" id="add_pres_first" placeholder="First name"
                     value="<?= htmlspecialchars($_POST['president_first_name'] ?? '') ?>" required oninput="validateAddFormState()">
            </div>
          </div>

          <div class="m-grp">
            <label>Middle Name</label>
            <div class="m-wrap">
              <i class="fas fa-user m-ico"></i>
              <input type="text" name="president_middle_name" id="add_pres_mid" placeholder="Middle name"
                     value="<?= htmlspecialchars($_POST['president_middle_name'] ?? '') ?>" oninput="validateAddFormState()">
            </div>
          </div>

          <div class="m-grp">
            <label>Last Name <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-user m-ico"></i>
              <input type="text" name="president_last_name" id="add_pres_last" placeholder="Last name"
                     value="<?= htmlspecialchars($_POST['president_last_name'] ?? '') ?>" required oninput="validateAddFormState()">
            </div>
          </div>

          <div class="m-grp">
            <label>Sex <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-venus-mars m-ico"></i>
              <select name="president_sex" id="add_pres_sex" required onchange="validateAddFormState()">
                <option value="" disabled selected hidden>Select sex</option>
                <option value="Male"   <?= (($_POST['president_sex'] ?? '') === 'Male')   ? 'selected' : '' ?>>Male</option>
                <option value="Female" <?= (($_POST['president_sex'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
              </select>
            </div>
          </div>
        </div>

        <span class="field-hint" id="add_pres_name_hint" style="margin-bottom:10px;"></span>

        <div class="m-row-4">
          <div class="m-grp">
            <label>Date of Birth <span class="req">*</span></label>
            <div class="m-wrap">
              <input type="text" name="president_dob" id="pres_dob_fp" placeholder="mm/dd/yyyy" readonly required
                     value="<?= htmlspecialchars($_POST['president_dob'] ?? '') ?>" style="cursor:pointer; padding-left:12px;">
            </div>
          </div>

          <div class="m-grp">
            <label>Age</label>
            <div class="m-wrap">
              <i class="fas fa-hashtag m-ico"></i>
              <input type="number" name="president_age" id="pres_age_input" placeholder="Auto-calculated" min="1" max="120" readonly
                     value="<?= htmlspecialchars($_POST['president_age'] ?? '') ?>">
            </div>
          </div>

          <div class="m-grp">
            <label>President Email <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-envelope m-ico"></i>
              <input type="email" name="president_email" id="add_pres_email" placeholder="president@example.com"
                     value="<?= htmlspecialchars($_POST['president_email'] ?? '') ?>" required oninput="validateAddFormState()">
            </div>
            <span class="field-hint" id="add_pres_email_hint"></span>
          </div>

          <div class="m-grp">
            <label>President Phone <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-phone m-ico"></i>
              <input type="text" name="president_phone" id="add_pres_phone" placeholder="09XXXXXXXXX" maxlength="11"
                     value="<?= htmlspecialchars($_POST['president_phone'] ?? '') ?>" required oninput="validateAddFormState()">
            </div>
            <span class="field-hint" id="add_pres_phone_hint"></span>
          </div>
        </div>

        <div class="m-row-3">
          <div class="m-grp">
            <label>Province</label>
            <div class="m-wrap">
              <i class="fas fa-map m-ico"></i>
              <input type="text" name="president_province" value="Zamboanga del Sur" readonly style="background:#f0fdf4; color:#166534; cursor:default; border-color:#86efac;">
            </div>
          </div>

          <div class="m-grp">
            <label>Municipality <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-city m-ico"></i>
              <select name="president_municipality" id="add_pres_municipality" required onchange="updateAddPresBarangays(); validateAddFormState();">
                <option value="" disabled selected hidden>Select Municipality</option>
              </select>
            </div>
          </div>

          <div class="m-grp">
            <label>Barangay <span class="req">*</span></label>
            <div class="m-wrap">
              <i class="fas fa-home m-ico"></i>
              <select name="president_barangay" id="add_pres_barangay" required disabled onchange="validateAddFormState();">
                <option value="" disabled selected hidden>Select Barangay</option>
              </select>
            </div>
          </div>
        </div>

        <div class="m-sec"><i class="fas fa-lock"></i> Login Credentials</div>
        <div style="display:flex; align-items:center; gap:10px; padding:11px 14px; background:#f0fdf4; border:1.5px solid #86efac; border-radius:8px; font-size:13px; color:#166534; margin-bottom:12px;">
          <i class="fas fa-info-circle" style="font-size:15px; color:#2d7a2d;"></i>
          <span>Default password is automatically set to <strong>123456</strong>.</span>
        </div>

        <div class="form-foot">
          <button type="submit" class="btn-submit" id="addSubmitBtn" disabled>Add Association</button>
          <a href="staff_associations.php" class="btn-cancel">Close</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/flatpickr/4.6.13/flatpickr.min.js"></script>
<script>
const existingAssocs = <?= json_encode($existing_assocs) ?>;
const existingPres   = <?= json_encode($existing_pres) ?>;

const zamboangaDelSurData = {
  "Pagadian City": ["Alegria", "Balangasan", "Balintawak", "Baloyboan", "Banale", "Bogo", "Bomba", "Buenavista", "Bulatok", "Bulawan", "Dampalan", "Danlugan", "Dao", "Datagan", "Deborok", "Ditoray", "Dumagoc", "Gatas", "Gubac", "Gubang", "Kagawasan", "Kahayagan", "Kalasan", "Kawit", "La Suerte", "Lala", "Lapidian", "Lenienza", "Lizon Valley", "Lourdes", "Lower Sibatang", "Lumad", "Lumbia", "Macasing", "Manga", "Muricay", "Napolan", "Palpalan", "Pedulonan", "Poloyagan", "San Francisco", "San Jose", "San Pedro", "Santa Lucia", "Santa Maria", "Santiago", "Santo Niño", "Tawagan Sur", "Tiguma", "Tuburan", "Tulangan", "Tulawas", "Upper Sibatang", "White Beach"],
  "Aurora": ["Acad", "Alang-alang", "Alegria", "Anonang", "Bagong Mandaue", "Bagong Maslog", "Bagong Oslob", "Bagong Pitogo", "Baki", "Balas", "Balide", "Balintawak", "Bayabas", "Bemposa", "Cabilinan", "Campo Uno", "Ceboneg", "Commonwealth", "Gubaan", "Inasagan", "Inroad", "Kahayagan East (Katipunan)", "Kahayagan West", "Kauswagan", "La Paz (Tinibtiban)", "La Victoria", "Lantungan", "Libertad", "Lintugop", "Lubid", "Maguikay", "Mahayahay", "Monte Alegre", "Montela", "Napo", "Panaghiusa", "Poblacion", "Resthouse", "Romarate", "San Jose", "San Juan", "Sapa Loboc", "Tagulalo", "Waterfall"],
  "Bayog": ["Baking", "Balukbahan", "Balumbunan", "Bantal", "Bobuan", "Camp Blessing", "Canoayan", "Conacon", "Dagum", "Damit", "Datagan", "Depase", "Depili", "Depore", "Deporehan", "Dimalinao", "Kahayagan", "Kanipaan", "Lamare", "Liba", "Matin-ao", "Matun-og", "Pangi (San Isidro)", "Poblacion", "Pulang Bato", "Salawagan", "Sigacad", "Supon"],
  "Dimataling": ["Bacayawan", "Baha", "Balanagan", "Baluno", "Binuay", "Buburay", "Grap", "Josefina", "Kagawasan", "Lalab", "Libertad", "Magahis", "Mahayag", "Mercedes", "Poblacion", "Saloagan", "San Roque", "Sugbay Uno", "Sumbato", "Sumpot", "Tinggabulong", "Tiniguangan", "Tipangi", "Upper Ludiong"],
  "Dinas": ["Bacawan", "Benuatan", "Beray", "Don Jose", "Dongos", "East Migpulao", "Guinicolalay", "Ignacio Garrata (New Mirapao)", "Kinacap", "Legarda 1", "Legarda 2", "Legarda 3", "Lower Dimaya", "Lucoban", "Ludiong", "Nangka", "Nian", "Old Mirapao", "Pisa-an", "Poblacion", "Proper Dimaya", "Sagacad", "Sambulawan", "San Isidro", "Songayan", "Sumpotan", "Tarakan", "Upper Dimaya", "Upper Sibul", "West Migpulao"],
  "Dumalinao": ["Anonang", "Bag-ong Misamis", "Bag-ong Silao", "Baga", "Baloboan", "Banta-ao", "Bibilik", "Calingayan", "Camalig", "Camanga", "Cuatro-cuatro", "Locuban", "Malasik", "Mama (San Juan)", "Matab-ang", "Mecolong", "Metokong", "Motosawa", "Pag-asa (Poblacion)", "Paglaum (Poblacion)", "Pantad", "Piniglibano", "Rebokon", "San Agustin", "Sibucao", "Sumadat", "Tikwas", "Tina", "Tubo-Pait", "Upper Dumalinao"],
  "Dumingag": ["Bag-ong Valencia", "Bagong Kauswagan", "Bagong Silang", "Bucayan", "Calumanggi", "Canibong", "Caridad", "Danlugan", "Dapiwak", "Datu Totocan", "Dilud", "Ditulan", "Dulian", "Dulop", "Guintananan", "Guitran", "Gumpingan", "La Fortuna", "Labangon", "Libertad", "Licabang", "Lipawan", "Lower Landing", "Lower Timonan", "Macasing", "Mahayahay", "Malagalad", "Manlabay", "Maralag", "Marangan", "New Basak", "Saad", "Salvador", "San Juan", "San Pablo (Poblacion)", "San Pedro (Poblacion)", "San Vicente", "Senote", "Sinonok", "Sunop", "Tagun", "Tamurayan", "Upper Landing", "Upper Timonan"],
  "Guipos": ["Bagong Oroquieta", "Baguitan", "Balongating", "Canunan", "Dacsol", "Dagohoy", "Dalapang", "Datagan", "Poblacion", "Guling", "Katipunan", "Lintum", "Litan", "Magting", "Regla", "Sikatuna", "Singclot"],
  "Josefina": ["Bogo Calabat", "Dawa", "Ebarle", "Gumahan", "Leonardo", "Litapan", "Lower Bagong Tudela", "Mansanas", "Moradji", "Nemeño", "Nopulan", "Sebukang", "Tagaytay Hill", "Upper Bagong Tudela"],
  "Kumalarang": ["Bogayo", "Bolisong", "Boyugan East", "Boyugan West", "Bualan", "Diplo", "Gawil", "Gusom", "Kitaan Dagat", "Lantawan", "Limamawan", "Mahayahay", "Pangi", "Picanan", "Poblacion", "Salagmanok", "Secade", "Suminalum"],
  "Labangan": ["Bagalupa", "Balimbingan", "Binayan", "Bokong", "Bulanit", "Cogonan", "Combo", "Dalapang", "Dimasangca", "Dipaya", "Langapod", "Lantian", "Lower Campo Islam", "Lower Pulacan", "Lower Sang-an", "New Labangan", "Noboran", "Old Labangan", "San Isidro", "Santa Cruz", "Tapodoc", "Tawagan Norte", "Upper Campo Islam", "Upper Pulacan", "Upper Sang-an"],
  "Lakewood": ["Baking", "Bagong Kahayag", "Biswangan", "Bululawan", "Dagum", "Gasa", "Gatub", "Poblacion", "Lukuan", "Matalang", "Sapang Pinoles", "Sebuguey", "Tiwales", "Tubod"],
  "Lapuyan": ["Bulawan", "Carpoc", "Danganan", "Dansal", "Dumara", "Linokmadalum", "Luanan", "Lubusan", "Mahalingeb", "Mandeg", "Maralag", "Maruing", "Molum", "Pampang", "Pantad", "Pingalay", "Poblacion", "Salambuyan", "San Jose", "Sayog", "Tabon", "Talabob", "Tiguha", "Tininghalang", "Tipasan", "Tugaya"],
  "Mahayag": ["Bag-ong Balamban", "Bag-ong Dalaguete", "Boniao", "Delusom", "Diwan", "Guripan", "Kaangayan", "Kabuhi", "Lourmah", "Lower Salug Daku", "Lower Santo Niño", "Malubo", "Manguiles", "Marabanan", "Panagaan", "Paraiso", "Pedagan", "Poblacion", "Pugwan", "San Isidro", "San Jose", "San Vicente", "Santa Cruz", "Sicpao", "Tuboran", "Tulan", "Tumapic", "Upper Salug Daku", "Upper Santo Niño"],
  "Margosatubig": ["Balintawak", "Bularong", "Digon", "Guinimanan", "Igat Island", "Josefina", "Kalian", "Kolot", "Limbatong", "Limamawan", "Lumbog", "Magahis", "Poblacion", "Sagua", "Talanusa", "Tiguian", "Tulapok"],
  "Midsalip": ["Bacahan", "Balonai", "Bibilop", "Buloron", "Cabaloran", "Canipay Norte", "Canipay Sur", "Cumaron", "Dakayakan", "Duelic", "Dumalinao", "Ecuan", "Golictop", "Guinabot", "Guitalos", "Guma", "Kahayagan", "Licuro-an", "Lumpunid", "Matalang", "New Katipunan", "New Unidos", "Palili", "Pawan", "Pili", "Pisompongan", "Piwan", "Poblacion A", "Poblacion B", "Sigapod", "Timbaboy", "Tulbong", "Tuluan"],
  "Molave": ["Alicia", "Ariosa", "Bagong Argao", "Bagong Gutlang", "Blancia", "Bogo Capalaran", "Culo", "Dalaon", "Dipolo", "Dontulan", "Gonosan", "Lower Dimalinao", "Lower Dimorok", "Mabuhay", "Madasigon", "Makuguihon", "Maloloy-on", "Miligan", "Parasan", "Rizal", "Santo Rosario", "Silangit", "Simata", "Sudlon", "Upper Dimorok"],
  "Pitogo": ["Balabawan", "Balong-balong", "Colojo", "Liasan", "Liguac", "Limbayan", "Lower Paniki-an", "Matin-ao", "Panubigan", "Poblacion", "Punta Flecha", "Sugbay Dos", "Tongao", "Upper Paniki-an"],
  "Ramon Magsaysay": ["Bagong Opon", "Bambong Daku", "Bambong Diut", "Bobongan", "Campo IV", "Campo V", "Caniangan", "Dipalusan", "Eastern Bobongan", "Esperanza", "Gapasan", "Katipunan", "Kauswagan", "Lower Sambulawan", "Mabini", "Magsaysay", "Malating", "Paradise", "Pasingkalan", "Poblacion", "San Fernando", "Santo Rosario", "Sapa Anding", "Sinaguing", "Switch", "Upper Laperian", "Wakat"],
  "San Miguel": ["Betinan", "Bulawan", "Calube", "Concepcion", "Dao-an", "Dumalian", "Fatima", "Langilan", "Lantawan", "Laperian", "Libuganan", "Limonan", "Mati", "Ocapan", "Poblacion", "San Isidro", "Sayog", "Tapian"],
  "San Pablo": ["Bag-ong Misamis", "Bubual", "Buton", "Culasian", "Daplayan", "Kalilangan", "Kapamanok", "Kondum", "Lumbayao", "Mabuhay", "Marcos Village", "Miasin", "Molansong", "Pantad", "Pao", "Payag", "Poblacion", "Pongapong", "Sacbulan", "Sagasan", "San Juan", "Senior", "Songgoy", "Tandubuay", "Taniapan", "Ticala Island", "Tubo-pait", "Villakapa"],
  "Sominot": ["Bag-ong Baroy", "Bag-ong Oroquieta", "Barubuhan", "Bulanay", "Datagan", "Eastern Poblacion", "Lantawan", "Libertad", "Lumangoy", "New Carmen", "Picturan", "Poblacion", "Rizal", "San Miguel", "Santo Niño", "Sawa", "Tungawan", "Upper Sicpao"],
  "Tabina": ["Abong-abong", "Baganian", "Baya-baya", "Capisan", "Concepcion", "Culabay", "Doña Josefina", "Lumbia", "Mabuhay", "Malim", "Manikaan", "New Oroquieta", "Poblacion", "San Francisco", "Tultolan"],
  "Tambulig": ["Alang-alang", "Angeles", "Bag-ong Kauswagan", "Bag-ong Tabogon", "Balugo", "Cabgan", "Calolot", "Dimalinao", "Fabian", "Gabunon", "Happy Valley", "Kapalaran", "Libato", "Limamawan", "Lower Liasan", "Lower Lodiong", "Lower Tiparak", "Lower Usogan", "Maya-maya", "New Village", "Pelocoban", "Riverside", "Sagrada Familia", "San Jose", "San Vicente", "Sumalig", "Tuluan", "Tungawan", "Upper Liason", "Upper Lodiong", "Upper Tiparak"],
  "Tigbao": ["Begong", "Busol", "Caluma", "Diana Countryside", "Guinlin", "Lacarayan", "Lacupayan", "Libayoy", "Limas", "Longmot", "Maragang", "Mate", "Nangan-nangan", "New Tuburan", "Nilo", "Tigbao", "Timolan", "Upper Nilo"],
  "Tukuran": ["Alindahaw", "Baclay", "Balimbingan", "Buenasuerte", "Camanga", "Curvada", "Laperian", "Libertad", "Lower Bayao", "Luy-a", "Manilan", "Manlayag", "Militar", "Navalan", "Panduma Senior", "Sambulawan", "San Antonio", "San Carlos", "Santo Niño", "Santo Rosario", "Sugod", "Tabuan", "Tagulo", "Tinotungan", "Upper Bayao"],
  "Vincenzo A. Sagun": ["Bui-os", "Cogon", "Danan", "Kabatan", "Kapatagan", "Limason", "Linoguayan", "Lumbal", "Lunib", "Maculay", "Maraya", "Sagucan", "Waling-waling", "Ambulon"]
};

function populateMunicipalitySelects() {
  const selects = ['add_assoc_municipality', 'add_pres_municipality'];
  selects.forEach(id => {
    const el = document.getElementById(id);
    if(!el) return;
    el.innerHTML = '<option value="" disabled selected hidden>Select Municipality</option>';
    Object.keys(zamboangaDelSurData).sort().forEach(muni => {
      const opt = document.createElement('option');
      opt.value = muni; opt.textContent = muni;
      el.appendChild(opt);
    });
  });
}

function updateAddBarangays() {
  const muni = document.getElementById('add_assoc_municipality').value;
  const bgy = document.getElementById('add_assoc_barangay');
  bgy.innerHTML = '<option value="" disabled selected hidden>Select Barangay</option>';
  if (muni && zamboangaDelSurData[muni]) {
    bgy.disabled = false;
    zamboangaDelSurData[muni].sort().forEach(item => {
      const opt = document.createElement('option');
      opt.value = item; opt.textContent = item;
      bgy.appendChild(opt);
    });
  } else { bgy.disabled = true; }
}

function updateAddPresBarangays() {
  const muni = document.getElementById('add_pres_municipality').value;
  const bgy = document.getElementById('add_pres_barangay');
  bgy.innerHTML = '<option value="" disabled selected hidden>Select Barangay</option>';
  if (muni && zamboangaDelSurData[muni]) {
    bgy.disabled = false;
    zamboangaDelSurData[muni].sort().forEach(item => {
      const opt = document.createElement('option');
      opt.value = item; opt.textContent = item;
      bgy.appendChild(opt);
    });
  } else { bgy.disabled = true; }
}

function setFieldError(input, hint, msg) {
  input.style.borderColor = '#dc2626'; input.style.background = '#fef2f2';
  if (hint) { hint.className = 'field-hint error'; hint.textContent = msg; }
}

function setFieldOk(input, hint, msg) {
  input.style.borderColor = '#16a34a'; input.style.background = '#f0fdf4';
  if (hint) { hint.className = 'field-hint success'; hint.textContent = msg; }
}

function resetField(input, hint) {
  input.style.borderColor = ''; input.style.background = '';
  if (hint) { hint.className = 'field-hint'; hint.textContent = ''; }
}

function validateAddFormState() {
  const btn = document.getElementById('addSubmitBtn');
  
  const nameInput = document.getElementById('add_assoc_name');
  const emailInput = document.getElementById('add_assoc_email');
  const phoneInput = document.getElementById('add_assoc_phone');
  const muniInput = document.getElementById('add_assoc_municipality');
  const brgyInput = document.getElementById('add_assoc_barangay');

  const presFirst = document.getElementById('add_pres_first');
  const presMid   = document.getElementById('add_pres_mid');
  const presLast  = document.getElementById('add_pres_last');
  const presSex   = document.getElementById('add_pres_sex');
  const presDob   = document.getElementById('pres_dob_fp');
  const presEmail = document.getElementById('add_pres_email');
  const presPhone = document.getElementById('add_pres_phone');
  const presMuni  = document.getElementById('add_pres_municipality');
  const presBrgy  = document.getElementById('add_pres_barangay');

  const nameHint  = document.getElementById('add_assoc_name_hint');
  const emailHint = document.getElementById('add_assoc_email_hint');
  const phoneHint = document.getElementById('add_assoc_phone_hint');
  const presNameHint  = document.getElementById('add_pres_name_hint');
  const presEmailHint = document.getElementById('add_pres_email_hint');
  const presPhoneHint = document.getElementById('add_pres_phone_hint');

  let valid = true;
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

  // Check Assoc Name
  const nameVal = nameInput.value.trim().toLowerCase();
  if (!nameVal) { resetField(nameInput, nameHint); valid = false; }
  else if (existingAssocs.some(a => a.name.toLowerCase() === nameVal)) {
    setFieldError(nameInput, nameHint, 'Association name has already been taken.'); valid = false;
  } else { setFieldOk(nameInput, nameHint, ''); }

  // Check Assoc Email
  const emailVal = emailInput.value.trim().toLowerCase();
  if (!emailVal) { resetField(emailInput, emailHint); valid = false; }
  else if (!emailRegex.test(emailVal)) {
    setFieldError(emailInput, emailHint, 'Enter a valid email'); valid = false;
  } else if (existingAssocs.some(a => a.email.toLowerCase() === emailVal) || existingPres.some(p => p.email.toLowerCase() === emailVal)) {
    setFieldError(emailInput, emailHint, 'Email has already been taken.'); valid = false;
  } else { setFieldOk(emailInput, emailHint, ''); }

  // Check Assoc Phone
  let phoneVal = phoneInput.value.replace(/\D/g, '').substring(0, 11);
  phoneInput.value = phoneVal;
  if (!phoneVal) { resetField(phoneInput, phoneHint); valid = false; }
  else if (!phoneVal.startsWith('09') || phoneVal.length < 11) {
    setFieldError(phoneInput, phoneHint, 'Must be 11 digits starting with 09'); valid = false;
  } else if (existingAssocs.some(a => a.phone === phoneVal) || existingPres.some(p => p.phone === phoneVal)) {
    setFieldError(phoneInput, phoneHint, 'Phone number has already been taken.'); valid = false;
  } else { setFieldOk(phoneInput, phoneHint, ''); }

  if (!muniInput.value) valid = false;
  if (!brgyInput.value) valid = false;

  // Check Pres Name
  const pf = presFirst.value.trim().toLowerCase();
  const pm = presMid.value.trim().toLowerCase();
  const pl = presLast.value.trim().toLowerCase();
  if (!pf || !pl) { resetField(presFirst, presNameHint); valid = false; }
  else if (existingPres.some(p => p.first_name.toLowerCase() === pf && (p.middle_name||'').toLowerCase() === pm && p.last_name.toLowerCase() === pl)) {
    setFieldError(presFirst, presNameHint, 'President name has already been taken.'); valid = false;
  } else { setFieldOk(presFirst, presNameHint, ''); }

  if (!presSex.value) valid = false;
  if (!presDob.value) valid = false;

  // Check Pres Email
  const pEmailVal = presEmail.value.trim().toLowerCase();
  if (!pEmailVal) { resetField(presEmail, presEmailHint); valid = false; }
  else if (!emailRegex.test(pEmailVal)) {
    setFieldError(presEmail, presEmailHint, 'Enter a valid email'); valid = false;
  } else if (existingAssocs.some(a => a.email.toLowerCase() === pEmailVal) || existingPres.some(p => p.email.toLowerCase() === pEmailVal)) {
    setFieldError(presEmail, presEmailHint, 'Email has already been taken.'); valid = false;
  } else { setFieldOk(presEmail, presEmailHint, ''); }

  // Check Pres Phone
  let pPhoneVal = presPhone.value.replace(/\D/g, '').substring(0, 11);
  presPhone.value = pPhoneVal;
  if (!pPhoneVal) { resetField(presPhone, presPhoneHint); valid = false; }
  else if (!pPhoneVal.startsWith('09') || pPhoneVal.length < 11) {
    setFieldError(presPhone, presPhoneHint, 'Must be 11 digits starting with 09'); valid = false;
  } else if (existingAssocs.some(a => a.phone === pPhoneVal) || existingPres.some(p => p.phone === pPhoneVal)) {
    setFieldError(presPhone, presPhoneHint, 'Phone number has already been taken.'); valid = false;
  } else { setFieldOk(presPhone, presPhoneHint, ''); }

  if (!presMuni.value) valid = false;
  if (!presBrgy.value) valid = false;

  btn.disabled = !valid;
}

document.addEventListener('DOMContentLoaded', () => {
  populateMunicipalitySelects();

  flatpickr('#pres_dob_fp', {
    dateFormat: 'm/d/Y',
    maxDate: 'today',
    allowInput: false,
    onChange: function(dates) {
      if (dates.length) {
        const dob   = dates[0];
        const today = new Date();
        let age = today.getFullYear() - dob.getFullYear();
        const m = today.getMonth() - dob.getMonth();
        if (m < 0 || (m === 0 && today.getDate() < dob.getDate())) age--;
        document.getElementById('pres_age_input').value = age >= 0 ? age : '';
      } else {
        document.getElementById('pres_age_input').value = '';
      }
      validateAddFormState();
    }
  });

  validateAddFormState();
});
</script>
</body>
</html>