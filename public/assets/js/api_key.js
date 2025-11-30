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
     * Cached environments array
     * 
     * Loaded once on page load and used for rendering environment
     * checkbox sections. Sorted by sort_order, then name.
     * 
     * @type {Array}
     */
    environments: [],
    
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
     * Loads both the API key data and its environment assignments,
     * then populates the form with all information.
     * 
     * @param {number} id - API Key ID to load
     */
    loadForEdit: function(id) {
        UI.showLoading();
        
        // Load environments first, then API key and assignments
        this.loadEnvironments()
            .then(() => {
                return Promise.all([
                    this.api.get('/PhlagApiKey/' + id + '/'),
                    this.loadEnvironmentAssignments(id)
                ]);
            })
            .then(([api_key, assignments]) => {
                UI.hideLoading();
                this._populateForm(api_key, assignments);
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
     * 
     * Loads environments and renders the environment checkbox section.
     * Sets up form submission handlers for creating/updating API keys
     * with environment assignments.
     */
    initForm: function() {
        const form = document.getElementById('api-key-form');
        
        // Load environments and render checkboxes
        this.loadEnvironments()
            .then(() => {
                this._renderEnvironmentCheckboxes();
            })
            .catch(error => {
                UI.showMessage('Failed to load environments: ' + error.message, 'error');
                document.getElementById('environment-loading').textContent = 'Error loading environments';
            });
        
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
            
            // Extract environment assignments
            const env_ids = this._extractEnvironmentAssignments();
            
            if (mode === 'create') {
                this.create(form_data, env_ids);
            } else if (mode === 'edit') {
                const api_key_id = form.dataset.apiKeyId;
                this.update(api_key_id, form_data, env_ids);
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
     * Loads all environments and caches them
     * 
     * Makes a GET request to /api/PhlagEnvironment and stores the results
     * in the environments property. Environments are sorted by sort_order
     * (ascending), then by name (ascending).
     * 
     * @return {Promise} Resolves with environments array
     */
    loadEnvironments: function() {
        return this.api.get('/PhlagEnvironment/')
            .then(environments => {
                // Sort by sort_order, then name
                environments.sort((a, b) => {
                    if (a.sort_order !== b.sort_order) {
                        return a.sort_order - b.sort_order;
                    }
                    return a.name.localeCompare(b.name);
                });
                
                this.environments = environments;
                return environments;
            });
    },
    
    /**
     * Loads environment assignments for an API key
     * 
     * Makes a POST request to /api/PhlagApiKeyEnvironment/_search/ to find
     * all environment assignments for the specified API key.
     * 
     * @param {number} api_key_id - API key ID to load assignments for
     * 
     * @return {Promise} Resolves with array of assignment objects
     */
    loadEnvironmentAssignments: function(api_key_id) {
        return this.api.post('/PhlagApiKeyEnvironment/_search/', {
            plag_api_key_id: parseInt(api_key_id)
        });
    },
    
    /**
     * Creates a new API key
     * 
     * After successful creation, saves environment assignments (if any),
     * then displays the full API key in a modal for the user to copy.
     * The key will never be shown again.
     * 
     * @param {object} form_data - Form data to submit
     * @param {Array}  env_ids   - Array of environment IDs to assign
     */
    create: function(form_data, env_ids) {
        UI.showLoading();
        
        // Step 1: Create the API key
        this.api.post('/PhlagApiKey/', form_data)
            .then(api_key => {
                // Step 2: Save environment assignments (if any selected)
                return this._saveEnvironmentAssignments(api_key.plag_api_key_id, env_ids)
                    .then(() => api_key);
            })
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
     * Updates an existing API key's description and environment assignments
     * 
     * Note: Only the description and environment assignments can be updated.
     * The API key value itself cannot be changed for security reasons.
     * 
     * @param {number} id      - API Key ID to update
     * @param {object} form_data - Form data to submit
     * @param {Array}  env_ids - Array of environment IDs to assign
     */
    update: function(id, form_data, env_ids) {
        UI.showLoading();
        
        // Add the ID to the form data
        form_data.plag_api_key_id = parseInt(id);
        
        // Step 1: Update the API key
        this.api.put('/PhlagApiKey/' + id + '/', form_data)
            .then(() => {
                // Step 2: Update environment assignments
                return this._updateEnvironmentAssignments(id, env_ids);
            })
            .then(() => {
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
     * Populates the description field and checks the appropriate
     * environment checkboxes based on current assignments.
     * 
     * @param {object} api_key     - API key object
     * @param {Array}  assignments - Array of environment assignment objects
     * 
     * @private
     */
    _populateForm: function(api_key, assignments = []) {
        document.getElementById('description').value = api_key.description || '';
        
        // Render environment checkboxes
        this._renderEnvironmentCheckboxes();
        
        // Check appropriate environment boxes
        if (assignments && assignments.length > 0) {
            assignments.forEach(assignment => {
                const checkbox = document.getElementById(`env_${assignment.phlag_environment_id}`);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
        }
    },
    
    /**
     * Renders environment checkboxes in the form
     * 
     * Creates a checkbox for each environment. If no environments exist,
     * displays a helpful message with a link to create one.
     * 
     * Heads-up: Checkboxes do NOT have a name attribute because we handle
     * environment assignments separately via _extractEnvironmentAssignments().
     * Including a name would cause the DataMapper API to try setting a
     * non-existent property on the PhlagApiKey object.
     * 
     * @private
     */
    _renderEnvironmentCheckboxes: function() {
        const container = document.getElementById('environment-checkboxes');
        const loading = document.getElementById('environment-loading');
        
        if (!container) {
            return;
        }
        
        // Hide loading message
        if (loading) {
            loading.classList.add('hidden');
        }
        
        container.innerHTML = '';
        
        if (this.environments.length === 0) {
            container.innerHTML = '<p class="help-text"><em>No environments configured. <a href="' + this.base_url + '/environments/create">Create an environment</a> to get started.</em></p>';
            return;
        }
        
        this.environments.forEach(env => {
            const checkbox_item = document.createElement('div');
            checkbox_item.className = 'checkbox-item';
            
            checkbox_item.innerHTML = `
                <label>
                    <input type="checkbox" 
                           id="env_${env.phlag_environment_id}"
                           value="${env.phlag_environment_id}"
                           class="env-checkbox"
                           data-env-id="${env.phlag_environment_id}">
                    ${this._escapeHtml(env.name)}
                </label>
            `;
            
            container.appendChild(checkbox_item);
        });
    },
    
    /**
     * Extracts selected environment IDs from checkboxes
     * 
     * Collects all checked environment checkboxes and returns their
     * values as an array of integers.
     * 
     * @return {Array} Array of environment IDs (integers)
     * 
     * @private
     */
    _extractEnvironmentAssignments: function() {
        const checkboxes = document.querySelectorAll('.env-checkbox:checked');
        const env_ids = [];
        
        checkboxes.forEach(cb => {
            env_ids.push(parseInt(cb.value));
        });
        
        return env_ids;
    },
    
    /**
     * Saves environment assignments for an API key
     * 
     * Creates environment assignment records via the API. Used when
     * creating a new API key.
     * 
     * @param {number} api_key_id - API key ID
     * @param {Array}  env_ids    - Array of environment IDs to assign
     * 
     * @return {Promise} Resolves when all assignments are saved
     * 
     * @private
     */
    _saveEnvironmentAssignments: function(api_key_id, env_ids) {
        // If no environments selected, don't create any assignments (unrestricted access)
        if (env_ids.length === 0) {
            return Promise.resolve();
        }
        
        const promises = env_ids.map(env_id => {
            return this.api.post('/PhlagApiKeyEnvironment/', {
                plag_api_key_id: api_key_id,
                phlag_environment_id: env_id
            });
        });
        
        return Promise.all(promises);
    },
    
    /**
     * Updates environment assignments for an API key
     * 
     * Deletes all existing assignments and creates new ones.
     * This is simpler than trying to diff and update individually.
     * 
     * @param {number} api_key_id - API key ID
     * @param {Array}  env_ids    - Array of environment IDs to assign
     * 
     * @return {Promise} Resolves when all assignments are updated
     * 
     * @private
     */
    _updateEnvironmentAssignments: function(api_key_id, env_ids) {
        // Step 1: Load existing assignments
        return this.loadEnvironmentAssignments(api_key_id)
            .then(existing => {
                // Step 2: Delete all existing assignments
                const delete_promises = existing.map(assignment => {
                    return this.api.delete('/PhlagApiKeyEnvironment/' + assignment.phlag_api_key_environment_id + '/');
                });
                
                return Promise.all(delete_promises);
            })
            .then(() => {
                // Step 3: Create new assignments
                return this._saveEnvironmentAssignments(api_key_id, env_ids);
            });
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
