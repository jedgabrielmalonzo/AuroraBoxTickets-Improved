
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".see-details-btn").forEach(btn => {
    btn.addEventListener("click", function () {
      const parkId = this.closest(".wishlist-card").dataset.id;
      // Papunta sa park_info.php at ipapasa yung park ID
      window.location.href = `park_info.php?id=${parkId}`;
    });
  });
});
