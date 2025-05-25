/**
 * My Account Display JavaScript
 * 
 * @package Astra Child
 * @since 1.0.0
 */

document.addEventListener('DOMContentLoaded', function() {
    console.log('My Account page loaded');
    
    // Only run if we're on the main account page (not password reset steps)
    if (window.location.search.includes('password_reset_step')) {
        return;
    }
    
    // Store original name values
    var originalFirstName = document.getElementById('first-name').value;
    var originalLastName = document.getElementById('last-name').value;
    var displayName = document.getElementById('display-name');
    
    console.log('Initial values:', {originalFirstName, originalLastName});

    // Name editing functionality
    document.querySelector('.edit-name-btn').addEventListener('click', function(e) {
        console.log('Edit button clicked');
        e.preventDefault();
        
        // Don't overwrite the original values here - we need them for comparison later
        // The input fields already have the correct values from the PHP variables
        console.log('Original values for comparison:', {originalFirstName, originalLastName});
        
        // Show edit fields
        document.querySelector('.name-row').style.display = 'none';
        document.querySelectorAll('.name-edit-row').forEach(function(row) {
            row.style.display = 'flex';
        });
    });

    document.querySelector('.cancel-name-btn').addEventListener('click', function(e) {
        console.log('Cancel button clicked');
        e.preventDefault();
        
        // Restore original values
        document.getElementById('first-name').value = originalFirstName;
        document.getElementById('last-name').value = originalLastName;
        
        document.querySelector('.name-row').style.display = 'flex';
        document.querySelectorAll('.name-edit-row').forEach(function(row) {
            row.style.display = 'none';
        });
    });

    document.querySelector('.save-name-btn').addEventListener('click', function(e) {
        console.log('Save button clicked');
        e.preventDefault();
        
        var firstName = document.getElementById('first-name').value.trim();
        var lastName = document.getElementById('last-name').value.trim();
        
        if (firstName === '' && lastName === '') {
            alert('Please enter at least a first name or last name');
            return;
        }

        // Check if anything actually changed
        if (firstName === originalFirstName && lastName === originalLastName) {
            console.log('No changes detected, just hiding edit form');
            // No changes, just hide the edit form
            document.querySelector('.name-row').style.display = 'flex';
            document.querySelectorAll('.name-edit-row').forEach(function(row) {
                row.style.display = 'none';
            });
            return;
        }

        console.log('Changes detected, sending to server');
        
        // Create form data for AJAX request
        var formData = new FormData();
        formData.append('action', 'update_user_name');
        formData.append('first_name', firstName);
        formData.append('last_name', lastName);
        formData.append('nonce', MyAccountAjax.update_user_name_nonce);

        // Send AJAX request
        fetch(MyAccountAjax.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Refresh the page with success parameter
                window.location.href = window.location.pathname + '?name_updated=1';
            } else {
                alert('Error updating name: ' + (data.data || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error updating name. Please try again.');
        });
    });

    // Handle Enter key in name inputs
    document.querySelectorAll('.name-input').forEach(function(input) {
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.querySelector('.save-name-btn').click();
            }
        });
    });

    // Password reset functionality
    document.querySelector('.reset-password-btn').addEventListener('click', function(e) {
        e.preventDefault();
        console.log('Reset password clicked');
        
        if (confirm('Are you sure you want to reset your password? A verification code will be sent to your phone number.')) {
            initiatePasswordReset();
        }
    });

    function initiatePasswordReset() {
        var formData = new FormData();
        formData.append('action', 'initiate_password_reset');
        formData.append('nonce', MyAccountAjax.password_reset_nonce);

        fetch(MyAccountAjax.ajax_url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Verification code sent to your phone number. Please check your messages.');
                window.location.href = window.location.pathname + '?password_reset_step=verify';
            } else {
                alert('Error: ' + (data.data || 'Unable to send verification code'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error sending verification code. Please try again.');
        });
    }
}); 