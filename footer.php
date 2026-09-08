<?php
// footer.php
?>

<style>
/* ══════════════════════════════════════
   FOOTER STYLES — Desktop + Mobile
══════════════════════════════════════ */
.footer {
  background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
  padding: 24px 0 0;
  color: #fff;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  position: relative;
  z-index: 900;
  border-top: 3px solid #16a34a;
}

.footer-container {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  flex-wrap: wrap;
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 24px 20px;
  gap: 20px;
}

.footer-left {
  flex: 1 1 300px;
  max-width: 600px;
}

.footer-right {
  flex: 0 1 auto;
  text-align: right;
}

.footer-title {
  font-size: 16px;
  color: #16a34a;
  margin-bottom: 8px;
  font-weight: 700;
  letter-spacing: 0.3px;
  line-height: 1.4;
}

.footer-text {
  font-size: 13px;
  line-height: 1.6;
  color: #b0b0b0;
  margin-bottom: 8px;
}

.footer-copy {
  margin: 0;
  font-size: 12px;
  color: #888;
}

.footer-right h6 {
  font-size: 13px;
  margin-bottom: 10px;
  color: #d0d0d0;
  font-weight: 600;
}

.social-icons {
  display: flex;
  gap: 10px;
  margin-top: 8px;
  justify-content: flex-end;
}

.social-icons .icon {
  width: 38px;
  height: 38px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 18px;
  color: #ffffff;
  background: rgba(255, 255, 255, 0.1);
  border-radius: 10px;
  transition: all 0.3s ease;
  text-decoration: none;
  border: 1px solid rgba(255, 255, 255, 0.15);
}

.social-icons .icon:hover {
  transform: translateY(-3px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.social-icons .facebook:hover  { background: #3b5998; border-color: #3b5998; }
.social-icons .twitter:hover   { background: #1da1f2; border-color: #1da1f2; }
.social-icons .instagram:hover {
  background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
  border-color: #e4405f;
}
.social-icons .email:hover { background: #16a34a; border-color: #16a34a; }

.footer-bottom {
  background: rgba(0, 0, 0, 0.35);
  padding: 10px 16px;
  text-align: center;
  font-size: 12px;
  color: #888;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
}

/* ══════════════════════════════════════
   TABLET  (≤768px)
══════════════════════════════════════ */
@media (max-width: 768px) {
  .footer {
    padding: 20px 0 0;
  }

  .footer-container {
    flex-direction: column;
    align-items: center;
    padding: 0 20px 16px;
    gap: 18px;
    text-align: center;
  }

  .footer-left {
    max-width: 100%;
    width: 100%;
  }

  .footer-right {
    text-align: center;
    width: 100%;
  }

  .footer-title {
    font-size: 15px;
  }

  .footer-text {
    font-size: 13px;
  }

  .social-icons {
    justify-content: center;
    gap: 12px;
  }
}

/* ══════════════════════════════════════
   MOBILE  (≤480px)
══════════════════════════════════════ */
@media (max-width: 480px) {
  .footer {
    padding: 18px 0 0;
  }

  .footer-container {
    padding: 0 14px 14px;
    gap: 16px;
  }

  /* Brand name wraps neatly */
  .footer-title {
    font-size: 14px;
    line-height: 1.5;
  }

  .footer-text {
    font-size: 12px;
    line-height: 1.55;
    color: #a0a0a0;
  }

  .footer-copy {
    font-size: 11px;
  }

  .footer-right h6 {
    font-size: 12px;
    margin-bottom: 8px;
  }

  /* Bigger tap targets on mobile */
  .social-icons {
    gap: 10px;
    justify-content: center;
  }

  .social-icons .icon {
    width: 42px;
    height: 42px;
    font-size: 19px;
    border-radius: 12px;
  }

  .footer-bottom {
    font-size: 11px;
    padding: 9px 14px;
  }
}

/* ══════════════════════════════════════
   VERY SMALL  (≤360px)
══════════════════════════════════════ */
@media (max-width: 360px) {
  .footer-container {
    padding: 0 12px 12px;
  }

  .social-icons .icon {
    width: 38px;
    height: 38px;
    font-size: 17px;
  }

  .footer-title {
    font-size: 13px;
  }
}
</style>

<!-- Bootstrap Icons CDN -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<!-- ══════════════════════════════════════
     FOOTER HTML
══════════════════════════════════════ -->
<footer class="footer">
  <div class="footer-container">

    <!-- Left: branding + description -->
    <div class="footer-left">
      <h5 class="footer-title">
        Agricultural Machineries Reservation and Monitoring System
      </h5>
      <p class="footer-text">
        Developed to serve Filipino farmers, cooperatives, and local agricultural
        units by making equipment access easier and faster.
      </p>
      <p class="footer-copy">&copy; <?= date('Y') ?> All rights reserved.</p>
    </div>

    <!-- Right: social icons -->
    <div class="footer-right">
      <h6>Follow us:</h6>
      <div class="social-icons">
        <a href="https://facebook.com"  target="_blank" class="icon facebook"
           title="Facebook">
          <i class="bi bi-facebook"></i>
        </a>
        <a href="https://twitter.com"   target="_blank" class="icon twitter"
           title="Twitter">
          <i class="bi bi-twitter"></i>
        </a>
        <a href="https://instagram.com" target="_blank" class="icon instagram"
           title="Instagram">
          <i class="bi bi-instagram"></i>
        </a>
        <a href="mailto:support@agrimachinery.com" class="icon email"
           title="Email us">
          <i class="bi bi-envelope-fill"></i>
        </a>
      </div>
    </div>

  </div>

  <!-- Bottom bar -->
  <div class="footer-bottom">
    <small>&copy; <?= date('Y') ?> Agricultural Machineries Reservation and Monitoring System. All rights reserved.</small>
  </div>
</footer>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>