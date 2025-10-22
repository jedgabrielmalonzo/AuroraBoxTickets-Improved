  const toggleBtn = document.getElementById("darkModeToggle");
  const body = document.body;

  // Load saved mode from localStorage
  if (localStorage.getItem("darkMode") === "true") {
    body.classList.add("dark-mode");
    toggleBtn.checked = true;
  }

  toggleBtn.addEventListener("change", () => {
    body.classList.toggle("dark-mode", toggleBtn.checked);
    localStorage.setItem("darkMode", toggleBtn.checked);
  });