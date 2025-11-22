/**
 * Environment Manager
 * 
 * Handles all CRUD operations for PhlagEnvironment objects via AJAX calls
 * to the REST API. Manages the UI for listing, creating, editing,
 * and viewing deployment environments.
 * 
 * ## Responsibilities
 * 
 * Environments represent different deployment contexts where feature
 * flags are managed, such as development, staging, and production.
 */

const EnvironmentManager = {
    
    /**
     * API client instance
     */
    api: null,
    
    /**
     * Base URL for the application
     */
    base_url: '',
    
    /**
     * Initializes the Environment Manager
     * 
     * @param {string} api_url - Base URL for API endpoints (e.g., '/api')
     */
    init: function(api_url) {
        this.api = new ApiClient(api_url);
        
        // Get base URL from current window location
        this.base_url = window.location.origin;
    },
    
    /**
     * Loads and displays the list of environments
     * 
     * Makes a GET request to /api/PhlagEnvironment and populates the table
     * with the results. Shows empty state if no environments exist.
     */
    loadList: function() {
        UI.showLoading();
        
        this.api.get('/PhlagEnvironment/')
            .then(data => {
                UI.hideLoading();
                
                const loading = document.getElementById('loading');
                const container = document.getElementById('environment-table-container');
                const tbody = document.getElementById('environment-tbody');
                const empty_state = document.getElementById('empty-state');
                
                loading.classList.add('hidden');
                container.classList.remove('hidden');
                
                // Clear existing rows
                tbody.innerHTML = '';
                
                if (!data || data.length === 0) {
                    document.getElementById('environment-table').classList.add('hidden');
                    empty_state.classList.remove('hidden');
                    return;
                }
                
                // Populate table with environments
                data.forEach(environment => {
                    const row = this._createTableRow(environment);
                    tbody.appendChild(row);
                });
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to load environments: ' + error.message, 'error');
                document.getElementById('loading').innerHTML = 
                    '<p class="error">Error loading environments. Please try again.</p>';
            });
    },
    
    /**
     * Loads a single environment for viewing
     * 
     * @param {number} id - Environment ID to load
     */
    loadSingle: function(id) {
        UI.showLoading();
        
        this.api.get('/PhlagEnvironment/' + id + '/')
            .then(environment => {
                UI.hideLoading();
                
                document.getElementById('loading').classList.add('hidden');
                document.getElementById('environment-details').classList.remove('hidden');
                
                this._populateDetails(environment);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to load environment: ' + error.message, 'error');
                
                if (error.status === 404) {
                    document.getElementById('loading').innerHTML = 
                        '<p class="error">Environment not found.</p>' +
                        '<p><a href="' + this.base_url + '/environments" class="btn btn-primary">Back to List</a></p>';
                } else {
                    document.getElementById('loading').innerHTML = 
                        '<p class="error">Error loading environment. Please try again.</p>';
                }
            });
    },
    
    /**
     * Loads an environment for editing
     * 
     * @param {number} id - Environment ID to load
     */
    loadForEdit: function(id) {
        UI.showLoading();
        
        this.api.get('/PhlagEnvironment/' + id + '/')
            .then(environment => {
                UI.hideLoading();
                this._populateForm(environment);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to load environment: ' + error.message, 'error');
                
                if (error.status === 404) {
                    setTimeout(() => {
                        window.location.href = this.base_url + '/environments';
                    }, 2000);
                }
            });
    },
    
    /**
     * Initializes the environment form with event handlers
     */
    initForm: function() {
        const form = document.getElementById('environment-form');
        
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
                const environment_id = form.dataset.environmentId;
                this.update(environment_id, form_data);
            }
        });
    },
    
    /**
     * Creates a new environment
     * 
     * @param {object} form_data - Form data to submit
     */
    create: function(form_data) {
        UI.showLoading();
        
        this.api.post('/PhlagEnvironment/', form_data)
            .then(environment => {
                UI.hideLoading();
                UI.showMessage('Environment created successfully!', 'success');
                
                // Redirect to list
                setTimeout(() => {
                    window.location.href = this.base_url + '/environments';
                }, 1000);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to create environment: ' + error.message, 'error');
            });
    },
    
    /**
     * Updates an existing environment
     * 
     * @param {number} id - Environment ID to update
     * @param {object} form_data - Form data to submit
     */
    update: function(id, form_data) {
        UI.showLoading();
        
        // Add the ID to the form data
        form_data.phlag_environment_id = parseInt(id);
        
        this.api.put('/PhlagEnvironment/' + id + '/', form_data)
            .then(environment => {
                UI.hideLoading();
                UI.showMessage('Environment updated successfully!', 'success');
                
                // Redirect to view page
                setTimeout(() => {
                    window.location.href = this.base_url + '/environments/' + id;
                }, 1000);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to update environment: ' + error.message, 'error');
            });
    },
    
    /**
     * Deletes an environment
     * 
     * Shows a confirmation dialog warning the user that this action
     * cannot be undone.
     * 
     * @param {number} id - Environment ID to delete
     */
    delete: function(id) {
        if (!UI.confirm('Are you sure you want to delete this environment? This action cannot be undone.')) {
            return;
        }
        
        UI.showLoading();
        
        this.api.delete('/PhlagEnvironment/' + id + '/')
            .then(() => {
                UI.hideLoading();
                UI.showMessage('Environment deleted successfully!', 'success');
                
                // Redirect to list
                setTimeout(() => {
                    window.location.href = this.base_url + '/environments';
                }, 1000);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to delete environment: ' + error.message, 'error');
            });
    },
    
    /**
     * Creates a table row for an environment
     * 
     * @param {object} environment - Environment object
     * @returns {HTMLElement} Table row element
     * @private
     */
    _createTableRow: function(environment) {
        const row = document.createElement('tr');
        
        row.innerHTML = `
            <td><a href="${this.base_url}/environments/${environment.phlag_environment_id}">${this._escapeHtml(environment.name)}</a></td>
            <td class="hide-mobile">${this._formatDate(environment.create_datetime)}</td>
            <td class="hide-mobile">${this._formatDate(environment.update_datetime)}</td>
            <td class="actions">
                <a href="${this.base_url}/environments/${environment.phlag_environment_id}" class="btn btn-small btn-primary">View</a>
                <a href="${this.base_url}/environments/${environment.phlag_environment_id}/edit" class="btn btn-small btn-secondary">Edit</a>
                <button onclick="EnvironmentManager.delete(${environment.phlag_environment_id})" class="btn btn-small btn-danger">Delete</button>
            </td>
        `;
        
        return row;
    },
    
    /**
     * Populates the detail view with environment data
     * 
     * @param {object} environment - Environment object
     * @private
     */
    _populateDetails: function(environment) {
        document.getElementById('detail-id').textContent = environment.phlag_environment_id;
        document.getElementById('detail-name').textContent = environment.name;
        document.getElementById('detail-created').textContent = this._formatDate(environment.create_datetime);
        document.getElementById('detail-updated').textContent = this._formatDate(environment.update_datetime);
    },
    
    /**
     * Populates the form with environment data for editing
     * 
     * @param {object} environment - Environment object
     * @private
     */
    _populateForm: function(environment) {
        document.getElementById('name').value = environment.name || '';
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
