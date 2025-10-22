document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".see-more-btn").forEach(btn => {
    btn.addEventListener("click", function () {
      const desc = this.previousElementSibling;
      const fullText = desc.getAttribute("data-full");
      const isExpanded = this.getAttribute("data-expanded") === "true";

      if (!isExpanded) {
        desc.textContent = fullText;
        this.textContent = "Less";
        this.setAttribute("data-expanded", "true");
      } else {
        desc.textContent = fullText.substring(0, 120) + "...";
        this.textContent = "More";
        this.setAttribute("data-expanded", "false");
      }
    });
  });
});

