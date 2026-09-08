<?php
include('dashboard_itadmin.php'); 
include('../includes/db_connection.php'); 

$message = "";

$result = $conn->query("SELECT * FROM contact_info WHERE id = 1");
$contact = $result->fetch_assoc();

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"]);
    $phone = trim($_POST["phone"]);
    $facebook = trim($_POST["facebook_link"]);
    $address = trim($_POST["address"]);

    if ($email && $phone && $facebook && $address) {
        $stmt = $conn->prepare("UPDATE contact_info SET email=?, phone=?, facebook_link=?, address=? WHERE id=1");
        $stmt->bind_param("ssss", $email, $phone, $facebook, $address);

        if ($stmt->execute()) {
            $message = "Contact Us updated successfully!";
            $contact = ['email' => $email, 'phone' => $phone, 'facebook_link' => $facebook, 'address' => $address];
        } else {
            $message = "❌ Failed to update contact information.";
        }
        $stmt->close();
    } else {
        $message = "❌ All fields are required.";
    }
}
?>
<!DOCTYPE html>
<html lang="en-US">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | AMRMS</title>
    <style>
        /* ── Standard Admin Layout ── */
        .main-content { height: auto !important; padding: 50px 20px; }
        h2 { font-size: 28px; color: #2d7a2d; font-weight: bold; text-align: center; text-shadow: 1px 1px 3px rgba(14,4,4,0.7); margin: 10px 0 20px 0; padding-bottom: 1px; }

        /* ── Contact Container Alignment ── */
        .contact-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            margin-top: 10px;
        }

        .contact-container {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            border: 1px solid #ddd;
            padding: 30px;
            width: 100%;
            max-width: 700px;
            position: relative;
        }

        /* ── Display Mode Styles ── */
        .display-group {
            margin-bottom: 15px;
        }

        .display-label {
            font-weight: bold;
            color: #000;
            font-size: 14px;
            margin-bottom: 5px;
            display: block;
        }

        .display-value {
            font-size: 14px;
            color: #333;
            background-color: #f9f9f9;
            padding: 10px 12px;
            border-radius: 6px;
            border: 1px solid #e0e0e0;
            word-wrap: break-word;
        }

        .display-value.address-box {
            white-space: pre-wrap;
            min-height: 80px;
            max-height: 200px;
            overflow-y: auto;
        }

        /* ── Input Field Styles ── */
        label {
            font-weight: bold;
            color: #000000;
            font-size: 14px;
            display: block;
            margin-bottom: 5px;
        }

        input, textarea {
            margin-bottom: 15px;
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background-color: white; 
            color: black;
            font-size: 14px;
            box-sizing: border-box;
            font-family: inherit;
        }

        textarea {
            resize: vertical;
            min-height: 90px;
        }

        /* ── Action Buttons ── */
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

        /* ── Modal Styling ── */
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

    <h2>Contact Us</h2>

    <div class="contact-wrapper">
        <div class="contact-container">

            <!-- READ-ONLY VIEW -->
            <div id="displaySection">
                <div class="display-group">
                    <span class="display-label">Email:</span>
                    <div class="display-value"><?= htmlspecialchars($contact['email'] ?? 'N/A'); ?></div>
                </div>

                <div class="display-group">
                    <span class="display-label">Phone:</span>
                    <div class="display-value"><?= htmlspecialchars($contact['phone'] ?? 'N/A'); ?></div>
                </div>

                <div class="display-group">
                    <span class="display-label">Facebook Link:</span>
                    <div class="display-value">
                        <?php if (!empty($contact['facebook_link'])): ?>
                            <a href="<?= htmlspecialchars($contact['facebook_link']); ?>" target="_blank" style="color: #2d7a2d; text-decoration: none;"><?= htmlspecialchars($contact['facebook_link']); ?></a>
                        <?php else: ?>
                            N/A
                        <?php endif; ?>
                    </div>
                </div>

                <div class="display-group">
                    <span class="display-label">Address:</span>
                    <div class="display-value address-box"><?= htmlspecialchars($contact['address'] ?? 'N/A'); ?></div>
                </div>

                <div class="btn-group">
                    <button type="button" id="editBtn">Edit</button>
                </div>
            </div>

            <!-- EDIT FORM VIEW -->
            <form id="contactForm" method="post" style="display: none;">
                <label>Email:</label>
                <input type="email" name="email" value="<?= htmlspecialchars($contact['email'] ?? ''); ?>" required>

                <label>Phone:</label>
                <input type="text" name="phone" value="<?= htmlspecialchars($contact['phone'] ?? ''); ?>" required>

                <label>Facebook Link:</label>
                <input type="url" name="facebook_link" value="<?= htmlspecialchars($contact['facebook_link'] ?? ''); ?>" required>

                <label>Address:</label>
                <textarea name="address" required><?= htmlspecialchars($contact['address'] ?? ''); ?></textarea>

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
        <p style="font-weight: bold;"><?= $message; ?></p>
        <button type="button" id="okModalBtn">OK</button>
    </div>
</div>
<?php endif; ?>

<script>
const displaySection = document.getElementById("displaySection");
const contactForm = document.getElementById("contactForm");
const editBtn = document.getElementById("editBtn");
const responseModal = document.getElementById("responseModal");
const okModalBtn = document.getElementById("okModalBtn");

// Toggle edit view
editBtn.addEventListener("click", () => {
    displaySection.style.display = "none";
    contactForm.style.display = "block";
});

// Close notification modal
if (okModalBtn) {
    okModalBtn.addEventListener("click", () => {
        responseModal.style.display = "none";
    });
}
</script>

</body>
</html>