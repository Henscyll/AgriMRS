<?php
include('dashboard_itadmin.php');
include('../includes/db_connection.php');

$message = "";
$messageType = ""; 

$result = $conn->query("SELECT * FROM about_us WHERE id = 1");
$about = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $content = trim($_POST["content"]);

    if (!empty($content)) {
        $stmt = $conn->prepare("UPDATE about_us SET content=? WHERE id=1");
        $stmt->bind_param("s", $content);

        if ($stmt->execute()) {
            $message = "About Us updated successfully!";
            $messageType = "success";
            $about['content'] = $content;
        } else {
            $message = "❌ Failed to update About Us section.";
            $messageType = "error";
        }
        $stmt->close();
    } else {
        $message = "❌ Content cannot be empty.";
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>About Us | AMRMS</title>
<style>
/* ── Main Layout & Header ── */
.main-content { height: auto !important; padding: 50px 20px; }

h2 { 
    font-size: 28px; 
    color: #2d7a2d; 
    font-weight: bold; 
    text-align: center; 
    text-shadow: 1px 1px 3px rgba(14,4,4,0.7); 
    margin: 10px 0 20px 0; 
    padding-bottom: 1px; 
}

/* ── Container Alignment ── */
.about-wrapper {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 100%;
}

.about-container {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border: 1px solid #ddd;
    padding: 30px;
    width: 100%;
    max-width: 1200px;
    position: relative;
}

/* Fixed spacing display with inner vertical scrollbar */
.about-display-text {
    font-size: 14px;
    font-family: inherit;
    line-height: 1.6;
    color: #333;
    white-space: pre-wrap; /* Preserves exact spaces, tabs, and newlines */
    word-wrap: break-word;
    height: 290px;
    max-height: 290px;
    overflow-y: auto; /* Internal scrollbar */
    background-color: #f9f9f9;
    padding: 12px;
    border-radius: 6px;
    border: 1px solid #ccc;
    box-sizing: border-box;
}

textarea {
    width: 100%;
    height: 290px;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    background-color: white;
    color: black;
    resize: vertical;
    font-family: inherit;
    font-size: 14px;
    box-sizing: border-box;
}

textarea:focus {
    outline: none;
    border-color: #2d7a2d;
}

.btn-group {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 20px;
}

button {
    background-color: #2d7a2d;
    color: white;
    border: none;
    padding: 10px 25px;
    cursor: pointer;
    border-radius: 6px;
    font-size: 16px;
    font-weight: 600;
    transition: background-color 0.3s;
}

button:hover {
    background-color: #256725;
}

button.cancel-btn {
    background-color: #6c757d;
}

button.cancel-btn:hover {
    background-color: #5a6268;
}

/* ── Response Modal Styles ── */
.modal {
    display: <?= !empty($message) ? 'flex' : 'none'; ?>;
    position: fixed;
    z-index: 1000;
    inset: 0;
    backdrop-filter: blur(4px);
    background-color: rgba(0,0,0,0.4);
    justify-content: center;
    align-items: center;
}

.modal-content {
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

.modal-content p {
    font-size: 16px;
    font-weight: 600;
    margin-bottom: 20px;
    color: #333;
}

.modal-content button {
    padding: 8px 30px;
}

@keyframes fadeIn {
    from {opacity: 0; transform: translateY(-15px);}
    to {opacity: 1; transform: translateY(0);}
}

@media(max-width:640px) {
    .main-content { padding: 10px; }
}
</style>
</head>
<body>

<div class="main-content">

    <h2>About Us</h2>

    <div class="about-wrapper">
        <div class="about-container">
            
            <!-- READ-ONLY VIEW -->
            <div id="displaySection">
                <div class="about-display-text"><?= htmlspecialchars($about['content'] ?? 'No content available.'); ?></div>
                <div class="btn-group">
                    <button type="button" id="editBtn">Edit</button>
                </div>
            </div>

            <!-- EDIT FORM VIEW -->
            <form id="aboutForm" method="post" style="display: none;">
                <textarea name="content" required><?= htmlspecialchars($about['content'] ?? ''); ?></textarea>
                <div class="btn-group">
                    <button type="submit" id="saveBtn">Save</button>
                </div>
            </form>

        </div>
    </div>
</div>

<!-- Response Pop-up Modal -->
<?php if (!empty($message)): ?>
<div id="responseModal" class="modal">
    <div class="modal-content">
        <p style="font-weight:bold;"><?= $message; ?></p>
        <button type="button" id="okModalBtn">OK</button>
    </div>
</div>
<?php endif; ?>

<script>
const displaySection = document.getElementById("displaySection");
const aboutForm = document.getElementById("aboutForm");
const editBtn = document.getElementById("editBtn");
const responseModal = document.getElementById("responseModal");
const okModalBtn = document.getElementById("okModalBtn");

editBtn.addEventListener("click", () => {
    displaySection.style.display = "none";
    aboutForm.style.display = "block";
});

if (okModalBtn) {
    okModalBtn.addEventListener("click", () => {
        responseModal.style.display = "none";
    });
}
</script>

</body>
</html>