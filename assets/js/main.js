/**
 * Event Platform - Main JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize components
    initModals();
    initToasts();
    initConfirmDialogs();
    initCodeInput();
});

/**
 * Modal System
 */
function initModals() {
    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(overlay => {
        overlay.addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal(this.id);
            }
        });
    });

    // Close modal on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const activeModal = document.querySelector('.modal-overlay--active');
            if (activeModal) {
                closeModal(activeModal.id);
            }
        }
    });
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('modal-overlay--active');
        document.body.style.overflow = 'hidden';

        // Focus first input
        const firstInput = modal.querySelector('input, textarea, select');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('modal-overlay--active');
        document.body.style.overflow = '';
    }
}

/**
 * Toast Notifications
 */
let toastContainer = null;

function initToasts() {
    // Create toast container if it doesn't exist
    toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.style.cssText = `
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 1100;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 400px;
        `;
        document.body.appendChild(toastContainer);
    }
}

function showToast(message, type = 'success', duration = 4000) {
    if (!toastContainer) initToasts();

    const toast = document.createElement('div');
    toast.className = `alert alert--${type}`;
    toast.style.cssText = `
        animation: slideInRight 0.3s ease-out;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    `;

    const icon = type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ';
    toast.innerHTML = `<span>${icon}</span><span>${message}</span>`;

    toastContainer.appendChild(toast);

    // Auto remove
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease-in forwards';
        setTimeout(() => toast.remove(), 300);
    }, duration);

    // Click to dismiss
    toast.addEventListener('click', () => {
        toast.style.animation = 'slideOutRight 0.3s ease-in forwards';
        setTimeout(() => toast.remove(), 300);
    });
}

/**
 * Confirm Dialogs
 */
function initConfirmDialogs() {
    document.querySelectorAll('[data-confirm]').forEach(element => {
        element.addEventListener('click', function(e) {
            const message = this.dataset.confirm || 'Er du sikker?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

/**
 * Code Input (Guest codes)
 */
function initCodeInput() {
    const codeInput = document.querySelector('.code-input');
    if (codeInput) {
        // Only allow numbers
        codeInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Auto-submit when complete
        codeInput.addEventListener('keyup', function() {
            if (this.value.length === 6) {
                setTimeout(() => this.form.submit(), 150);
            }
        });
    }
}

/**
 * API Helper
 */
async function api(endpoint, options = {}) {
    const defaults = {
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };

    const config = { ...defaults, ...options };
    if (options.body && typeof options.body === 'object') {
        config.body = JSON.stringify(options.body);
    }

    try {
        const response = await fetch('/api/' + endpoint, config);
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Request failed');
        }

        return data;
    } catch (error) {
        console.error('API Error:', error);
        throw error;
    }
}

/**
 * Form Validation
 */
function validateForm(form) {
    let isValid = true;

    form.querySelectorAll('[required]').forEach(input => {
        if (!input.value.trim()) {
            input.classList.add('form-input--error');
            isValid = false;
        } else {
            input.classList.remove('form-input--error');
        }
    });

    return isValid;
}

/**
 * Formatting Utilities
 */
function formatNumber(num) {
    return new Intl.NumberFormat('da-DK').format(num);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('da-DK', {
        style: 'currency',
        currency: 'DKK',
        minimumFractionDigits: 0
    }).format(amount);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('da-DK', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
}

/**
 * Debounce Helper
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Add CSS animation keyframes
 */
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);
