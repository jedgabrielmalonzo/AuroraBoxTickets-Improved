
  document.getElementById('toggleSignupPassword').addEventListener('click', function () {
    const passwordField = document.getElementById('signupPassword');
    passwordField.type = passwordField.type === 'password' ? 'text' : 'password';
    this.classList.toggle('bx-hide');
  });

    document.getElementById('togglePassword').addEventListener('click', function () {
      const passwordField = document.getElementById('password');
      passwordField.type = passwordField.type === 'password' ? 'text' : 'password';
      this.classList.toggle('fa-eye-slash');
    });
 