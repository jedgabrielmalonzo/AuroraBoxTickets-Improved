<!-- Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content auth-modal">
      <div class="row g-0">
        <!-- Form Section -->
        <div class="col-md-6 form-section p-4">
          <form id="modalLoginForm" method="post" action="login.php">
            <h3 class="mb-4 text-center fw-bold">Login</h3>

            <!-- Error Alert -->
            <div id="loginErrorAlert" class="alert alert-danger d-none" role="alert">
              <span id="loginErrorMessage"></span>
            </div>
            <!-- Success Alert -->
            <div id="loginSuccessAlert" class="alert alert-success d-none" role="alert">
              <span id="loginSuccessMessage"></span>
            </div>

            <div class="mb-3">
              <input type="email" name="email" id="modalEmail" class="form-control" placeholder="Email" required>
            </div>
            <div class="mb-3 position-relative">
              <input type="password" name="password" id="modalPassword" class="form-control" placeholder="Password" required>
              <i class="fas fa-eye password-toggle" id="toggleModalPassword"
                 style="position:absolute;right:15px;top:50%;transform:translateY(-50%);cursor:pointer;color:#888;"></i>
            </div>

            <button type="submit" class="btn btn-gradient w-100 mb-3" id="modalLoginBtn">
              <span class="spinner-border spinner-border-sm d-none" id="modalLoginSpinner"></span>
              Login
            </button>

            <a href="<?= $client->createAuthUrl() ?>" class="btn btn-google w-100 mb-3">
              <i class="fab fa-google me-2"></i> Continue with Google
            </a>

            <p class="text-center">
              Don't have an account? 
              <a href="#" data-bs-toggle="modal" data-bs-target="#signupModal" data-bs-dismiss="modal">Sign up</a>
            </p>
          </form>
        </div>

        <!-- Welcome Section -->
        <div class="col-md-6 welcome-section d-flex flex-column justify-content-center text-center text-white p-4">
          <h2 class="fw-bold">Welcome Back!</h2>
          <p>Log in to access your account and continue where you left off.</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Signup Modal -->
<div class="modal fade" id="signupModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content auth-modal">
      <div class="row g-0">
        <!-- Form Section -->
        <div class="col-md-6 form-section p-4">
          <form id="modalSignupForm" method="post">
            <h3 class="mb-4 text-center fw-bold">Sign Up</h3>

            <!-- Error Alert -->
            <div id="signupErrorAlert" class="alert alert-danger d-none" role="alert">
              <span id="signupErrorMessage"></span>
            </div>

            <div class="mb-3">
              <input type="text" name="name" class="form-control" placeholder="Full Name" required>
            </div>
            <div class="mb-3">
              <input type="email" name="email" class="form-control" placeholder="Email" required>
            </div>
            <div class="mb-3 position-relative">
              <input type="password" name="password" id="signupPassword" class="form-control" placeholder="Password" required>
              <i class="fas fa-eye password-toggle" id="toggleSignupPassword"
                 style="position:absolute;right:15px;top:50%;transform:translateY(-50%);cursor:pointer;color:#888;"></i>
            </div>

            <button type="submit" class="btn btn-gradient w-100 mb-3" id="modalSignupBtn">
              <span class="spinner-border spinner-border-sm d-none" id="modalSignupSpinner"></span>
              Sign Up
            </button>

            <a href="<?= $client->createAuthUrl() ?>" class="btn btn-google w-100 mb-3">
              <i class="fab fa-google me-2"></i> Sign Up with Google
            </a>

            <p class="text-center">
              Already have an account? 
              <a href="#" data-bs-toggle="modal" data-bs-target="#loginModal" data-bs-dismiss="modal">Login</a>
            </p>
          </form>
        </div>

        <!-- Welcome Section -->
        <div class="col-md-6 welcome-section d-flex flex-column justify-content-center text-center text-white p-4">
          <h2 class="fw-bold">Glad to see You!</h2>
          <p>Join us and start your journey today!</p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Email Exists Error Modal -->
