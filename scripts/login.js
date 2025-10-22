function showLoginModal() {
    const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
    loginModal.show();
}

function handleWishlistClick() {
    if (!isLoggedIn) {
        showLoginModal();
    } else {
        window.location.href = 'wishlist.php';
    }
}

function handleCartClick() {
    if (!isLoggedIn) {
        showLoginModal();
    } else {
        window.location.href = 'cart.php';
    }
}

function handleAccountClick() {
    if (!isLoggedIn) {
        showLoginModal();
    } else {
        window.location.href = 'account.php';
    }
}