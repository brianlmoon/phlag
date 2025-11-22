/**
 * API Key Manager
 * 
 * Handles all CRUD operations for PhlagApiKey objects via AJAX calls
 * to the REST API. Manages the UI for listing, creating, editing,
 * and viewing API keys.
 * 
 * ## Security Considerations
 * 
 * API keys are sensitive credentials. After creation, keys are masked
 * in all views showing only the first and last few characters. The full
 * key is only shown once immediately after creation in a modal dialog.
 */

const ApiKeyManager = {
    
    /**
     * API client instance
     */
    api: null,
    
    /**
     * Base URL for the application
     */
    base_url: '',
    
    /**
     * Initializes the API Key Manager
     * 
     * @param {string} api_url - Base URL for API endpoints (e.g., '/api')
     */
    init: function(api_url) {
        this.api = new ApiClient(api_url);
        
        // Get base URL from current window location
        // Extract base path by removing known application routes
        const pathname = window.location.pathname;
        const routes = ['/flags', '/api-keys', '/users', '/environments', '/login', '/logout'];
        let base_path = '';
        
        for (const route of routes) {
            const route_index = pathname.indexOf(route);
            if (route_index !== -1) {
                // Check if it's actually a route boundary (followed by / or end of string)
                const after_route = pathname.charAt(route_index + route.length);
                if (after_route === '/' || after_route === '' || after_route === '?') {
                    base_path = pathname.substring(0, route_index);
                    break;
                }
            }
        }
        
        this.base_url = window.location.origin + base_path;
    },
    
    /**
     * Loads and displays the list of API keys
     * 
     * Makes a GET request to /api/PhlagApiKey and populates the table
     * with the results. Shows empty state if no API keys exist.
     * API key values are masked for security.
     */
    loadList: function() {
        UI.showLoading();
        
        this.api.get('/PhlagApiKey/')
            .then(data => {
                UI.hideLoading();
                
                const loading = document.getElementById('loading');
                const container = document.getElementById('api-key-table-container');
                const tbody = document.getElementById('api-key-tbody');
                const empty_state = document.getElementById('empty-state');
                
                loading.classList.add('hidden');
                container.classList.remove('hidden');
                
                // Clear existing rows
                tbody.innerHTML = '';
                
                if (!data || data.length === 0) {
                    document.getElementById('api-key-table').classList.add('hidden');
                    empty_state.classList.remove('hidden');
                    return;
                }
                
                // Populate table with API keys
                data.forEach(api_key => {
                    const row = this._createTableRow(api_key);
                    tbody.appendChild(row);
                });
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to load API keys: ' + error.message, 'error');
                document.getElementById('loading').innerHTML = 
                    '<p class="error">Error loading API keys. Please try again.</p>';
            });
    },
    
    /**
     * Loads a single API key for viewing
     * 
     * @param {number} id - API Key ID to load
     */
    loadSingle: function(id) {
        UI.showLoading();
        
        this.api.get('/PhlagApiKey/' + id + '/')
            .then(api_key => {
                UI.hideLoading();
                
                document.getElementById('loading').classList.add('hidden');
                document.getElementById('api-key-details').classList.remove('hidden');
                
                this._populateDetails(api_key);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to load API key: ' + error.message, 'error');
                
                if (error.status === 404) {
                    document.getElementById('loading').innerHTML = 
                        '<p class="error">API key not found.</p>' +
                        '<p><a href="' + this.base_url + '/api-keys" class="btn btn-primary">Back to List</a></p>';
                } else {
                    document.getElementById('loading').innerHTML = 
                        '<p class="error">Error loading API key. Please try again.</p>';
                }
            });
    },
    
    /**
     * Loads an API key for editing
     * 
     * @param {number} id - API Key ID to load
     */
    loadForEdit: function(id) {
        UI.showLoading();
        
        this.api.get('/PhlagApiKey/' + id + '/')
            .then(api_key => {
                UI.hideLoading();
                this._populateForm(api_key);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to load API key: ' + error.message, 'error');
                
                if (error.status === 404) {
                    setTimeout(() => {
                        window.location.href = this.base_url + '/api-keys';
                    }, 2000);
                }
            });
    },
    
    /**
     * Initializes the API key form with event handlers
     */
    initForm: function() {
        const form = document.getElementById('api-key-form');
        
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            
            // Clear any previous errors
            FormUtils.clearErrors(form);
            
            // Validate required fields
            if (!FormUtils.validateRequired(form)) {
                UI.showMessage('Please fill in all required fields', 'error');
                return;
            }
            
            const mode = form.dataset.mode;
            const form_data = FormUtils.serialize(form);
            
            if (mode === 'create') {
                this.create(form_data);
            } else if (mode === 'edit') {
                const api_key_id = form.dataset.apiKeyId;
                this.update(api_key_id, form_data);
            }
        });
        
        // Set up modal close handlers if modal exists
        const close_btn = document.getElementById('close-modal-btn');
        if (close_btn) {
            close_btn.addEventListener('click', () => {
                window.location.href = this.base_url + '/api-keys';
            });
        }
        
        // Set up copy button if it exists
        const copy_btn = document.getElementById('copy-api-key-btn');
        if (copy_btn) {
            copy_btn.addEventListener('click', () => {
                const input = document.getElementById('new-api-key-value');
                input.select();
                document.execCommand('copy');
                UI.showMessage('API key copied to clipboard!', 'success');
            });
        }
    },
    
    /**
     * Creates a new API key
     * 
     * After successful creation, displays the full API key in a modal
     * for the user to copy. The key will never be shown again.
     * 
     * @param {object} form_data - Form data to submit
     */
    create: function(form_data) {
        UI.showLoading();
        
        this.api.post('/PhlagApiKey/', form_data)
            .then(api_key => {
                UI.hideLoading();
                
                // Show the API key in a modal
                this._showApiKeyModal(api_key.api_key);
                
                UI.showMessage('API key created successfully!', 'success');
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to create API key: ' + error.message, 'error');
            });
    },
    
    /**
     * Updates an existing API key's description
     * 
     * Note: Only the description can be updated. The API key value
     * itself cannot be changed for security reasons.
     * 
     * @param {number} id - API Key ID to update
     * @param {object} form_data - Form data to submit
     */
    update: function(id, form_data) {
        UI.showLoading();
        
        // Add the ID to the form data
        form_data.plag_api_key_id = parseInt(id);
        
        this.api.put('/PhlagApiKey/' + id + '/', form_data)
            .then(api_key => {
                UI.hideLoading();
                UI.showMessage('API key updated successfully!', 'success');
                
                // Redirect to view page
                setTimeout(() => {
                    window.location.href = this.base_url + '/api-keys/' + id;
                }, 1000);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to update API key: ' + error.message, 'error');
            });
    },
    
    /**
     * Deletes an API key
     * 
     * Shows a confirmation dialog warning the user that any applications
     * using this key will stop working.
     * 
     * @param {number} id - API Key ID to delete
     */
    delete: function(id) {
        if (!UI.confirm('Are you sure you want to delete this API key? Any applications using this key will stop working. This action cannot be undone.')) {
            return;
        }
        
        UI.showLoading();
        
        this.api.delete('/PhlagApiKey/' + id + '/')
            .then(() => {
                UI.hideLoading();
                UI.showMessage('API key deleted successfully!', 'success');
                
                // Redirect to list
                setTimeout(() => {
                    window.location.href = this.base_url + '/api-keys';
                }, 1000);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to delete API key: ' + error.message, 'error');
            });
    },
    
    /**
     * Shows a modal with the newly created API key
     * 
     * This is the only time the full key will be displayed. The modal
     * includes a copy button and warning message.
     * 
     * @param {string} api_key - The full API key value
     * @private
     */
    _showApiKeyModal: function(api_key) {
        const modal = document.getElementById('api-key-success-modal');
        const input = document.getElementById('new-api-key-value');
        
        input.value = api_key;
        modal.classList.remove('hidden');
        
        // Auto-select the key for easy copying
        input.select();
    },
    
    /**
     * Creates a table row for an API key
     * 
     * The API key value is masked showing only the first and last
     * few characters for security.
     * 
     * @param {object} api_key - API key object
     * @returns {HTMLElement} Table row element
     * @private
     */
    _createTableRow: function(api_key) {
        const row = document.createElement('tr');
        
        const masked_key = this._maskApiKey(api_key.api_key);
        
        row.innerHTML = `
            <td><a href="${this.base_url}/api-keys/${api_key.plag_api_key_id}">${this._escapeHtml(api_key.description)}</a></td>
            <td class="hide-mobile"><code class="masked-key">${masked_key}</code></td>
            <td class="hide-mobile">${this._formatDate(api_key.create_datetime)}</td>
            <td class="actions">
                <a href="${this.base_url}/api-keys/${api_key.plag_api_key_id}" class="btn btn-small btn-primary">View</a>
                <a href="${this.base_url}/api-keys/${api_key.plag_api_key_id}/edit" class="btn btn-small btn-secondary">Edit</a>
                <button onclick="ApiKeyManager.delete(${api_key.plag_api_key_id})" class="btn btn-small btn-danger">Delete</button>
            </td>
        `;
        
        return row;
    },
    
    /**
     * Populates the detail view with API key data
     * 
     * The API key value is masked for security.
     * 
     * @param {object} api_key - API key object
     * @private
     */
    _populateDetails: function(api_key) {
        document.getElementById('detail-id').textContent = api_key.plag_api_key_id;
        document.getElementById('detail-description').textContent = api_key.description;
        document.getElementById('detail-api-key').textContent = this._maskApiKey(api_key.api_key);
        document.getElementById('detail-created').textContent = this._formatDate(api_key.create_datetime);
    },
    
    /**
     * Populates the form with API key data for editing
     * 
     * Only the description field is editable. The API key value
     * cannot be changed.
     * 
     * @param {object} api_key - API key object
     * @private
     */
    _populateForm: function(api_key) {
        document.getElementById('description').value = api_key.description || '';
    },
    
    /**
     * Masks an API key for display
     * 
     * Shows only the first 4 and last 4 characters with asterisks
     * in between for security.
     * 
     * @param {string} api_key - Full API key value
     * @returns {string} Masked API key
     * @private
     */
    _maskApiKey: function(api_key) {
        if (!api_key || api_key.length < 12) {
            return '***masked***';
        }
        
        const first = api_key.substring(0, 4);
        const last = api_key.substring(api_key.length - 4);
        const middle_length = api_key.length - 8;
        const middle = '*'.repeat(Math.min(middle_length, 12));
        
        return first + middle + last;
    },
    
    /**
     * Formats a datetime string for display
     * 
     * @param {string} datetime - Datetime string
     * @returns {string} Formatted datetime or 'N/A'
     * @private
     */
    _formatDate: function(datetime) {
        if (!datetime) {
            return 'N/A';
        }
        
        const date = new Date(datetime);
        return date.toLocaleString();
    },
    
    /**
     * Escapes HTML to prevent XSS
     * 
     * @param {string} text - Text to escape
     * @returns {string} Escaped text
     * @private
     */
    _escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};
