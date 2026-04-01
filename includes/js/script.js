// Reusable AJAX utility: supports GET, POST, FormData, and plain string payloads
const Ajax = {
    request: function (url, method = 'GET', data = null, callback = null) {
        const xhr = new XMLHttpRequest();

        xhr.onreadystatechange = function () {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        if (callback) callback(response, null);
                    } catch (e) {
                        // Response is plain text, not JSON
                        if (callback) callback(xhr.responseText, null);
                    }
                } else {
                    if (callback) callback(null, 'Request failed: ' + xhr.status);
                }
            }
        };

        xhr.open(method, url, true);

        if (method === 'POST' && data instanceof FormData) {
            xhr.send(data);
        } else if (method === 'POST') {
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.send(data);
        } else {
            xhr.send();
        }
    },

    get: function (url, callback) {
        this.request(url, 'GET', null, callback);
    },

    post: function (url, data, callback) {
        this.request(url, 'POST', data, callback);
    },

    // Serialize a form element and POST it to its action URL
    submitForm: function (formElement, callback) {
        const formData = new FormData(formElement);
        const url = formElement.action || window.location.href;
        this.post(url, formData, callback);
    }
};

// Attach AJAX submit handler to a form; shows spinner on submit button
function handleAjaxForm(formId, successCallback, errorCallback) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', function (e) {
        e.preventDefault();

        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn ? submitBtn.innerHTML : '';

        if (submitBtn) {
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processing...';
            submitBtn.disabled = true;
        }

        Ajax.submitForm(form, function (response, error) {
            if (submitBtn) {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }

            if (error) {
                if (errorCallback) errorCallback(error);
                else showError(error);
            } else {
                if (successCallback) {
                    successCallback(response);
                } else if (response.success) {
                    showSuccess(response.message || 'Operation completed successfully');
                    // Redirect after 1.5s if a redirect URL is provided
                    if (response.redirect) {
                        setTimeout(() => window.location.href = response.redirect, 1500);
                    }
                } else {
                    showError(response.message || 'Operation failed');
                }
            }
        });
    });
}

// Fetch HTML content via AJAX and inject it into a target element
function loadContent(url, targetElementId, callback) {
    const target = document.getElementById(targetElementId);
    if (!target) return;

    target.innerHTML = '<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>';

    Ajax.get(url, function (response, error) {
        if (error) {
            target.innerHTML = '<div class="alert alert-danger">Failed to load content</div>';
        } else {
            target.innerHTML = response;
            if (callback) callback(response);
        }
    });
}

// Confirm then delete an item via AJAX POST
function deleteItem(url, itemName, callback) {
    if (!confirm(`Are you sure you want to delete ${itemName}?`)) return;

    Ajax.post(url, 'action=delete', function (response, error) {
        if (error) {
            showError('Failed to delete item');
        } else if (response.success) {
            showSuccess(response.message || 'Item deleted successfully');
            if (callback) callback(response);
        } else {
            showError(response.message || 'Failed to delete item');
        }
    });
}

// Live search: fires AJAX after 300ms debounce, minimum 2 characters
function initLiveSearch(inputId, resultsId, searchUrl) {
    const input = document.getElementById(inputId);
    const results = document.getElementById(resultsId);
    if (!input || !results) return;

    let timeout = null;

    input.addEventListener('input', function () {
        clearTimeout(timeout);
        const query = this.value.trim();

        if (query.length < 2) {
            results.innerHTML = '';
            return;
        }

        timeout = setTimeout(function () {
            Ajax.get(searchUrl + '?q=' + encodeURIComponent(query), function (response, error) {
                results.innerHTML = error
                    ? '<div class="alert alert-danger">Search failed</div>'
                    : response;
            });
        }, 300);
    });
}

// Periodically auto-save a form in the background (default every 30 seconds)
function autoSaveForm(formId, saveUrl, interval = 30000) {
    const form = document.getElementById(formId);
    if (!form) return;

    setInterval(function () {
        const formData = new FormData(form);
        formData.append('auto_save', '1');

        Ajax.post(saveUrl, formData, function (response, error) {
            if (!error && response.success) console.log('Form auto-saved');
        });
    }, interval);
}

