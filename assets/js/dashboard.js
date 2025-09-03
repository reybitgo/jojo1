// assets/js/dashboard.js
document.addEventListener('DOMContentLoaded', function () {
    // Auto-dismiss flash messages
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // Mobile menu toggle (if needed)
    const toggleBtn = document.querySelector('.sidebar-toggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function () {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    }

    // Confirm purchase dialog
    const purchaseBtns = document.querySelectorAll('[data-confirm-purchase]');
    purchaseBtns.forEach(btn => {
        btn.addEventListener('click', function (e) {
            if (!confirm('Are you sure you want to purchase this package?')) {
                e.preventDefault();
            }
        });
    });

    // Balance check before purchase
    const checkoutForm = document.querySelector('#checkout-form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function (e) {
            const balance = parseFloat(this.dataset.balance);
            const price = parseFloat(this.dataset.price);

            if (balance < price) {
                e.preventDefault();
                alert('Insufficient funds. Please add funds to your e-wallet first.');
            }
        });
    }
});

// AJAX helper for dynamic content loading
function loadDashboardStats() {
    fetch('../api/user_stats.php')
        .then(response => response.json())
        .then(data => {
            // Update stats dynamically
            document.querySelector('#ewallet-balance').textContent = data.balance;
            document.querySelector('#referrals-count').textContent = data.referrals;
        })
        .catch(console.error);
}