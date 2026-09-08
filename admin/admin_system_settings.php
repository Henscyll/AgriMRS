<?php
// 1. AJAX BACKEND HANDLER (Must be placed BEFORE any HTML output or includes that echo code)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    // Suppress errors from breaking JSON structure
    error_reporting(0);
    ini_set('display_errors', 0);

    require_once '../includes/config.php';
    
    // Clear any output buffer caused by included files
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $machine_id = intval($_POST['machine_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if ($machine_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid machine selected.']);
        exit;
    }

    if ($_POST['action'] === 'add') {
        // Check if machine already has a description
        $check = $conn->prepare("SELECT description FROM machines WHERE id = ?");
        $check->bind_param("i", $machine_id);
        $check->execute();
        $res = $check->get_result()->fetch_assoc();
        $check->close();

        if (!empty($res['description'])) {
            echo json_encode(['status' => 'exists', 'message' => 'Machine already exists in System Settings!']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE machines SET description = ? WHERE id = ?");
        $stmt->bind_param("si", $description, $machine_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Machine added successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to add machine listing.']);
        }
        $stmt->close();
        exit;
    } elseif ($_POST['action'] === 'edit') {
        $stmt = $conn->prepare("UPDATE machines SET description = ? WHERE id = ?");
        $stmt->bind_param("si", $description, $machine_id);
        if ($stmt->execute()) {
            echo json_encode(['status' => 'success', 'message' => 'Machine updated successfully!']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Failed to update machine listing.']);
        }
        $stmt->close();
        exit;
    }
}

// 2. NORMAL PAGE LOAD
include('dashboard_itadmin.php');
require_once '../includes/config.php';

// Retrieve all machines with association & address details
$db_machines_query = "
    SELECT m.*, 
           a.name AS association_name,
           CONCAT_WS(', ', NULLIF(a.barangay, ''), NULLIF(a.municipality, ''), NULLIF(a.province, '')) AS address
    FROM machines m 
    LEFT JOIN associations a ON m.association_id = a.id
    ORDER BY m.machine_name ASC
";
$db_machines_res = $conn->query($db_machines_query);
$db_machines = [];
if ($db_machines_res && $db_machines_res->num_rows > 0) {
    while ($row = $db_machines_res->fetch_assoc()) {
        $db_machines[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>System Settings | AMRMS</title>
<style>
* { box-sizing: border-box; }
:root {
  --green-dark: #1b5e20;
  --green-btn: #2d7a2d;
  --green-hover: #1a5c1a;
  --green-pale: #e8f5e9;
  --white: #fff;
  --gray-bg: #ffffff;
  --gray-border: #ddd;
  --gray-text: #666;
  --text: #222;
  --radius: 6px;
}
body { font-family: 'Segoe UI', Arial, sans-serif; background: var(--gray-bg); color: var(--text); font-size: 14px; }

.main-content { height: auto !important; padding: 60px 20px; }

.page-body { max-width: 900px; margin: 0 auto; padding: 0 20px; }

/* ── GALLERY & DROPDOWNS ── */
.gallery-row { display: flex; justify-content: center; position: relative; }
.gallery-btn {
  background: var(--white);
  border: 1px solid var(--gray-border);
  border-radius: var(--radius);
  padding: 9px 32px;
  font-size: 14px;
  font-weight: 600;
  color: var(--text);
  cursor: pointer;
  box-shadow: 0 1px 3px rgba(0,0,0,0.08);
  min-width: 220px;
  text-align: center;
}
.gallery-btn:hover { border-color: var(--green-btn); }

.service-dropdown {
  display: none;
  position: absolute;
  top: 100%; left: 50%;
  transform: translateX(-50%);
  background: var(--white);
  border: 1px solid var(--gray-border);
  border-top: none;
  border-radius: 0 0 var(--radius) var(--radius);
  box-shadow: 0 4px 14px rgba(0,0,0,0.12);
  min-width: 240px;
  z-index: 50;
}
.service-dropdown.open { display: block; }
.service-option { padding: 9px 16px; font-size: 13px; cursor: pointer; border-bottom: 1px solid #f0f0f0; }
.service-option:hover { background: var(--green-pale); color: var(--green-dark); }
.service-option.selected { background: var(--green-btn); color: #fff; }

.assoc-row { display: none; justify-content: center; margin-top: 14px; position: relative; }
.assoc-row.visible { display: flex; }
.assoc-btn {
  background: var(--white);
  border: 1px solid var(--gray-border);
  border-radius: var(--radius);
  padding: 8px 36px 8px 14px;
  font-size: 13px;
  cursor: pointer;
  min-width: 200px;
  text-align: left;
  font-weight: 500;
  position: relative;
}
.assoc-btn::after { content: '▾'; position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: var(--gray-text); }
.assoc-dropdown {
  display: none;
  position: absolute;
  top: 100%; left: 0;
  background: var(--white);
  border: 1px solid var(--gray-border);
  border-top: none;
  border-radius: 0 0 var(--radius) var(--radius);
  box-shadow: 0 4px 14px rgba(0,0,0,0.12);
  min-width: 200px;
  z-index: 50;
}
.assoc-dropdown.open { display: block; }
.assoc-option { padding: 9px 14px; font-size: 13px; cursor: pointer; border-bottom: 1px solid #f0f0f0; }
.assoc-option:hover { background: var(--green-pale); color: var(--green-dark); }
.assoc-option.selected { background: var(--green-btn); color: #fff; }

/* ── CARDS SECTION ── */
.cards-section { margin-top: 24px; display: none; }
.cards-section.visible { display: block; }
.cards-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.cards-heading { font-size: 13px; color: var(--gray-text); }
.cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; margin-bottom: 28px; }

.machine-card {
  background: var(--white);
  border: 2px solid var(--gray-border);
  border-radius: var(--radius);
  overflow: hidden;
  box-shadow: 0 1px 4px rgba(0,0,0,0.07);
  transition: all 0.2s;
  cursor: pointer;
  position: relative;
  user-select: none;
}
.machine-card:hover { box-shadow: 0 4px 16px rgba(0,0,0,0.13); transform: translateY(-2px); }
.machine-card.selected-card { border-color: var(--green-btn); background-color: #f4fbf4; }
.selected-badge { display: none; position: absolute; top: 7px; left: 7px; background: var(--green-btn); color: white; font-size: 10px; font-weight: bold; padding: 2px 6px; border-radius: 10px; z-index: 2; }
.machine-card.selected-card .selected-badge { display: block; }

.card-img-box { height: 120px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
.card-img-box img { width: 100%; height: 100%; object-fit: cover; }
.card-status { position: absolute; top: 7px; right: 7px; font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 10px; text-transform: uppercase; }
.status-active { background: #c8e6c9; color: #1b5e20; }
.status-inactive { background: #ffcdd2; color: #b71c1c; }
.status-maintenance { background: #fff9c4; color: #f57f17; }

.card-body { padding: 10px 12px; }
.card-name { font-weight: 700; font-size: 13px; color: var(--text); margin-bottom: 3px; }
.card-meta { font-size: 11px; color: var(--gray-text); line-height: 1.4; }

/* TRUNCATED DESCRIPTION FORMAT */
.card-desc {
  font-size: 11px;
  color: #555;
  margin-top: 6px;
  font-style: italic;
  background: #f9f9f9;
  padding: 4px 6px;
  border-radius: 4px;
  border-left: 2px solid var(--green-btn);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
  max-height: 38px;
}

.card-price { margin-top: 8px; padding-top: 7px; border-top: 1px solid #eee; font-size: 12px; color: var(--gray-text); }
.card-price strong { color: var(--green-dark); font-size: 13px; }

/* ── ACTIONS ── */
.bottom-actions { display: flex; justify-content: center; gap: 16px; padding-top: 20px; border-top: 1px solid var(--gray-border); }
.btn { padding: 9px 40px; border-radius: var(--radius); font-size: 14px; font-weight: 600; cursor: pointer; border: none; }
.btn-green { background: var(--green-btn); color: #fff; }
.btn-green:hover:not(:disabled) { background: var(--green-hover); }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

/* ── FORM MODALS ── */
.overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 1200; align-items: center; justify-content: center; }
.overlay.open { display: flex; }
.modal { background: var(--white); border-radius: 10px; width: 480px; max-width: 96vw; box-shadow: 0 8px 32px rgba(0,0,0,0.18); overflow: hidden; }
.modal-head { background: var(--green-btn); color: #fff; padding: 14px 18px; font-size: 15px; font-weight: 600; }
.modal-body { padding: 20px; display: flex; flex-direction: column; gap: 11px; max-height: 75vh; overflow-y: auto; }
.field-group { display: flex; flex-direction: column; gap: 4px; }
.field-label { font-size: 11px; font-weight: 600; color: var(--gray-text); }
.field-input { border: 1px solid var(--gray-border); border-radius: var(--radius); padding: 8px 11px; font-size: 13px; outline: none; width: 100%; }
.field-input[readonly] { background-color: #f5f5f5; color: #666; cursor: not-allowed; }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.modal-foot { padding: 12px 18px; border-top: 1px solid var(--gray-border); display: flex; justify-content: flex-end; gap: 8px; background: #fafafa; }
.btn-cancel { padding: 7px 20px; border-radius: var(--radius); border: 1px solid var(--gray-border); background: var(--white); font-size: 13px; cursor: pointer; color: var(--gray-text); }
.btn-save { padding: 7px 22px; border-radius: var(--radius); border: none; background: var(--green-btn); color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; }
.btn-save:disabled { opacity: 0.5; cursor: not-allowed; }

.img-preview-box { border: 2px dashed var(--gray-border); border-radius: var(--radius); height: 110px; display: flex; align-items: center; justify-content: center; background: #fafafa; overflow: hidden; }
.img-preview-box img { width: 100%; height: 100%; object-fit: cover; }

/* ── POP-UP MODAL (ABOUT US STYLE) ── */
.response-modal {
    display: none;
    position: fixed;
    z-index: 13000;
    inset: 0;
    backdrop-filter: blur(4px);
    background-color: rgba(0,0,0,0.4);
    justify-content: center;
    align-items: center;
}
.response-modal-content {
    background: #fff;
    border-radius: 16px;
    padding: 30px 25px;
    width: 90%;
    max-width: 400px;
    text-align: center;
    box-shadow: 0 8px 25px rgba(0,0,0,0.2);
    position: relative;
    animation: fadeIn 0.25s ease-in-out;
}
.response-modal-content p {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 20px;
    color: #333;
}
.response-modal-content button {
    background-color: #2d7a2d;
    color: white;
    border: none;
    padding: 8px 30px;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
}
.response-modal-content button:hover { background-color: #256725; }

/* ── FULLSCREEN LIGHTBOX ── */
.lightbox-modal {
    display: none;
    position: fixed;
    z-index: 14000;
    inset: 0;
    background: rgba(0,0,0,0.85);
    justify-content: center;
    align-items: center;
}
.lightbox-modal.open { display: flex; }
.lightbox-modal img { max-width: 90vw; max-height: 90vh; border-radius: 8px; box-shadow: 0 0 20px rgba(0,0,0,0.5); object-fit: contain; }

@keyframes fadeIn {
    from {opacity: 0; transform: translateY(-15px);}
    to {opacity: 1; transform: translateY(0);}
}
.empty { text-align: center; padding: 50px 20px; color: #bbb; font-size: 13px; grid-column: 1/-1; }
</style>
</head>
<body>

<div class="main-content">

  <div class="page-body">

    <!-- STEP 1: FARMER GALLERY DROPDOWN -->
    <div class="gallery-row" id="galleryRow">
      <div style="position:relative; display:inline-block;">
        <button class="gallery-btn" id="galleryBtn" onclick="toggleGallery()">Farmer Gallery</button>
        <div class="service-dropdown" id="serviceDropdown">
          <div class="service-option" onclick="selectService(this, 'Farmer Services Harvester', 'Harvester')">Farmer Services Harvester</div>
          <div class="service-option" onclick="selectService(this, 'Farmer Services Tractor', 'Tractor')">Farmer Services Tractor</div>
        </div>
      </div>
    </div>

    <!-- STEP 2: ASSOCIATION DROPDOWN -->
    <div class="assoc-row" id="assocRow">
      <div class="assoc-select-wrap" style="position:relative;">
        <button class="assoc-btn" id="assocBtn" onclick="toggleAssoc()">Association</button>
        <div class="assoc-dropdown" id="assocDropdown"></div>
      </div>
    </div>

    <!-- STEP 3: CARDS SECTION -->
    <div class="cards-section" id="cardsSection">
      <div class="cards-header">
        <div class="cards-heading" id="cardsHeading">Showing machines</div>
      </div>
      <div class="cards-grid" id="cardsGrid"></div>
      <div class="bottom-actions">
        <button class="btn btn-green" onclick="openAddModal()">Add</button>
        <button class="btn btn-green" id="btnEdit" onclick="openEditModal()" disabled>Edit</button>
      </div>
    </div>

  </div>
</div>

<!-- ADD MACHINE MODAL -->
<div class="overlay" id="addModal">
  <div class="modal">
    <div class="modal-head">Add Machine</div>
    <form id="addMachineForm" onsubmit="submitAddForm(event)">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="machine_id" id="addMachineId">

      <div class="modal-body">
        <div class="field-group">
          <label class="field-label">SELECT MACHINE *</label>
          <select class="field-input" id="addSelectMachine" onchange="handleSelectExistingMachine(this.value)" required>
            <option value="" disabled selected hidden>Select Machine</option>
          </select>
        </div>

        <div class="field-row">
          <div class="field-group">
            <label class="field-label">MACHINE NAME</label>
            <input class="field-input" type="text" id="addName" readonly>
          </div>
          <div class="field-group">
            <label class="field-label">MACHINE TYPE</label>
            <input class="field-input" type="text" id="addType" readonly>
          </div>
        </div>

        <div class="field-row">
          <div class="field-group">
            <label class="field-label">PRICE / HECTARE</label>
            <input class="field-input" type="text" id="addRate" readonly>
          </div>
          <div class="field-group">
            <label class="field-label">OPERATOR RATE / HECTARE</label>
            <input class="field-input" type="text" id="addOpRate" readonly>
          </div>
        </div>

        <div class="field-row">
          <div class="field-group">
            <label class="field-label">ASSOCIATION</label>
            <input class="field-input" type="text" id="addAssoc" readonly>
          </div>
          <div class="field-group">
            <label class="field-label">STATUS</label>
            <input class="field-input" type="text" id="addStatus" readonly>
          </div>
        </div>

        <div class="field-group">
          <label class="field-label">ADDRESS</label>
          <input class="field-input" type="text" id="addAddress" readonly>
        </div>

        <div class="field-group">
          <label class="field-label">DESCRIPTION *</label>
          <textarea class="field-input" name="description" id="addDesc" rows="3" placeholder="Enter listing description..." oninput="validateAddForm()" required></textarea>
        </div>

        <div class="field-group">
          <label class="field-label">MACHINE PHOTO</label>
          <div class="img-preview-box" id="addImgPreview">
             <span style="color:#aaa; font-size:12px;">No Machine Image Selected</span>
          </div>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn-save" type="submit" id="btnAddSubmit" disabled>Add</button>
        <button class="btn-cancel" type="button" onclick="closeModal('addModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT MACHINE MODAL -->
<div class="overlay" id="editModal">
  <div class="modal">
    <div class="modal-head">Edit Machine</div>
    <form id="editMachineForm" onsubmit="submitEditForm(event)">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" name="machine_id" id="editMachineId">

      <div class="modal-body">
        <div class="field-row">
          <div class="field-group">
            <label class="field-label">MACHINE NAME</label>
            <input class="field-input" type="text" id="editName" readonly>
          </div>
          <div class="field-group">
            <label class="field-label">STATUS</label>
            <input class="field-input" type="text" id="editStatus" readonly>
          </div>
        </div>
        <div class="field-row">
          <div class="field-group">
            <label class="field-label">PRICE / HECTARE</label>
            <input class="field-input" type="text" id="editRate" readonly>
          </div>
          <div class="field-group">
            <label class="field-label">OPERATOR RATE / HECTARE</label>
            <input class="field-input" type="text" id="editOpRate" readonly>
          </div>
        </div>
        <div class="field-group">
          <label class="field-label">ADDRESS</label>
          <input class="field-input" type="text" id="editAddress" readonly>
        </div>
        <div class="field-group">
          <label class="field-label">DESCRIPTION (EDITABLE) *</label>
          <textarea class="field-input" name="description" id="editDesc" rows="3" required></textarea>
        </div>
        <div class="field-group">
          <label class="field-label">MACHINE PHOTO</label>
          <div class="img-preview-box" id="editImgPreview"></div>
        </div>
      </div>
      <div class="modal-foot">
        <button class="btn-save" type="submit">Save changes</button>
        <button class="btn-cancel" type="button" onclick="closeModal('editModal')">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- FULL DETAILS MODAL (DOUBLE CLICK) -->
<div class="overlay" id="detailsModal">
  <div class="modal">
    <div class="modal-head">Machine Details</div>
    <div class="modal-body">
      <div class="img-preview-box" style="height:160px; cursor:pointer;" title="Click image to view full screen" onclick="viewFullScreenImage()">
         <div id="detailsImgPreview" style="width:100%; height:100%;"></div>
      </div>
      <div class="field-row">
        <div class="field-group">
          <label class="field-label">MACHINE NAME</label>
          <input class="field-input" type="text" id="detName" readonly>
        </div>
        <div class="field-group">
          <label class="field-label">STATUS</label>
          <input class="field-input" type="text" id="detStatus" readonly>
        </div>
      </div>
      <div class="field-row">
        <div class="field-group">
          <label class="field-label">RENT / HECTARE</label>
          <input class="field-input" type="text" id="detRate" readonly>
        </div>
        <div class="field-group">
          <label class="field-label">OPERATOR RATE</label>
          <input class="field-input" type="text" id="detOpRate" readonly>
        </div>
      </div>
      <div class="field-group">
        <label class="field-label">ASSOCIATION</label>
        <input class="field-input" type="text" id="detAssoc" readonly>
      </div>
      <div class="field-group">
        <label class="field-label">ADDRESS</label>
        <input class="field-input" type="text" id="detAddress" readonly>
      </div>
      <div class="field-group">
        <label class="field-label">DESCRIPTION</label>
        <div class="field-input" id="detDesc" style="height:auto; min-height:60px; white-space:pre-wrap; background:#f9f9f9;"></div>
      </div>
    </div>
    <div class="modal-foot">
      <button class="btn-cancel" type="button" onclick="closeModal('detailsModal')">Close</button>
    </div>
  </div>
</div>

<!-- FULLSCREEN LIGHTBOX -->
<div class="lightbox-modal" id="lightboxModal" onclick="this.classList.remove('open')">
   <img id="lightboxImg" src="" alt="Full Screen Preview">
</div>

<!-- RESPONSE POP-UP MODAL (ABOUT US STYLE) -->
<div id="responseModal" class="response-modal">
    <div class="response-modal-content">
        <p id="responseMsgText"></p>
        <button type="button" onclick="document.getElementById('responseModal').style.display='none'">OK</button>
    </div>
</div>

<script>
const dbMachines = <?= json_encode($db_machines, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

let currentService = null;
let currentAssoc = null;
let selectedMachineId = null;
let currentActiveImgPath = '';

/* ── DROPDOWNS ── */
function toggleGallery() {
  document.getElementById('galleryBtn').classList.toggle('open');
  document.getElementById('serviceDropdown').classList.toggle('open');
}

function selectService(el, label, serviceType) {
  document.querySelectorAll('.service-option').forEach(o => o.classList.remove('selected'));
  el.classList.add('selected');
  currentService = serviceType;
  
  document.getElementById('galleryBtn').textContent = label;
  document.getElementById('galleryBtn').classList.remove('open');
  document.getElementById('serviceDropdown').classList.remove('open');
  
  currentAssoc = null;
  document.getElementById('assocBtn').textContent = 'Association';
  
  populateAssocDropdown(serviceType);
  
  document.getElementById('assocRow').classList.add('visible');
  document.getElementById('cardsSection').classList.remove('visible');
  deselectMachine();
}

function populateAssocDropdown(serviceType) {
  const assocDropdown = document.getElementById('assocDropdown');
  assocDropdown.innerHTML = '';

  const matchedAssocs = [...new Set(
    dbMachines
      .filter(m => m.type === serviceType && m.association_name)
      .map(m => m.association_name)
  )];

  if (matchedAssocs.length === 0) {
    assocDropdown.innerHTML = '<div class="assoc-option" style="color:#aaa; cursor:default;">No Associations Found</div>';
    return;
  }

  matchedAssocs.forEach(assocName => {
    const opt = document.createElement('div');
    opt.className = 'assoc-option';
    opt.textContent = assocName;
    opt.onclick = function() { selectAssoc(this, assocName); };
    assocDropdown.appendChild(opt);
  });
}

function toggleAssoc() {
  document.getElementById('assocBtn').classList.toggle('open');
  document.getElementById('assocDropdown').classList.toggle('open');
}

function selectAssoc(el, name) {
  document.querySelectorAll('.assoc-option').forEach(o => o.classList.remove('selected'));
  el.classList.add('selected');
  currentAssoc = name;
  document.getElementById('assocBtn').textContent = name;
  document.getElementById('assocBtn').classList.remove('open');
  document.getElementById('assocDropdown').classList.remove('open');
  deselectMachine();
  renderCards();
}

/* ── RENDER CARDS ── */
function renderCards() {
  const filtered = dbMachines.filter(m => m.type === currentService && m.association_name === currentAssoc && m.description && m.description.trim() !== '');
  const grid = document.getElementById('cardsGrid');
  document.getElementById('cardsHeading').innerHTML =
    `Showing <strong>${currentService}</strong> machines for <strong>${currentAssoc}</strong>`;

  if (!filtered.length) {
    grid.innerHTML = '<div class="empty">No added machines for this selection yet. Click "Add" to list one.</div>';
  } else {
    grid.innerHTML = filtered.map(cardHTML).join('');
  }
  document.getElementById('cardsSection').classList.add('visible');
}

function cardHTML(item) {
  const sc = { Active:'status-active', Inactive:'status-inactive', Maintenance:'status-maintenance', Damaged:'status-inactive' }[item.status] || 'status-active';
  const isSelected = selectedMachineId == item.id ? 'selected-card' : '';
  const imgTag = item.image_path ? `<img src="${item.image_path}" alt="${item.machine_name}">` : `<span style="color:#aaa; font-size:11px;">No Image</span>`;
  
  return `<div class="machine-card ${isSelected}" onclick="selectMachineCard(${item.id})" ondblclick="openDetailsModal(${item.id})">
    <div class="card-img-box">
      ${imgTag}
      <span class="card-status ${sc}">${item.status}</span>
    </div>
    <div class="card-body">
      <div class="card-name">${item.machine_name}</div>
      <div class="card-meta">
        <div><strong>Assoc:</strong> ${item.association_name || 'N/A'}</div>
      </div>
      <div class="card-desc">${item.description}</div>
      <div class="card-price"><strong>₱${parseFloat(item.price_per_hectare || 0).toFixed(2)}</strong>/ha</div>
    </div>
  </div>`;
}

function selectMachineCard(id) {
  if (selectedMachineId == id) {
    deselectMachine();
  } else {
    selectedMachineId = id;
    document.getElementById('btnEdit').disabled = false;
  }
  renderCards();
}

function deselectMachine() {
  selectedMachineId = null;
  document.getElementById('btnEdit').disabled = true;
}

/* ── ADD MODAL ── */
function openAddModal() {
  const selectDropdown = document.getElementById('addSelectMachine');
  selectDropdown.innerHTML = '<option value="" disabled selected hidden>Select Existing Machine</option>';

  const filteredDbMachines = dbMachines.filter(m => m.type === currentService && m.association_name === currentAssoc);

  if (filteredDbMachines.length === 0) {
    selectDropdown.innerHTML += '<option value="" disabled>No existing machines available</option>';
  } else {
    filteredDbMachines.forEach(m => {
      selectDropdown.innerHTML += `<option value="${m.id}">${m.machine_name}</option>`;
    });
  }

  document.getElementById('addMachineId').value = '';
  document.getElementById('addName').value = '';
  document.getElementById('addType').value = '';
  document.getElementById('addRate').value = '';
  document.getElementById('addOpRate').value = '';
  document.getElementById('addAssoc').value = '';
  document.getElementById('addStatus').value = '';
  document.getElementById('addAddress').value = '';
  document.getElementById('addDesc').value = '';
  document.getElementById('addImgPreview').innerHTML = '<span style="color:#aaa; font-size:12px;">No Machine Image Selected</span>';

  validateAddForm();
  openModal('addModal');
}

function handleSelectExistingMachine(machineId) {
  const m = dbMachines.find(x => x.id == machineId);
  if (!m) return;

  if (m.description && m.description.trim() !== '') {
    showPopUpMessage("❌ Machine already exists in System Settings!");
    document.getElementById('addSelectMachine').value = '';
    document.getElementById('addMachineId').value = '';
    clearAddFields();
    validateAddForm();
    return;
  }

  document.getElementById('addMachineId').value = m.id;
  document.getElementById('addName').value = m.machine_name;
  document.getElementById('addType').value = m.type;
  document.getElementById('addRate').value = '₱ ' + parseFloat(m.price_per_hectare || 0).toFixed(2);
  document.getElementById('addOpRate').value = '₱ ' + parseFloat(m.operator_rate_per_hectare || m.price_per_hectare || 0).toFixed(2);
  document.getElementById('addAssoc').value = m.association_name || 'N/A';
  document.getElementById('addStatus').value = m.status;
  document.getElementById('addAddress').value = m.address || 'N/A';

  const imgBox = document.getElementById('addImgPreview');
  if (m.image_path) {
    imgBox.innerHTML = `<img src="${m.image_path}" alt="Machine Image">`;
  } else {
    imgBox.innerHTML = '<span style="color:#aaa; font-size:12px;">No Image Available</span>';
  }

  validateAddForm();
}

function clearAddFields() {
  document.getElementById('addName').value = '';
  document.getElementById('addType').value = '';
  document.getElementById('addRate').value = '';
  document.getElementById('addOpRate').value = '';
  document.getElementById('addAssoc').value = '';
  document.getElementById('addStatus').value = '';
  document.getElementById('addAddress').value = '';
  document.getElementById('addImgPreview').innerHTML = '<span style="color:#aaa; font-size:12px;">No Machine Image Selected</span>';
}

function validateAddForm() {
  const selected = document.getElementById('addSelectMachine').value;
  const desc = document.getElementById('addDesc').value.trim();
  document.getElementById('btnAddSubmit').disabled = !(selected && desc.length > 0);
}

/* AJAX Form Submission for Add Machine */
function submitAddForm(e) {
  e.preventDefault();
  const form = document.getElementById('addMachineForm');
  const formData = new FormData(form);

  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    closeModal('addModal');
    if (data.status === 'success') {
      const machineId = document.getElementById('addMachineId').value;
      const desc = document.getElementById('addDesc').value.trim();
      const target = dbMachines.find(x => x.id == machineId);
      if (target) target.description = desc;
      renderCards();
      showPopUpMessage(data.message);
    } else {
      showPopUpMessage(data.message || 'Error adding machine.');
    }
  })
  .catch((err) => {
    closeModal('addModal');
    showPopUpMessage('❌ Server communication error.');
  });
}

/* ── EDIT MODAL ── */
function openEditModal() {
  if (!selectedMachineId) return;
  const item = dbMachines.find(x => x.id == selectedMachineId);
  if (!item) return;

  document.getElementById('editMachineId').value = item.id;
  document.getElementById('editName').value = item.machine_name;
  document.getElementById('editStatus').value = item.status;
  document.getElementById('editRate').value = '₱ ' + parseFloat(item.price_per_hectare || 0).toFixed(2);
  document.getElementById('editOpRate').value = '₱ ' + parseFloat(item.operator_rate_per_hectare || item.price_per_hectare || 0).toFixed(2);
  document.getElementById('editAddress').value = item.address || 'N/A';
  document.getElementById('editDesc').value = item.description || '';

  const editImgBox = document.getElementById('editImgPreview');
  if (item.image_path) {
    editImgBox.innerHTML = `<img src="${item.image_path}" alt="Machine Image">`;
  } else {
    editImgBox.innerHTML = '<span style="color:#aaa; font-size:12px;">No Image Available</span>';
  }

  openModal('editModal');
}

/* AJAX Form Submission for Edit Machine */
function submitEditForm(e) {
  e.preventDefault();
  const form = document.getElementById('editMachineForm');
  const formData = new FormData(form);

  fetch(window.location.href, {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    closeModal('editModal');
    if (data.status === 'success') {
      const machineId = document.getElementById('editMachineId').value;
      const desc = document.getElementById('editDesc').value.trim();
      const target = dbMachines.find(x => x.id == machineId);
      if (target) target.description = desc;
      renderCards();
      showPopUpMessage(data.message);
    } else {
      showPopUpMessage(data.message || 'Error updating machine.');
    }
  })
  .catch((err) => {
    closeModal('editModal');
    showPopUpMessage('❌ Server communication error.');
  });
}

/* ── FULL DETAILS MODAL & LIGHTBOX ── */
function openDetailsModal(id) {
  const m = dbMachines.find(x => x.id == id);
  if (!m) return;

  currentActiveImgPath = m.image_path || '';
  document.getElementById('detName').value = m.machine_name;
  document.getElementById('detStatus').value = m.status;
  document.getElementById('detRate').value = '₱ ' + parseFloat(m.price_per_hectare || 0).toFixed(2);
  document.getElementById('detOpRate').value = '₱ ' + parseFloat(m.operator_rate_per_hectare || m.price_per_hectare || 0).toFixed(2);
  document.getElementById('detAssoc').value = m.association_name || 'N/A';
  document.getElementById('detAddress').value = m.address || 'N/A';
  document.getElementById('detDesc').textContent = m.description || 'No description provided.';

  const previewBox = document.getElementById('detailsImgPreview');
  if (m.image_path) {
    previewBox.innerHTML = `<img src="${m.image_path}" style="width:100%; height:100%; object-fit:cover;" alt="Machine Image">`;
  } else {
    previewBox.innerHTML = '<span style="color:#aaa; font-size:12px; display:flex; height:100%; align-items:center; justify-content:center;">No Image Available</span>';
  }

  openModal('detailsModal');
}

function viewFullScreenImage() {
  if (!currentActiveImgPath) return;
  document.getElementById('lightboxImg').src = currentActiveImgPath;
  document.getElementById('lightboxModal').classList.add('open');
}

/* ── POPUP MESSAGE HELPER ── */
function showPopUpMessage(msg) {
  const modal = document.getElementById('responseModal');
  document.getElementById('responseMsgText').textContent = msg;
  modal.style.display = 'flex';
}

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

document.querySelectorAll('.overlay').forEach(o => {
  o.addEventListener('click', e => { if (e.target === o) closeModal(o.id); });
});

/* Close dropdowns when clicking outside */
document.addEventListener('click', e => {
  if (!e.target.closest('#galleryRow')) {
    document.getElementById('galleryBtn').classList.remove('open');
    document.getElementById('serviceDropdown').classList.remove('open');
  }
  if (!e.target.closest('.assoc-select-wrap')) {
    document.getElementById('assocBtn').classList.remove('open');
    document.getElementById('assocDropdown').classList.remove('open');
  }
});
</script>
</body>
</html>