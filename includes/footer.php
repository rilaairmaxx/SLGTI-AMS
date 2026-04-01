</div><!-- End content-wrapper -->
</div><!-- End main-content -->

<?php include __DIR__ . "/../ai_chatbox.php"; ?>

<!-- ══ Session Timeout Warning Modal ══ -->
<div class="modal fade" id="sessionWarnModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-body text-center p-4">
                <div style="font-size:2.5rem;color:#f59e0b;margin-bottom:12px;">
                    <i class="fas fa-clock"></i>
                </div>
                <h5 class="fw-bold mb-1">Session Expiring Soon</h5>
                <p class="text-muted small mb-3">
                    Your session will expire in <strong id="warnCountdown">2:00</strong>.<br>
                    Stay on the page or click below to continue.
                </p>
                <button class="btn btn-primary btn-sm w-100" onclick="extendSession()">
                    <i class="fas fa-refresh me-2"></i>Keep Me Logged In
                </button>
                <a href="logout.php" class="btn btn-outline-secondary btn-sm w-100 mt-2">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout Now
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Mobile Menu Toggle — original button, redesigned style in header.php -->
<button class="mobile-menu-toggle btn d-md-none" type="button" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Mobile sidebar toggle — UNCHANGED
    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        sidebar.classList.toggle('show');
    }

    // Auto-hide alerts after 5 seconds — UNCHANGED
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            }, 5000);
        });

        // Add loading states to buttons — UNCHANGED
        const forms = document.querySelectorAll('form');
        forms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
                    submitBtn.disabled = true;

                    // Re-enable after 3 seconds as fallback — UNCHANGED
                    setTimeout(function() {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 3000);
                }
            });
        });

        // Add smooth scrolling — UNCHANGED
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Add fade-in animation to cards — UNCHANGED
        const cards = document.querySelectorAll('.card');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.classList.add('fade-in');
        });
    });

    // Confirm delete actions — UNCHANGED
    function confirmDelete(message = 'Are you sure you want to delete this item?') {
        return confirm(message);
    }

    // ── Session timeout warning (fires 2 min before expiry) ──
    (function() {
        const WARN_AT = 120; // seconds before expiry to show modal
        let warnShown = false;
        let warnInterval = null;
        let warnRemaining = WARN_AT;
        const modal = new bootstrap.Modal(document.getElementById('sessionWarnModal'), {});

        // Piggyback on the existing sessionTimeout variable from header.php
        function checkWarn() {
            if (typeof sessionTimeout === 'undefined') return;
            if (!warnShown && sessionTimeout <= WARN_AT && sessionTimeout > 0) {
                warnShown = true;
                warnRemaining = sessionTimeout;
                modal.show();
                warnInterval = setInterval(function() {
                    warnRemaining--;
                    const m = Math.floor(warnRemaining / 60);
                    const s = warnRemaining % 60;
                    const el = document.getElementById('warnCountdown');
                    if (el) el.textContent = m + ':' + s.toString().padStart(2, '0');
                    if (warnRemaining <= 0) {
                        clearInterval(warnInterval);
                        modal.hide();
                        window.location.href = 'logout.php';
                    }
                }, 1000);
            }
            // Reset if user was active (sessionTimeout reset to 1800)
            if (warnShown && sessionTimeout > WARN_AT) {
                warnShown = false;
                clearInterval(warnInterval);
                modal.hide();
            }
        }
        setInterval(checkWarn, 1000);
    })();

    function extendSession() {
        // Ping the server to reset session activity
        fetch(window.location.href, {
                method: 'HEAD',
                credentials: 'same-origin'
            })
            .catch(() => {});
        // Reset client-side timer
        if (typeof sessionTimeout !== 'undefined') sessionTimeout = 1800;
        bootstrap.Modal.getInstance(document.getElementById('sessionWarnModal'))?.hide();
    }
    // Show success message — UNCHANGED
    function showSuccess(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-success alert-dismissible fade show';
        alertDiv.innerHTML = `
        <i class="fas fa-check-circle me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

        const container = document.querySelector('.content-wrapper');
        container.insertBefore(alertDiv, container.firstChild);

        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }

    // Show error message — UNCHANGED
    function showError(message) {
        const alertDiv = document.createElement('div');
        alertDiv.className = 'alert alert-danger alert-dismissible fade show';
        alertDiv.innerHTML = `
        <i class="fas fa-exclamation-triangle me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

        const container = document.querySelector('.content-wrapper');
        container.insertBefore(alertDiv, container.firstChild);

        setTimeout(() => {
            alertDiv.remove();
        }, 5000);
    }
</script>

<!-- ══════════════════════════════════════
     FOOTER BAR
══════════════════════════════════════ -->
<footer style="
    margin-left: 260px;
    background: #fff;
    border-top: 1px solid #e4eaf3;
    padding: 12px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px;
    font-size: .73rem;
    color: #94a3b8;
">
    <span>
        &copy; <?php echo date('Y'); ?>
        <strong style="color:#0a2d6e;">SLGTI</strong>
        &mdash; Sri Lanka German Technical Institute, Kilinochchi
    </span>
    <span style="display:flex; align-items:center; gap:16px;">
        <a href="tel:0214927799" style="color:#94a3b8; text-decoration:none;">
            <i class="fas fa-phone" style="margin-right:4px;"></i>0214 927 799
        </a>
        <a href="mailto:sao@slgti.ac.lk" style="color:#94a3b8; text-decoration:none;">
            <i class="fas fa-envelope" style="margin-right:4px;"></i>sao@slgti.ac.lk
        </a>
        <a href="https://www.slgti.ac.lk" target="_blank" rel="noopener" style="color:#94a3b8; text-decoration:none;">
            <i class="fas fa-globe" style="margin-right:4px;"></i>slgti.ac.lk
        </a>
    </span>
</footer>

<style>
    /* Mobile footer + toggle fix */
    @media (max-width: 992px) {
        footer {
            margin-left: 0 !important;
            padding: 12px 16px;
        }
    }
</style>

</body>

</html>