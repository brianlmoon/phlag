/**
 * Phlag Admin Application
 * 
 * Core JavaScript utilities and API client for making requests to the
 * Phlag REST API. This file provides shared functionality used across
 * all pages in the admin interface.
 */

/**
 * API Client for Phlag REST API
 * 
 * Handles all HTTP requests to the API endpoints using XMLHttpRequest.
 * Provides methods for GET, POST, PUT, and DELETE operations with
 * consistent error handling and response parsing.
 * 
 * ## Usage
 * 
 * ```javascript
 * const api = new ApiClient('/api');
 * 
 * api.get('/Phlag/1')
 *    .then(data => console.log(data))
 *    .catch(error => console.error(error));
 * ```
 */
class ApiClient {
    
    /**
     * Creates a new API client
     * 
     * @param {string} baseUrl - Base URL for API endpoints (e.g., '/api')
     */
    constructor(baseUrl) {
        this.baseUrl = baseUrl;
    }
    
    /**
     * Performs a GET request
     * 
     * @param {string} endpoint - API endpoint path (e.g., '/Phlag/1')
     * @returns {Promise} Promise resolving to parsed JSON response
     */
    get(endpoint) {
        return this._request('GET', endpoint);
    }
    
    /**
     * Performs a POST request
     * 
     * @param {string} endpoint - API endpoint path
     * @param {object} data - Data to send in request body
     * @returns {Promise} Promise resolving to parsed JSON response
     */
    post(endpoint, data) {
        return this._request('POST', endpoint, data);
    }
    
    /**
     * Performs a PUT request
     * 
     * @param {string} endpoint - API endpoint path
     * @param {object} data - Data to send in request body
     * @returns {Promise} Promise resolving to parsed JSON response
     */
    put(endpoint, data) {
        return this._request('PUT', endpoint, data);
    }
    
    /**
     * Performs a DELETE request
     * 
     * @param {string} endpoint - API endpoint path
     * @returns {Promise} Promise resolving to parsed JSON response
     */
    delete(endpoint) {
        return this._request('DELETE', endpoint);
    }
    
    /**
     * Internal request handler using XMLHttpRequest
     * 
     * @param {string} method - HTTP method
     * @param {string} endpoint - API endpoint path
     * @param {object} data - Optional request body data
     * @returns {Promise} Promise resolving to parsed JSON response
     * @private
     */
    _request(method, endpoint, data = null) {
        return new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const url = this.baseUrl + endpoint;
            
            xhr.open(method, url, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.setRequestHeader('Accept', 'application/json');
            
            xhr.onload = () => {
                if (xhr.status >= 200 && xhr.status < 300) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        resolve(response);
                    } catch (e) {
                        resolve(xhr.responseText);
                    }
                } else {
                    let error_message = 'Request failed';
                    try {
                        const error_data = JSON.parse(xhr.responseText);
                        error_message = error_data.message || error_data.error || error_message;
                    } catch (e) {
                        error_message = xhr.responseText || error_message;
                    }
                    reject({
                        status: xhr.status,
                        message: error_message,
                        response: xhr.responseText
                    });
                }
            };
            
            xhr.onerror = () => {
                reject({
                    status: 0,
                    message: 'Network error occurred',
                    response: null
                });
            };
            
            if (data) {
                xhr.send(JSON.stringify(data));
            } else {
                xhr.send();
            }
        });
    }
}

/**
 * UI Utilities
 * 
 * Shared functions for displaying messages, handling forms,
 * and other common UI operations.
 */
const UI = {
    
    /**
     * Displays a message to the user
     * 
     * Creates a toast-style notification that appears at the top
     * of the page and auto-dismisses after a few seconds.
     * 
     * @param {string} message - Message text to display
     * @param {string} type - Message type: 'success', 'error', 'info', 'warning'
     */
    showMessage: function(message, type = 'info') {
        const container = this._getMessageContainer();
        const message_el = document.createElement('div');
        message_el.className = 'message message-' + type;
        message_el.textContent = message;
        
        container.appendChild(message_el);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            message_el.classList.add('fade-out');
            setTimeout(() => {
                container.removeChild(message_el);
            }, 300);
        }, 5000);
    },
    
    /**
     * Gets or creates the message container element
     * 
     * @returns {HTMLElement} Message container element
     * @private
     */
    _getMessageContainer: function() {
        let container = document.getElementById('message-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'message-container';
            container.className = 'message-container';
            document.body.insertBefore(container, document.body.firstChild);
        }
        return container;
    },
    
    /**
     * Shows a confirmation dialog
     * 
     * Displays a native browser confirmation dialog and returns
     * the user's choice.
     * 
     * @param {string} message - Confirmation message
     * @returns {boolean} True if user confirmed, false otherwise
     */
    confirm: function(message) {
        return window.confirm(message);
    },
    
    /**
     * Shows a loading indicator
     * 
     * Displays a loading spinner or indicator to show that
     * an operation is in progress.
     */
    showLoading: function() {
        const loader = this._getLoader();
        loader.style.display = 'flex';
    },
    
    /**
     * Hides the loading indicator
     */
    hideLoading: function() {
        const loader = this._getLoader();
        loader.style.display = 'none';
    },
    
    /**
     * Gets or creates the loading indicator element
     * 
     * @returns {HTMLElement} Loading indicator element
     * @private
     */
    _getLoader: function() {
        let loader = document.getElementById('loading-indicator');
        if (!loader) {
            loader = document.createElement('div');
            loader.id = 'loading-indicator';
            loader.className = 'loading-indicator';
            loader.innerHTML = '<div class="spinner"></div>';
            document.body.appendChild(loader);
        }
        return loader;
    }
};

/**
 * Form Utilities
 * 
 * Helper functions for working with forms and form data.
 */
const FormUtils = {
    
    /**
     * Serializes a form to a JavaScript object
     * 
     * @param {HTMLFormElement} form - Form element to serialize
     * @returns {object} Object with form field names as keys
     */
    serialize: function(form) {
        const form_data = new FormData(form);
        const data = {};
        
        for (let [key, value] of form_data.entries()) {
            data[key] = value;
        }
        
        return data;
    },
    
    /**
     * Validates required fields in a form
     * 
     * @param {HTMLFormElement} form - Form to validate
     * @returns {boolean} True if all required fields are filled
     */
    validateRequired: function(form) {
        const required_fields = form.querySelectorAll('[required]');
        let is_valid = true;
        
        required_fields.forEach(field => {
            if (!field.value.trim()) {
                is_valid = false;
                field.classList.add('error');
            } else {
                field.classList.remove('error');
            }
        });
        
        return is_valid;
    },
    
    /**
     * Clears validation errors from a form
     * 
     * @param {HTMLFormElement} form - Form to clear errors from
     */
    clearErrors: function(form) {
        const error_fields = form.querySelectorAll('.error');
        error_fields.forEach(field => {
            field.classList.remove('error');
        });
    }
};
