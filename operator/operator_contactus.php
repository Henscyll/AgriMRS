<?php 
include('operator_dashboard.php');

?>

<!-- Scrollable content area pinned RIGHT below the navbar -->
<div class="page-scroll-wrapper">
    <main class="contact-container">
        <h1>Contact Us</h1>
        <p class="intro-text">
            If you have any questions or inquiries, feel free to reach out to us using the contact information below 
            or by sending a message through the form.
        </p>

        <div class="contact-grid">
            <!-- Left: Contact Info + Map -->
            <div class="left-section">
                <div class="contact-info-box">
                    <h2>Get In Touch</h2>
                    <div class="info-item">
                        <span class="icon">📧</span>
                        <div>
                            <strong>Email</strong>
                            <p>agrimach@gmail.com</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <span class="icon">📞</span>
                        <div>
                            <strong>Phone</strong>
                            <p>0928-220-6170</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <span class="icon">👥</span>
                        <div>
                            <strong>Facebook</strong>
                            <p><a href="#">https://www.facebook.com/DAZamPen</a></p>
                        </div>
                    </div>
                    <div class="info-item">
                        <span class="icon">📍</span>
                        <div>
                            <strong>Office</strong>
                            <p>Brgy. Linienza, Pagadian City, Philippines</p>
                        </div>
                    </div>
                </div>

                <div class="map-box">
                    <h2>Our Location</h2>
                    <iframe 
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2350.1322300593356!2d123.46085544349285!3d7.84940208388429!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x32543d38aa6af2fd%3A0x6e5fc755799262c3!2sDepartment%20of%20Agriculture%20(DA)%20IX!5e0!3m2!1sen!2sph!4v1752629891094!5m2!1sen!2sph" 
                        width="100%" height="220" 
                        style="border:0; border-radius:8px;" 
                        allowfullscreen="" loading="lazy" 
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            </div>

            <!-- Right: Contact Form -->
            <div class="right-section">
                <div class="form-box">
                    <h2>Send Us a Message</h2>
                    <form class="contact-form" method="post" action="#">
                        <label for="name">Your Name</label>
                        <input type="text" name="name" id="name" placeholder="Enter your name" required>

                        <label for="email">Your Email</label>
                        <input type="email" name="email" id="email" placeholder="Enter your email" required>

                        <label for="message">Your Message</label>
                        <textarea name="message" id="message" rows="5" placeholder="Type your message here..." required></textarea>

                        <button type="submit">
                            <span>Send Message</span>
                            <span class="arrow">→</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
</div>

<style>
    /* ── Lock body/html — NO browser scrollbar ── */
    html, body {
        height: 100%;
        margin: 0;
        padding: 0;
        overflow: hidden;
        font-family: "Segoe UI", Arial, sans-serif;
    }

    .page-scroll-wrapper {
        position: fixed;
        top: 210px;          /* ← adjust to your exact header height */
        left: 0;
        right: 0;
        bottom: 0;
        overflow-y: auto;
        overflow-x: hidden;
        background: transparent;
    }




    main.contact-container {
        padding: 25px 20px;
        max-width: 1000px;
        margin: 20px auto 40px;
        background: #ffffff;
        border-radius: 12px;
        box-shadow: 0 2px 20px rgba(0,0,0,0.08);
    }

    .contact-container h1 {
        color: #2E7D32;
        font-size: 1.8rem;
        margin-bottom: 10px;
        text-align: center;
        font-weight: 600;
    }

    .intro-text {
        font-size: 0.9rem;
        color: #666;
        line-height: 1.5;
        text-align: center;
        margin-bottom: 25px;
        max-width: 700px;
        margin-left: auto;
        margin-right: auto;
    }

    .contact-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-top: 20px;
    }

    .left-section { display: flex; flex-direction: column; gap: 15px; }

    .contact-info-box {
        background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
        padding: 20px;
        border-radius: 10px;
        color: white;
    }

    .contact-info-box h2 { color: white; font-size: 1.3rem; margin-top: 0; margin-bottom: 15px; font-weight: 600; }

    .info-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid rgba(255,255,255,0.2);
    }
    .info-item:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
    .info-item .icon   { font-size: 1.3rem; flex-shrink: 0; }
    .info-item strong  { display: block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 3px; opacity: 0.9; }
    .info-item p       { margin: 0; font-size: 0.9rem; }
    .info-item a       { color: white; text-decoration: underline; }
    .info-item a:hover { opacity: 0.8; }

    .map-box { background: #f9f9f9; padding: 18px; border-radius: 10px; border: 1px solid #e0e0e0; }
    .map-box h2 { color: #2E7D32; font-size: 1.2rem; margin-top: 0; margin-bottom: 12px; font-weight: 600; }

    .form-box { background: #f9f9f9; padding: 20px; border-radius: 10px; border: 1px solid #e0e0e0; }
    .form-box h2 { color: #2E7D32; font-size: 1.3rem; margin-top: 0; margin-bottom: 15px; font-weight: 600; }

    .contact-form label { display: block; margin-bottom: 6px; font-weight: 600; color: #333; font-size: 0.9rem; }

    .contact-form input,
    .contact-form textarea {
        width: 100%;
        padding: 10px 12px;
        margin-bottom: 15px;
        border-radius: 8px;
        border: 1px solid #ddd;
        box-sizing: border-box;
        font-size: 0.9rem;
        font-family: inherit;
        transition: border-color 0.3s, box-shadow 0.3s;
    }

    .contact-form input:focus,
    .contact-form textarea:focus {
        outline: none;
        border-color: #4CAF50;
        box-shadow: 0 0 0 3px rgba(76,175,80,0.1);
    }

    .contact-form textarea { resize: vertical; min-height: 80px; }

    .contact-form button {
        background: linear-gradient(135deg, #4CAF50 0%, #2E7D32 100%);
        color: white;
        padding: 12px 25px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 0.95rem;
        font-weight: 600;
        transition: transform 0.2s, box-shadow 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
    }

    .contact-form button:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(46,125,50,0.3); }
    .contact-form button .arrow { font-size: 1.2rem; transition: transform 0.2s; }
    .contact-form button:hover .arrow { transform: translateX(5px); }

    /* Responsive */
    @media (max-width: 968px) {
        .page-scroll-wrapper { top: 180px; }
        .contact-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 600px) {
        .page-scroll-wrapper { top: 160px; }
        main.contact-container { padding: 20px 15px; margin: 10px 10px 20px; }
        .contact-container h1 { font-size: 1.5rem; }
    }
</style>

<script>
    // Auto-detect the exact header height so the wrapper starts right below it
    window.addEventListener('DOMContentLoaded', function () {
        var header = document.querySelector('header') 
                  || document.querySelector('nav') 
                  || document.querySelector('.header')
                  || document.querySelector('#header');
        if (header) {
            var h = header.getBoundingClientRect().bottom;
            document.querySelector('.page-scroll-wrapper').style.top = h + 'px';
        }
    });
</script>