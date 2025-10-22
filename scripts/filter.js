
document.addEventListener("DOMContentLoaded", () => {
  const sortSelect = document.getElementById("sortSelect");
  const filterSelect = document.getElementById("filterSelect");
  const wishlistGrid = document.getElementById("wishlist-items");

  function sortWishlist() {
    const cards = Array.from(wishlistGrid.querySelectorAll(".wishlist-card"));
    const sortValue = sortSelect.value;

    cards.sort((a, b) => {
      const nameA = a.querySelector(".wishlist-name").textContent.trim().toLowerCase();
      const nameB = b.querySelector(".wishlist-name").textContent.trim().toLowerCase();

      if (sortValue === "name") {
        return nameA.localeCompare(nameB); // A → Z
      }
      if (sortValue === "most-rated") {
        const ratingA = parseFloat(a.dataset.rating || 0);
        const ratingB = parseFloat(b.dataset.rating || 0);
        return ratingB - ratingA; // Highest rating first
      }
      return 0;
    });

    wishlistGrid.innerHTML = "";
    cards.forEach(card => wishlistGrid.appendChild(card));
  }

    // FILTERING 
    function filterWishlist() {
      const filterValue = filterSelect.value; // keep as string or number
      const cards = wishlistGrid.querySelectorAll(".wishlist-card");
    
      cards.forEach(card => {
        const category = card.dataset.category; // numeric string
        // Display if "all" selected or if category matches
        card.style.display = (filterValue === "all" || category === filterValue) ? "flex" : "none";
      });
    
      sortWishlist(); // optional: sort visible cards after filtering
    }
    
      sortSelect.addEventListener("change", sortWishlist);
      filterSelect.addEventListener("change", filterWishlist);
    });
   