/**
 * Password Reset JavaScript
 * 
 * @package Astra Child
 * @since 1.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('Password reset functionality loaded');
    
    // Only run password reset scripts if we're on a password reset step
    if (!window.location.search.includes('password_reset_step')) {
        return;
    }
    
    // Get the current step
    const urlParams = new URLSearchParams(window.location.search);
    const currentStep = urlParams.get('password_reset_step');
    
    if (currentStep === 'verify') {
        initializeVerificationForm();
    } else if (currentStep === 'new_password') {
        initializePasswordForm();
    }
    
    /**
     * Initialize verification form functionality
     */
    function initializeVerificationForm() {
        console.log('Initializing verification form');
        
        const verificationInput = document.getElementById('verification-code');
        const verifyBtn = document.querySelector('.verify-code-btn');
        const cancelBtn = document.querySelector('.cancel-reset-btn');
        const resendBtn = document.querySelector('.resend-code-btn');

        if (!verificationInput || !verifyBtn) {
            console.log('Verification form elements not found');
            return;
        }

        // Auto-format verification code input
        verificationInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Verify code
        verifyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const code = verificationInput.value.trim();
            
            if (code.length !== 6) {
                alert('Please enter a 6-digit verification code');
                return;
            }
            
            verifyPasswordResetCode(code);
        });

        // Cancel reset
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = window.location.pathname;
            });
        }

        // Resend code
        if (resendBtn) {
            resendBtn.addEventListener('click', function(e) {
                e.preventDefault();
                resendPasswordResetCode();
            });
        }

        // Handle Enter key
        verificationInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                verifyBtn.click();
            }
        });
    }
    
    /**
     * Initialize password form functionality
     */
    function initializePasswordForm() {
        console.log('Initializing password form');
        
        const newPasswordInput = document.getElementById('new-password');
        const confirmPasswordInput = document.getElementById('confirm-password');
        const updateBtn = document.querySelector('.update-password-btn');
        const cancelBtn = document.querySelector('.cancel-reset-btn');
        const strengthDiv = document.getElementById('password-strength');

        if (!newPasswordInput || !confirmPasswordInput || !updateBtn) {
            console.log('Password form elements not found');
            return;
        }

        // Password strength checker
        newPasswordInput.addEventListener('input', function() {
            checkPasswordStrength(this.value);
            validatePasswords();
        });

        confirmPasswordInput.addEventListener('input', function() {
            validatePasswords();
        });

        // Update password
        updateBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (newPassword.length < 8) {
                alert('Password must be at least 8 characters long');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                alert('Passwords do not match');
                return;
            }
            
            updatePassword(newPassword);
        });

        // Cancel reset
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function(e) {
                e.preventDefault();
                window.location.href = window.location.pathname;
            });
        }

        /**
         * Check password strength
         */
        function checkPasswordStrength(password) {
            if (!strengthDiv) return;
            
            let strength = 0;
            let message = '';
            
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            if (password.length === 0) {
                message = '';
            } else if (strength < 3) {
                message = '⚠️ Weak password';
                strengthDiv.className = 'password-strength strength-weak';
            } else if (strength < 4) {
                message = '⚡ Medium password';
                strengthDiv.className = 'password-strength strength-medium';
            } else {
                message = '✅ Strong password';
                strengthDiv.className = 'password-strength strength-strong';
            }
            
            strengthDiv.textContent = message;
        }

        /**
         * Validate password fields
         */
        function validatePasswords() {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            // Reset input styles
            newPasswordInput.classList.remove('valid', 'invalid');
            confirmPasswordInput.classList.remove('valid', 'invalid');
            
            let isValid = true;
            
            // Check password strength
            if (newPassword.length < 8) {
                newPasswordInput.classList.add('invalid');
                isValid = false;
            } else {
                newPasswordInput.classList.add('valid');
            }
            
            // Check password match
            if (confirmPassword.length > 0) {
                if (newPassword === confirmPassword) {
                    confirmPasswordInput.classList.add('valid');
                } else {
                    confirmPasswordInput.classList.add('invalid');
                    isValid = false;
                }
            }
            
            updateBtn.disabled = !isValid || newPassword.length < 8 || newPassword !== confirmPassword;
        }
    }
    
    /**
     * Verify password reset code
     */
    function verifyPasswordResetCode(code) {
        var formData = new FormData();
        formData.append('action', 'verify_password_reset_code');
        formData.append('code', code);
        formData.append('nonce', PasswordResetAjax.verify_password_reset_nonce);

        fetch(PasswordResetAjax.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = window.location.pathname + '?password_reset_step=new_password';
            } else {
                alert('Error: ' + (data.data || 'Invalid verification code'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error verifying code. Please try again.');
        });
    }
    
    /**
     * Resend password reset code
     */
    function resendPasswordResetCode() {
        var formData = new FormData();
        formData.append('action', 'initiate_password_reset');
        formData.append('nonce', PasswordResetAjax.password_reset_nonce);

        fetch(PasswordResetAjax.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Verification code sent again to your phone number.');
            } else {
                alert('Error: ' + (data.data || 'Unable to resend code'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error resending code. Please try again.');
        });
    }
    
    /**
     * Update password
     */
    function updatePassword(newPassword) {
        const updateBtn = document.querySelector('.update-password-btn');
        if (updateBtn) {
            updateBtn.disabled = true;
            updateBtn.textContent = 'Updating...';
        }
        
        var formData = new FormData();
        formData.append('action', 'update_password_reset');
        formData.append('new_password', newPassword);
        formData.append('nonce', PasswordResetAjax.update_password_reset_nonce);

        fetch(PasswordResetAjax.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = window.location.pathname + '?password_reset_step=success';
            } else {
                alert('Error: ' + (data.data || 'Unable to update password'));
                if (updateBtn) {
                    updateBtn.disabled = false;
                    updateBtn.textContent = 'Update Password';
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating password. Please try again.');
            if (updateBtn) {
                updateBtn.disabled = false;
                updateBtn.textContent = 'Update Password';
            }
        });
    }
}); 