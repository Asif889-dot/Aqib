<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - My Application</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="container-fluid h-100">
            <div class="row h-100 justify-content-center align-items-center">
                <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-4">
                    <div class="card login-card">
                        <div class="card-body p-5">
                            <!-- Logo/Brand -->
                            <div class="text-center mb-4">
                                <div class="brand-logo mb-3">
                                    <i class="bi bi-shield-lock-fill"></i>
                                </div>
                                <h2 class="card-title text-center mb-2">Welcome Back</h2>
                                <p class="text-muted text-center">Please sign in to your account</p>
                            </div>

                            <!-- Alert Messages -->
                            <div id="alertContainer"></div>

                            <!-- Login Form -->
                            <form id="loginForm" action="backend/login.php" method="POST" novalidate>
                                <!-- Email Field -->
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-envelope-fill"></i>
                                        </span>
                                        <input type="email" 
                                               class="form-control" 
                                               id="email" 
                                               name="email" 
                                               placeholder="Enter your email"
                                               required>
                                        <div class="invalid-feedback">
                                            Please enter a valid email address.
                                        </div>
                                    </div>
                                </div>

                                <!-- Password Field -->
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="bi bi-lock-fill"></i>
                                        </span>
                                        <input type="password" 
                                               class="form-control" 
                                               id="password" 
                                               name="password" 
                                               placeholder="Enter your password"
                                               required>
                                        <button class="btn btn-outline-secondary toggle-password" 
                                                type="button" 
                                                id="togglePassword">
                                            <i class="bi bi-eye-fill"></i>
                                        </button>
                                        <div class="invalid-feedback">
                                            Please enter your password.
                                        </div>
                                    </div>
                                </div>

                                <!-- Remember Me & Forgot Password -->
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <div class="form-check">
                                        <input class="form-check-input" 
                                               type="checkbox" 
                                               id="rememberMe" 
                                               name="remember_me">
                                        <label class="form-check-label" for="rememberMe">
                                            Remember me
                                        </label>
                                    </div>
                                    <a href="#" class="text-decoration-none forgot-password">
                                        Forgot password?
                                    </a>
                                </div>

                                <!-- Submit Button -->
                                <button type="submit" 
                                        class="btn btn-primary w-100 mb-3 login-btn" 
                                        id="loginBtn">
                                    <span class="spinner-border spinner-border-sm d-none" 
                                          id="loginSpinner"></span>
                                    <span id="loginBtnText">Sign In</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Custom JS -->
    <script src="assets/js/script.js"></script>
</body>
</html>