
    const menuLinks = document.querySelectorAll('.acc-menu a');
    const cards = document.querySelectorAll('.acc-card');

    // Handle navigation
    menuLinks.forEach(link => {
      link.addEventListener('click', () => {
        menuLinks.forEach(l => l.classList.remove('active'));
        link.classList.add('active');

        const targetId = link.getAttribute('data-target');
        cards.forEach(card => {
          if (card.id === targetId) {
            card.classList.add('active');
          } else {
            card.classList.remove('active');
          }
        });
      });
    });

    // Handle URL hash navigation
    window.addEventListener('load', () => {
      const hash = window.location.hash.substring(1);
      if (hash) {
        const targetLink = document.querySelector(`.acc-menu [data-target="${hash}"]`);
        if (targetLink) {
          targetLink.click();
        }
      }
    });

    // Profile picture change
    const avatarInput = document.getElementById('avatarInput');
    const avatarImg = document.getElementById('avatarImg');

    if (avatarInput) {
      avatarInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = function(evt) {
            avatarImg.src = evt.target.result;
          }
          reader.readAsDataURL(file);
        }
      });
    }

    // Auto-hide alerts after 5 seconds
    setTimeout(() => {
      const alerts = document.querySelectorAll('.acc-alert');
      alerts.forEach(alert => {
        alert.style.opacity = '0';
        setTimeout(() => alert.remove(), 300);
      });
    }, 5000);