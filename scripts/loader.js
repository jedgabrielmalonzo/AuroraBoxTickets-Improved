const loader = document.getElementById("loadingScreen");

window.onload = function() {
    hideLoader();
};

window.addEventListener("pageshow", function(event) {
    if (event.persisted) {
        hideLoader();
    }
});

function hideLoader() {
    loader.style.opacity = "1"; // Ensure it's visible for fade out
    setTimeout(() => {
        loader.style.transition = "opacity 0.6s ease";
        loader.style.opacity = "0"; // Fade out
        setTimeout(() => {
            loader.style.display = "none"; // Hide after fade out
        }, 600);
    }, 200);
}

document.querySelectorAll(".navbar a").forEach(link => {
    const label = (link.dataset.label || "").toLowerCase();
    link.addEventListener("click", function(event) {
        // Check if the link should trigger a modal
        if (label === "wishlist" || label === "account" || label === "cart") {
            // Prevent the loader from showing for these actions
            event.preventDefault(); // Prevent default action
            // You can call the function to show the relevant modal here
            if (label === "wishlist") {
                handleWishlistClick();
            } else if (label === "cart") {
                handleCartClick();
            } else if (label === "account") {
                handleAccountClick();
            }
            return; // Exit the function to avoid further execution
        }

        const currentUrl = window.location.href.split('#')[0];
        if (currentUrl === link.href.split('#')[0]) {
            // If navigating to the same page, do not show the loader
            event.preventDefault(); // Prevent default action
            const hash = link.href.split('#')[1]; // Get hash part
            if (hash) {
                const targetElement = document.getElementById(hash);
                if (targetElement) {
                    targetElement.scrollIntoView({ behavior: 'smooth' }); // Smooth scroll to the section
                }
            }
        } else {
            // If it's a different page, show the loader
            loader.style.display = "flex"; // Show loader immediately
            loader.style.opacity = "1"; // Set opacity to 1
            // Navigate to the link after a short delay
            setTimeout(() => {
                window.location.href = link.href; // Redirect to the link
            }, 500); // Adjust delay as needed
        }
    });
});