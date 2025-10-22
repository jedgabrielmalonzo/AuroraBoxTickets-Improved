
// Updated scroll function to handle multiple carousels
function scrollCarousel(carouselId, scrollAmount) {
    const carousel = document.getElementById(carouselId);
    carousel.scrollBy({ left: scrollAmount, behavior: "smooth" });
}

document.addEventListener("DOMContentLoaded", function () {
    // Keep the original slider functionality for backwards compatibility
    const slider = document.getElementById("ticket-slider");
    if (slider) {
        const nextBtn = document.getElementById("slider-next");
        const prevBtn = document.getElementById("slider-prev");
        
        if (nextBtn) {
            nextBtn.addEventListener("click", () => {
                slider.scrollBy({ left: 300, behavior: "smooth" });
            });
        }
        
        if (prevBtn) {
            prevBtn.addEventListener("click", () => {
                slider.scrollBy({ left: -300, behavior: "smooth" });
            });
        }
    }
});


