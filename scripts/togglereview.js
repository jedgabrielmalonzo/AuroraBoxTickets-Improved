document.addEventListener("DOMContentLoaded", () => {
const toggleBtn = document.getElementById("toggleReviews");
if (toggleBtn) {
let expanded = false; // track state

toggleBtn.addEventListener("click", () => {
const extraReviews = document.querySelectorAll(".extra-review");
expanded = !expanded;

extraReviews.forEach(r => {
if (expanded) {
r.classList.remove("d-none");
} else {
r.classList.add("d-none");
}
});

toggleBtn.innerText = expanded ? "Show Less Reviews ▲" : "Show More Reviews ▼";
});
}
});
