<?php
include('operator_dashboard.php');
include('../includes/db_connection.php');

$result = $conn->query("SELECT content FROM about_us ORDER BY updated_at DESC LIMIT 1");
$about_content = $result->fetch_assoc()['content'] ?? 'No About Us content found.';
?>

<div class="about-wrapper">
    <div class="about-container">
        <h1>About Us</h1>
        <div class="about-content">
            <?= nl2br(htmlspecialchars($about_content)) ?>
        </div>
    </div>
</div>

<style>
    .about-wrapper {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        min-height: calc(100vh - 150px);
        padding: 150px 20px 40px;
    }

    .about-container {
        background: #fff;
        border-radius: 8px;
        box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        padding: 28px;
        width: 100%;
        max-width: 1000px;
    }

    .about-container h1 {
        text-align: center;
        color: #333;
        margin-bottom: 16px;
        font-size: 24px;
    }

    .about-content {
        width: 100%;
        padding: 12px;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        background-color: #fafafa;
        color: #333;
        font-size: 15px;
        line-height: 1.8;
        text-align: justify;
        word-break: break-word;
    }

    .about-content p {
        margin-bottom: 12px;
    }

    @media (max-width: 768px) {
        .about-wrapper {
            padding: 190px 12px 30px; /* enough top clearance for fixed header */
            align-items: flex-start;
        }

        .about-container {
            padding: 16px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .about-container h1 {
            font-size: 20px;
            margin-bottom: 12px;
        }

        .about-content {
            font-size: 14px;
            line-height: 1.75;
            padding: 10px;
            text-align: left; /* justify looks bad on narrow screens */
            border: none;     /* cleaner look on mobile — card already has a border */
            background: transparent;
        }
    }
</style>