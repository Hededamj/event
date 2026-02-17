/**
 * Subcontractor Module - Client-Side JavaScript
 * PartyParart Leverandorportal
 *
 * Vanilla JS — no library dependencies.
 */

document.addEventListener('DOMContentLoaded', function () {
    initSidebarToggle();
    initModals();
    initStarRating();
    initMessageAutoScroll();
    initBookingStatusTabs();
    initCalendarNavigation();
    initFlashAutoDismiss();
    initImagePreview();
    initConfirmDialogs();
});

// ---------------------------------------------------------------------------
// 1. Sidebar toggle (mobile hamburger menu)
// ---------------------------------------------------------------------------
function initSidebarToggle() {
    var menuToggle = document.getElementById('menuToggle');
    var sidebar = document.getElementById('vendorSidebar');
    var overlay = document.getElementById('sidebarOverlay');

    if (!menuToggle || !sidebar || !overlay) return;

    function openSidebar() {
        sidebar.classList.add('open');
        overlay.classList.add('open');
        document.body.classList.add('sidebar-open');
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        overlay.classList.remove('open');
        document.body.classList.remove('sidebar-open');
    }

    menuToggle.addEventListener('click', function () {
        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    overlay.addEventListener('click', closeSidebar);

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            closeSidebar();
        }
    });
}

// ---------------------------------------------------------------------------
// 2. Modal open / close
// ---------------------------------------------------------------------------
function initModals() {
    // Close on overlay (background) click
    document.querySelectorAll('.modal').forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });

    // Close on ESC key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            var active = document.querySelector('.modal.modal-open');
            if (active) {
                closeModal(active.id);
            }
        }
    });

    // Delegate click for [data-modal-open] and [data-modal-close] attributes
    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-modal-open]');
        if (opener) {
            e.preventDefault();
            openModal(opener.getAttribute('data-modal-open'));
            return;
        }

        var closer = e.target.closest('[data-modal-close]');
        if (closer) {
            e.preventDefault();
            var modalId = closer.getAttribute('data-modal-close');
            if (modalId) {
                closeModal(modalId);
            } else {
                // Close the nearest parent modal
                var parentModal = closer.closest('.modal');
                if (parentModal) {
                    closeModal(parentModal.id);
                }
            }
        }
    });
}

function openModal(id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('modal-open');
    document.body.style.overflow = 'hidden';

    // Focus first input inside the modal
    var firstInput = modal.querySelector('input, textarea, select');
    if (firstInput) {
        setTimeout(function () { firstInput.focus(); }, 100);
    }
}

function closeModal(id) {
    var modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.remove('modal-open');
    document.body.style.overflow = '';
}

// ---------------------------------------------------------------------------
// 3. Star rating input (review forms)
// ---------------------------------------------------------------------------
function initStarRating() {
    document.querySelectorAll('.star-rating').forEach(function (container) {
        var stars = container.querySelectorAll('.star-rating-star');
        var hiddenInput = container.querySelector('input[type="hidden"]');
        if (!stars.length || !hiddenInput) return;

        stars.forEach(function (star, index) {
            var value = index + 1;

            star.addEventListener('click', function () {
                hiddenInput.value = value;
                updateStarDisplay(stars, value);
            });

            star.addEventListener('mouseenter', function () {
                updateStarDisplay(stars, value);
            });

            star.addEventListener('mouseleave', function () {
                updateStarDisplay(stars, parseInt(hiddenInput.value, 10) || 0);
            });
        });

        // Set initial display from hidden input value
        var initial = parseInt(hiddenInput.value, 10) || 0;
        if (initial) {
            updateStarDisplay(stars, initial);
        }
    });
}

function updateStarDisplay(stars, activeCount) {
    stars.forEach(function (star, i) {
        if (i < activeCount) {
            star.classList.add('star-filled');
        } else {
            star.classList.remove('star-filled');
        }
    });
}

// ---------------------------------------------------------------------------
// 4. Message auto-scroll
// ---------------------------------------------------------------------------
function initMessageAutoScroll() {
    var thread = document.querySelector('.message-thread');
    if (thread) {
        thread.scrollTop = thread.scrollHeight;
    }
}

// ---------------------------------------------------------------------------
// 5. Booking status filter tabs
// ---------------------------------------------------------------------------
function initBookingStatusTabs() {
    var tabContainer = document.querySelector('.status-tabs');
    if (!tabContainer) return;

    var tabs = tabContainer.querySelectorAll('.status-tab');
    if (!tabs.length) return;

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            var status = this.getAttribute('data-status');

            // Update active tab
            tabs.forEach(function (t) { t.classList.remove('active'); });
            this.classList.add('active');

            // Show / hide table rows
            var rows = document.querySelectorAll('tr[data-status]');
            rows.forEach(function (row) {
                if (!status || status === 'all') {
                    row.style.display = '';
                } else {
                    row.style.display = row.getAttribute('data-status') === status ? '' : 'none';
                }
            });
        });
    });
}

// ---------------------------------------------------------------------------
// 6. Calendar month navigation
// ---------------------------------------------------------------------------
function initCalendarNavigation() {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-calendar-nav]');
        if (!btn) return;

        e.preventDefault();

        var direction = parseInt(btn.getAttribute('data-calendar-nav'), 10);
        var params = new URLSearchParams(window.location.search);
        var now = new Date();
        var month = parseInt(params.get('month'), 10) || (now.getMonth() + 1);
        var year = parseInt(params.get('year'), 10) || now.getFullYear();

        month += direction;
        if (month > 12) { month = 1; year++; }
        if (month < 1) { month = 12; year--; }

        params.set('month', month);
        params.set('year', year);
        window.location.search = params.toString();
    });
}

// ---------------------------------------------------------------------------
// 7. Flash message auto-dismiss
// ---------------------------------------------------------------------------
function initFlashAutoDismiss() {
    document.querySelectorAll('.flash-message').forEach(function (el) {
        setTimeout(function () {
            el.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            el.style.opacity = '0';
            el.style.transform = 'translateY(-10px)';
            setTimeout(function () { el.remove(); }, 300);
        }, 5000);
    });
}

// ---------------------------------------------------------------------------
// 8. Image preview on upload
// ---------------------------------------------------------------------------
function initImagePreview() {
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
        input.addEventListener('change', function () {
            var previewId = this.getAttribute('data-preview');
            var preview = document.getElementById(previewId);
            if (!preview) return;

            if (this.files && this.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(this.files[0]);
            }
        });
    });
}

// ---------------------------------------------------------------------------
// 9. Confirm dangerous actions
// ---------------------------------------------------------------------------
function initConfirmDialogs() {
    document.addEventListener('click', function (e) {
        var target = e.target.closest('[data-confirm]');
        if (!target) return;

        var message = target.getAttribute('data-confirm') || 'Er du sikker?';
        if (!confirm(message)) {
            e.preventDefault();
        }
    });
}