// Populate a <select> dropdown with data fetched from a JSON endpoint
function populateDropdown(selectId, dataUrl, valueKey = 'id', textKey = 'name') {
    const select = document.getElementById(selectId);
    if (!select) return;

    select.innerHTML = '<option value="">Loading...</option>';

    Ajax.get(dataUrl, function (response, error) {
        if (error || !response.success) {
            select.innerHTML = '<option value="">Failed to load</option>';
            return;
        }

        select.innerHTML = '<option value="">Select an option</option>';

        if (Array.isArray(response.data)) {
            response.data.forEach(function (item) {
                const option = document.createElement('option');
                option.value = item[valueKey];
                option.textContent = item[textKey];
                select.appendChild(option);
            });
        }
    });
}

// Validate a single field on blur via AJAX; applies is-valid / is-invalid Bootstrap classes
function validateField(fieldId, validationUrl) {
    const field = document.getElementById(fieldId);
    if (!field) return;

    let timeout = null;

    field.addEventListener('blur', function () {
        clearTimeout(timeout);
        const value = this.value.trim();
        if (!value) return;

        timeout = setTimeout(function () {
            Ajax.post(validationUrl, 'field=' + fieldId + '&value=' + encodeURIComponent(value), function (response, error) {
                const feedback = field.nextElementSibling;
                if (error || !response.success) {
                    field.classList.add('is-invalid');
                    field.classList.remove('is-valid');
                    if (feedback && feedback.classList.contains('invalid-feedback')) {
                        feedback.textContent = response.message || 'Validation failed';
                    }
                } else {
                    field.classList.add('is-valid');
                    field.classList.remove('is-invalid');
                }
            });
        }, 500);
    });
}

// Notification helper: injects a Bootstrap alert with auto-dismiss
const Notification = {
    show: function (message, type = 'info', duration = 5000) {
        const container = document.querySelector('.content-wrapper') || document.body;
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;

        const icons = {
            success: 'fa-check-circle',
            danger: 'fa-exclamation-triangle',
            warning: 'fa-exclamation-circle',
            info: 'fa-info-circle'
        };

        alertDiv.innerHTML = `
            <i class="fas ${icons[type] || icons.info} me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        container.insertBefore(alertDiv, container.firstChild);

        setTimeout(() => {
            alertDiv.style.transition = 'opacity 0.5s';
            alertDiv.style.opacity = '0';
            setTimeout(() => alertDiv.remove(), 500);
        }, duration);
    }
};

// Inject a top-of-page loading bar animation style on DOM ready
document.addEventListener('DOMContentLoaded', function () {
    const style = document.createElement('style');
    style.textContent = `
        .ajax-loading {
            position: fixed;
            top: 0; left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            z-index: 9999;
            animation: loading 1s ease-in-out infinite;
        }
        @keyframes loading {
            0%   { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
    `;
    document.head.appendChild(style);
});

// Dev tool: strip all required attributes from a single form (disables HTML5 validation)
function bypassValidation(formId) {
    const form = document.getElementById(formId);
    if (!form) { console.error('Form not found: ' + formId); return; }

    form.setAttribute('novalidate', 'novalidate');
    form.addEventListener('submit', function () {
        form.querySelectorAll('[required]').forEach(field => field.removeAttribute('required'));
    });

    console.log('Validation bypassed for form: ' + formId);
}

// Dev tool: strip required attributes from every form on the page
function bypassAllValidation() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.setAttribute('novalidate', 'novalidate');
        form.addEventListener('submit', function () {
            form.querySelectorAll('[required]').forEach(field => field.removeAttribute('required'));
        });
    });
    console.log('Validation bypassed for ' + forms.length + ' form(s)');
}

// Keyboard shortcut Ctrl+Shift+B triggers bypassAllValidation (dev use only)
document.addEventListener('keydown', function (e) {
    if (e.ctrlKey && e.shiftKey && e.key === 'B') {
        e.preventDefault();
        bypassAllValidation();
        alert('Form validation bypassed! You can now submit forms without validation.');
    }
});