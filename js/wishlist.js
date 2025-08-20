// Wishlist functionality
document.addEventListener('DOMContentLoaded', function() {
    // Handle wishlist buttons
    document.querySelectorAll('.js-add-wishlist').forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const button = this;
            const productSlug = button.getAttribute('data-product');
            
            fetch('<?= BASE_URL ?>includes/ajax_wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=add&product=${encodeURIComponent(productSlug)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update button appearance
                    const heartIcon = button.querySelector('svg') || button;
                    if (data.in_wishlist) {
                        heartIcon.setAttribute('fill', 'red');
                        button.setAttribute('title', 'Remove from wishlist');
                        showToast('Added to wishlist', 'success');
                    } else {
                        heartIcon.removeAttribute('fill');
                        button.setAttribute('title', 'Add to wishlist');
                    }
                    
                    // Update wishlist counter if exists
                    const counter = document.querySelector('.wishlist-counter');
                    if (counter) {
                        counter.textContent = parseInt(counter.textContent) + (data.in_wishlist ? 1 : -1);
                    }
                } else if (data.login_required) {
                    window.location.href = '<?= BASE_URL ?>account/login.php?redirect=' + 
                        encodeURIComponent(window.location.pathname);
                } else {
                    showToast(data.message || 'Error updating wishlist', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Failed to update wishlist', 'error');
            });
        });
    });
    
    // Initialize wishlist button states
    function initializeWishlistButtons() {
        const wishlistButtons = document.querySelectorAll('.js-add-wishlist');
        if (!wishlistButtons.length) return;
        
        // Get user's wishlist items if logged in
        <?php if (isset($_SESSION['user_id'])): ?>
            fetch('<?= BASE_URL ?>includes/ajax_wishlist.php?action=get_items')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.items) {
                        document.querySelectorAll('.js-add-wishlist').forEach(button => {
                            const productSlug = button.getAttribute('data-product');
                            if (data.items.includes(productSlug)) {
                                const heartIcon = button.querySelector('svg') || button;
                                heartIcon.setAttribute('fill', 'red');
                                button.setAttribute('title', 'Remove from wishlist');
                            }
                        });
                    }
                })
                .catch(error => console.error('Error initializing wishlist:', error));
        <?php endif; ?>
    }
    
    // Call initialization
    initializeWishlistButtons();
    
    // Toast notification function
    function showToast(message, type = 'success') {
        const toast = document.createElement('div');
        toast.className = `toast toast-${type}`;
        toast.textContent = message;
        document.body.appendChild(toast);
        
        setTimeout(() => {
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }, 100);
    }
});