document.addEventListener('DOMContentLoaded', function() {
    // Get form elements
    const loginForm = document.getElementById('loginForm');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const togglePasswordBtn = document.getElementById('togglePassword');
    const loginBtn = document.getElementById('loginBtn');
    const loginSpinner = document.getElementById('loginSpinner');
    const loginBtnText = document.getElementById('loginBtnText');
    const alertContainer = document.getElementById('alertContainer');
    const rememberMe = document.getElementById('rememberMe');

    // Toggle Password Visibility
    if (togglePasswordBtn) {
        togglePasswordBtn.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle icon
            const icon = this.querySelector('i');
            icon.classList.toggle('bi-eye-fill');
            icon.classList.toggle('bi-eye-slash-fill');
        });
    }

    // Email Validation
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // Show Alert Message
    function showAlert(message, type = 'danger') {
        const alertHTML = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                <i class="bi bi-${type === 'danger' ? 'exclamation-triangle-fill' : 'check-circle-fill'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        alertContainer.innerHTML = alertHTML;
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            const alert = alertContainer.querySelector('.alert');
            if (alert) {
                alert.classList.remove('show');
                setTimeout(() => alert.remove(), 300);
            }
        }, 5000);
    }

    // Clear validation states
    function clearValidation() {
        emailInput.classList.remove('is-invalid');
        passwordInput.classList.remove('is-invalid');
        alertContainer.innerHTML = '';
    }

    // Form Validation
    function validateForm() {
        let isValid = true;
        
        // Validate Email
        if (!emailInput.value.trim()) {
            emailInput.classList.add('is-invalid');
            emailInput.nextElementSibling.textContent = 'Email is required.';
            isValid = false;
        } else if (!validateEmail(emailInput.value.trim())) {
            emailInput.classList.add('is-invalid');
            emailInput.nextElementSibling.textContent = 'Please enter a valid email address.';
            isValid = false;
        } else {
            emailInput.classList.remove('is-invalid');
        }

        // Validate Password
        if (!passwordInput.value.trim()) {
            passwordInput.classList.add('is-invalid');
            passwordInput.nextElementSibling.textContent = 'Password is required.';
            isValid = false;
        } else if (passwordInput.value.length < 6) {
            passwordInput.classList.add('is-invalid');
            passwordInput.nextElementSibling.textContent = 'Password must be at least 6 characters.';
            isValid = false;
        } else {
            passwordInput.classList.remove('is-invalid');
        }

        return isValid;
    }

    // Real-time validation
    emailInput.addEventListener('blur', function() {
        if (this.value.trim() && !validateEmail(this.value.trim())) {
            this.classList.add('is-invalid');
            this.nextElementSibling.textContent = 'Please enter a valid email address.';
        } else {
            this.classList.remove('is-invalid');
        }
    });

    emailInput.addEventListener('input', function() {
        if (this.classList.contains('is-invalid') && validateEmail(this.value.trim())) {
            this.classList.remove('is-invalid');
        }
    });

    passwordInput.addEventListener('input', function() {
        if (this.classList.contains('is-invalid') && this.value.length >= 6) {
            this.classList.remove('is-invalid');
        }
    });

    // Form Submission
    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        // Clear previous alerts
        alertContainer.innerHTML = '';
        
        // Validate form
        if (!validateForm()) {
            showAlert('Please correct the errors below.', 'danger');
            return;
        }

        // Show loading state
        loginBtn.disabled = true;
        loginSpinner.classList.remove('d-none');
        loginBtnText.textContent = 'Signing in...';

        try {
            // Create FormData
            const formData = new FormData(loginForm);
            
            // Send AJAX request
            const response = await fetch(loginForm.action, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Show success message
                showAlert(data.message || 'Login successful! Redirecting...', 'success');
                
                // Store remember me if checked
                if (rememberMe.checked) {
                    localStorage.setItem('rememberedEmail', emailInput.value);
                } else {
                    localStorage.removeItem('rememberedEmail');
                }

                // Redirect after short delay
                setTimeout(() => {
                    window.location.href = data.redirect || 'dashboard.php';
                }, 1500);
            } else {
                // Show error message
                showAlert(data.message || 'Login failed. Please try again.', 'danger');
                
                // Reset button state
                loginBtn.disabled = false;
                loginSpinner.classList.add('d-none');
                loginBtnText.textContent = 'Sign In';
            }
        } catch (error) {
            console.error('Error:', error);
            showAlert('An error occurred. Please try again later.', 'danger');
            
            // Reset button state
            loginBtn.disabled = false;
            loginSpinner.classList.add('d-none');
            loginBtnText.textContent = 'Sign In';
        }
    });

    // Load remembered email
    const rememberedEmail = localStorage.getItem('rememberedEmail');
    if (rememberedEmail) {
        emailInput.value = rememberedEmail;
        rememberMe.checked = true;
    }

    // Add animation to form elements on load
    const formElements = document.querySelectorAll('.form-control, .input-group-text, .btn');
    formElements.forEach((element, index) => {
        element.style.animationDelay = `${index * 0.1}s`;
    });

    // Forgot password handler
    document.querySelector('.forgot-password').addEventListener('click', function(e) {
        e.preventDefault();
        showAlert('Password reset link will be sent to your email.', 'success');
    });

    // Social login handlers
    document.querySelectorAll('.btn-outline-danger, .btn-outline-primary').forEach(btn => {
        btn.addEventListener('click', function() {
            showAlert('Social login is not implemented yet.', 'danger');
        });
    });
});