<div class="modal fade" id="emailExistsModal" tabindex="-1" aria-labelledby="emailExistsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content otp-modal-content">
      <div class="modal-header border-0 otp-modal-header">
        <h5 class="modal-title otp-modal-title" id="emailExistsModalLabel">Account Already Exists</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4 otp-modal-body">
        <div class="mb-3">
          <i class="fas fa-user-check fa-3x otp-icon mb-3"></i>
          <h4 class="otp-modal-title">Welcome Back!</h4>
          <p class="otp-text-muted">The email address:</p>
          <p class="otp-email-display" id="existingEmailDisplay"></p>
          <p class="otp-text-muted">is already registered with AuroraBox.</p>
        </div>
        
        <button id="switchToLoginBtn" class="btn w-100 mb-3 otp-verify-btn" type="button">
          <i class="fas fa-sign-in-alt me-2"></i>Log In Instead
        </button>
        
        <div class="text-center">
          <button type="button" class="btn btn-link p-0 otp-resend-btn" data-bs-dismiss="modal">
            <i class="fas fa-arrow-left me-1"></i>Try Different Email
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- OTP Modal -->
<div class="modal fade" id="otpModal" tabindex="-1" aria-labelledby="otpModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content otp-modal-content">
      <div class="modal-header border-0 otp-modal-header">
        <h5 class="modal-title otp-modal-title" id="otpModalLabel">Verify Your Email</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center p-4 otp-modal-body">
        <div class="mb-3">
          <i class="fas fa-envelope-circle-check fa-3x otp-icon mb-3"></i>
          <h4 class="otp-modal-title">Check Your Email</h4>
          <p class="otp-text-muted">We've sent a 6-digit verification code to:</p>
          <p class="otp-email-display" id="otpEmailDisplay"></p>
        </div>
        
        <div id="otpMessage"></div>
        
        <div class="mb-3">
          <input type="text" 
                 id="otpInput" 
                 class="form-control form-control-lg otp-input" 
                 placeholder="Enter 6-digit OTP" 
                 maxlength="6" 
                 required>
        </div>
        
        <button id="verifyOtpBtn" class="btn w-100 mb-3 otp-verify-btn" type="button">
          <i class="fas fa-check-circle me-2"></i>Verify OTP
        </button>
        
        <div class="text-center">
          <p class="otp-text-muted mb-2">Didn't receive the code?</p>
          <button id="resendOtpBtn" class="btn btn-link p-0 otp-resend-btn">
            <i class="fas fa-redo me-1"></i>Resend OTP
          </button>
        </div>
        
        <div class="mt-3">
          <small class="otp-text-muted">
            <i class="fas fa-clock me-1"></i>Code expires in 5 minutes
          </small>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Password Toggles
    const toggleModalPassword = document.getElementById('toggleModalPassword');
    const modalPassword = document.getElementById('modalPassword');
    if (toggleModalPassword && modalPassword) {
        toggleModalPassword.addEventListener('click', function() {
            const type = modalPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            modalPassword.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }

    const toggleSignupPassword = document.getElementById('toggleSignupPassword');
    const signupPassword = document.getElementById('signupPassword');
    if (toggleSignupPassword && signupPassword) {
        toggleSignupPassword.addEventListener('click', function() {
            const type = signupPassword.getAttribute('type') === 'password' ? 'text' : 'password';
            signupPassword.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }

    // Login Form Handler
    const modalLoginForm = document.getElementById('modalLoginForm');
    if (modalLoginForm) {
        modalLoginForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const loginBtn = document.getElementById('modalLoginBtn');
            const loginSpinner = document.getElementById('modalLoginSpinner');
            const loginErrorAlert = document.getElementById('loginErrorAlert');
            const loginSuccessAlert = document.getElementById('loginSuccessAlert');
            const loginErrorMessage = document.getElementById('loginErrorMessage');
            const loginSuccessMessage = document.getElementById('loginSuccessMessage');

            // Hide alerts
            if (loginErrorAlert) loginErrorAlert.classList.add('d-none');
            if (loginSuccessAlert) loginSuccessAlert.classList.add('d-none');

            // Set loading
            if (loginBtn && loginSpinner) {
                loginBtn.disabled = true;
                loginSpinner.classList.remove('d-none');
                loginBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Logging in...';
            }

            try {
                const formData = new FormData(this);
                const response = await fetch('login.php', {
                    method: 'POST',
                    body: formData
                });

                const text = await response.text();
                
                if (text.includes('Location:') || response.redirected) {
                    if (loginSuccessMessage) loginSuccessMessage.textContent = 'Login successful! Redirecting...';
                    if (loginSuccessAlert) loginSuccessAlert.classList.remove('d-none');
                    setTimeout(() => window.location.href = 'index.php', 1500);
                } 
                else if (text.includes('Invalid Login') || text.includes('style="color: red;"') || text.includes('<html')) {
                    if (loginErrorMessage) loginErrorMessage.textContent = 'Invalid email or password';
                    if (loginErrorAlert) loginErrorAlert.classList.remove('d-none');
                }
                else {
                    if (loginSuccessMessage) loginSuccessMessage.textContent = 'Login successful! Redirecting...';
                    if (loginSuccessAlert) loginSuccessAlert.classList.remove('d-none');
                    setTimeout(() => window.location.href = 'index.php', 1500);
                }

            } catch (error) {
                if (loginErrorMessage) loginErrorMessage.textContent = 'An error occurred. Please try again.';
                if (loginErrorAlert) loginErrorAlert.classList.remove('d-none');
            } finally {
                if (loginBtn && loginSpinner) {
                    loginBtn.disabled = false;
                    loginSpinner.classList.add('d-none');
                    loginBtn.innerHTML = 'Login';
                }
            }
        });
    }

    // Signup Form Handler - Simplified and Integrated
    const modalSignupForm = document.getElementById('modalSignupForm');
    if (modalSignupForm) {
        modalSignupForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const signupBtn = document.getElementById('modalSignupBtn');
            const signupSpinner = document.getElementById('modalSignupSpinner');
            const signupErrorAlert = document.getElementById('signupErrorAlert');
            const signupErrorMessage = document.getElementById('signupErrorMessage');

            // Hide alerts
            if (signupErrorAlert) signupErrorAlert.classList.add('d-none');

            // Set loading
            if (signupBtn && signupSpinner) {
                signupBtn.disabled = true;
                signupSpinner.classList.remove('d-none');
                signupBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Sending OTP...';
            }

            try {
                const formData = new FormData(this);
                const response = await fetch('send_otp.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.text();
                console.log('Signup response:', result);

                if (result.startsWith('success:')) {
                    // Close signup modal
                    const signupModal = bootstrap.Modal.getInstance(document.getElementById('signupModal'));
                    if (signupModal) signupModal.hide();
                    
                    // Show OTP modal after delay
                    setTimeout(() => {
                        const emailInput = modalSignupForm.querySelector('input[name="email"]');
                        const otpEmailDisplay = document.getElementById('otpEmailDisplay');
                        if (otpEmailDisplay && emailInput) {
                            otpEmailDisplay.textContent = emailInput.value;
                        }
                        
                        const otpModal = new bootstrap.Modal(document.getElementById('otpModal'), {
                            backdrop: 'static',
                            keyboard: false
                        });
                        otpModal.show();
                        
                        // Focus on OTP input
                        setTimeout(() => {
                            const otpInput = document.getElementById('otpInput');
                            if (otpInput) otpInput.focus();
                        }, 500);
                    }, 300);
                }
                else if (result.startsWith('error:Email already exists')) {
                    // Close signup modal
                    const signupModal = bootstrap.Modal.getInstance(document.getElementById('signupModal'));
                    if (signupModal) signupModal.hide();
                    
                    // Show email exists modal
                    setTimeout(() => {
                        const emailInput = modalSignupForm.querySelector('input[name="email"]');
                        const existingEmailDisplay = document.getElementById('existingEmailDisplay');
                        if (existingEmailDisplay && emailInput) {
                            existingEmailDisplay.textContent = emailInput.value;
                        }
                        
                        const emailExistsModal = new bootstrap.Modal(document.getElementById('emailExistsModal'));
                        emailExistsModal.show();
                    }, 300);
                }
                else {
                    // Show error in signup modal
                    const errorMsg = result.replace('error:', '') || 'Failed to send OTP. Please try again.';
                    if (signupErrorMessage) signupErrorMessage.textContent = errorMsg;
                    if (signupErrorAlert) signupErrorAlert.classList.remove('d-none');
                }

            } catch (error) {
                if (signupErrorMessage) signupErrorMessage.textContent = 'Network error! Please try again.';
                if (signupErrorAlert) signupErrorAlert.classList.remove('d-none');
            } finally {
                if (signupBtn && signupSpinner) {
                    signupBtn.disabled = false;
                    signupSpinner.classList.add('d-none');
                    signupBtn.innerHTML = 'Sign Up';
                }
            }
        });
    }

    // OTP Input - Only allow numbers and auto-submit
    const otpInput = document.getElementById('otpInput');
    if (otpInput) {
        otpInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length === 6) {
                setTimeout(() => document.getElementById('verifyOtpBtn').click(), 500);
            }
        });

        otpInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                document.getElementById('verifyOtpBtn').click();
            }
        });
    }

    // Verify OTP Button
    const verifyOtpBtn = document.getElementById('verifyOtpBtn');
    if (verifyOtpBtn) {
        verifyOtpBtn.addEventListener('click', function() {
            const otp = document.getElementById('otpInput').value.trim();
            const email = document.getElementById('otpEmailDisplay').textContent;
            const messageDiv = document.getElementById('otpMessage');
            
            if (!otp || otp.length !== 6) {
                messageDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Please enter a valid 6-digit OTP</div>';
                return;
            }
            
            // Disable button and show loading
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verifying...';
            messageDiv.innerHTML = '';
            
            fetch('verify_otp.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'otp=' + encodeURIComponent(otp) + '&email=' + encodeURIComponent(email)
            })
            .then(response => response.json())
            .then(data => {
                const alertClass = data.success ? 'success' : 'danger';
                const icon = data.success ? 'check-circle' : 'exclamation-circle';
                
                messageDiv.innerHTML = `<div class="alert alert-${alertClass}"><i class="fas fa-${icon} me-2"></i>${data.message}</div>`;
                
                if (data.success) {
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(document.getElementById('otpModal')).hide();
                        location.reload();
                    }, 2000);
                }
            })
            .catch(error => {
                messageDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Connection error. Please try again.</div>';
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = originalText;
            });
        });
    }

    // Resend OTP Button
    const resendOtpBtn = document.getElementById('resendOtpBtn');
    if (resendOtpBtn) {
        resendOtpBtn.addEventListener('click', function() {
            const email = document.getElementById('otpEmailDisplay').textContent;
            const messageDiv = document.getElementById('otpMessage');
            
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';
            
            fetch('send_otp.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'email=' + encodeURIComponent(email) + '&resend=1'
            })
            .then(response => response.text())
            .then(result => {
                if (result.startsWith('success:')) {
                    messageDiv.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>OTP resent successfully!</div>';
                    document.getElementById('otpInput').value = '';
                    document.getElementById('otpInput').focus();
                } else {
                    messageDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-circle me-2"></i>Failed to resend OTP</div>';
                }
            })
            .catch(error => {
                messageDiv.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Failed to resend OTP. Please try again.</div>';
            })
            .finally(() => {
                setTimeout(() => {
                    this.disabled = false;
                    this.innerHTML = originalText;
                }, 3000);
            });
        });
    }

    // Switch to Login Button
    const switchToLoginBtn = document.getElementById('switchToLoginBtn');
    if (switchToLoginBtn) {
        switchToLoginBtn.addEventListener('click', function() {
            bootstrap.Modal.getInstance(document.getElementById('emailExistsModal')).hide();
            setTimeout(() => {
                const loginModal = new bootstrap.Modal(document.getElementById('loginModal'));
                loginModal.show();
                
                const existingEmailDisplay = document.getElementById('existingEmailDisplay');
                const modalEmail = document.getElementById('modalEmail');
                if (existingEmailDisplay && modalEmail) {
                    modalEmail.value = existingEmailDisplay.textContent;
                }
            }, 300);
        });
    }

    // Clear forms when modals close
    const loginModal = document.getElementById('loginModal');
    if (loginModal) {
        loginModal.addEventListener('hidden.bs.modal', function() {
            const form = document.getElementById('modalLoginForm');
            if (form) form.reset();
        });
    }

    const signupModal = document.getElementById('signupModal');
    if (signupModal) {
        signupModal.addEventListener('hidden.bs.modal', function() {
            const form = document.getElementById('modalSignupForm');
            if (form) form.reset();
        });
    }
});
</script>

<style>
.alert {
    margin-bottom: 1rem;
    padding: 0.75rem 1.25rem;
    border: 1px solid transparent;
    border-radius: 0.25rem;
}

.alert-danger {
    color: #721c24;
    background-color: #f8d7da;
    border-color: #f5c6cb;
}

.alert-success {
    color: #155724;
    background-color: #d4edda;
    border-color: #c3e6cb;
}

.spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

.btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.d-none {
    display: none !important;
}

.auth-modal {
    border-radius: 20px;
    overflow: hidden;
    border: none;
    background: #fff;
    box-shadow: 0 8px 30px rgba(0,0,0,0.2);
}

.form-control {
    border-radius: 10px;
    padding: 12px;
    border: 1px solid #ddd;
}

.btn-gradient {
    background: linear-gradient(135deg, #684D8F, #3f2b63);
    border: none;
    color: #fff;
    font-weight: 600;
    padding: 12px;
    border-radius: 12px;
    transition: 0.3s;
}

.btn-gradient:hover {
    opacity: 0.9;
}

.btn-google {
    background: #42245f;
    color: #fff;
    font-weight: 600;
    padding: 12px;
    border-radius: 12px;
    border: none;
    transition: 0.3s;
}

.btn-google:hover {
    background: #351c4b;
}

.welcome-section {
    background: linear-gradient(135deg, #684D8F, #3f2b63);
}

.password-toggle {
    cursor: pointer;
    user-select: none;
}

.password-toggle:hover {
    color: #555;
}
</style>