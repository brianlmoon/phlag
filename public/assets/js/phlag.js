/**
 * Phlag Manager
 * 
 * Handles all CRUD operations for Phlag objects via AJAX calls
 * to the REST API. Manages the UI for listing, creating, editing,
 * and viewing phlags.
 * 
 * ## Breaking Change (v2.0)
 * 
 * Phlags now support environment-specific values. Each flag can have
 * different values across multiple environments (production, staging, etc.).
 * The manager handles loading environments and creating/editing environment
 * values for each flag.
 */

const PhlagManager = {
    
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
     * value input sections. Sorted by sort_order, then name.
     * 
     * @type {Array}
     */
    environments: [],
    
    /**
     * Initializes the Phlag Manager
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
     * Loads all environments and caches them
     * 
     * Makes a GET request to /api/PhlagEnvironment and stores the results
     * in the environments property. Environments are sorted by sort_order
     * (ascending), then by name (ascending).
     * 
     * This method should be called before rendering forms that need to
     * display environment value inputs.
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
     * Loads all environment values for a specific flag
     * 
     * Makes a GET request to /api/PhlagEnvironmentValue searching by
     * phlag_id. Returns all environment-specific values for the flag.
     * 
     * @param {number} phlag_id - Phlag ID to load values for
     * 
     * @return {Promise} Resolves with array of environment values
     */
    loadEnvironmentValues: function(phlag_id) {
        return this.api.post('/PhlagEnvironmentValue/_search/', {
            phlag_id: parseInt(phlag_id)
        });
    },
    
    /**
     * Loads and displays the list of phlags
     * 
     * Makes a GET request to /api/Phlag and populates the table
     * with the results. Shows empty state if no phlags exist.
     * Sorts flags by name in ascending order.
     * 
     * ## Breaking Change (v2.0)
     * 
     * The list now shows a count of configured environments instead
     * of the flag value. Click "Details" to see environment-specific values.
     */
    loadList: function() {
        UI.showLoading();
        
        this.api.get('/Phlag/')
            .then(data => {
                UI.hideLoading();
                
                const loading = document.getElementById('loading');
                const container = document.getElementById('phlag-table-container');
                const tbody = document.getElementById('phlag-tbody');
                const empty_state = document.getElementById('empty-state');
                
                loading.classList.add('hidden');
                container.classList.remove('hidden');
                
                // Clear existing rows
                tbody.innerHTML = '';
                
                if (!data || data.length === 0) {
                    document.getElementById('phlag-table').classList.add('hidden');
                    empty_state.classList.remove('hidden');
                    return;
                }
                
                // Sort flags by name ascending
                data.sort((a, b) => {
                    const name_a = (a.name || '').toLowerCase();
                    const name_b = (b.name || '').toLowerCase();
                    return name_a.localeCompare(name_b);
                });
                
                // Populate table with phlags
                data.forEach(phlag => {
                    const row = this._createTableRow(phlag);
                    tbody.appendChild(row);
                });
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to load flags: ' + error.message, 'error');
                document.getElementById('loading').innerHTML = 
                    '<p class="error">Error loading flags. Please try again.</p>';
            });
    },
    
    /**
     * Loads a single phlag for viewing
     * 
     * Loads the flag, its environment values, and all environments to
     * display complete details including environment-specific values.
     * 
     * @param {number} id - Phlag ID to load
     */
    loadSingle: function(id) {
        UI.showLoading();
        
        // Load flag, environment values, and environments
        Promise.all([
            this.api.get('/Phlag/' + id + '/'),
            this.loadEnvironmentValues(id),
            this.loadEnvironments()
        ])
            .then(([phlag, env_values, environments]) => {
                UI.hideLoading();
                
                document.getElementById('loading').classList.add('hidden');
                document.getElementById('phlag-details').classList.remove('hidden');
                
                this._populateDetails(phlag, env_values);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to load flag: ' + error.message, 'error');
                
                if (error.status === 404) {
                    document.getElementById('loading').innerHTML = 
                        '<p class="error">Flag not found.</p>' +
                        '<p><a href="' + this.base_url + '/flags" class="btn btn-primary">Back to List</a></p>';
                } else {
                    document.getElementById('loading').innerHTML = 
                        '<p class="error">Error loading flag. Please try again.</p>';
                }
            });
    },
    
    /**
     * Loads a phlag for editing
     * 
     * Loads both the flag data and its environment values, then populates
     * the form with all information.
     * 
     * @param {number} id - Phlag ID to load
     */
    loadForEdit: function(id) {
        UI.showLoading();
        
        // Load environments first, then flag and environment values
        this.loadEnvironments()
            .then(() => {
                return Promise.all([
                    this.api.get('/Phlag/' + id + '/'),
                    this.loadEnvironmentValues(id)
                ]);
            })
            .then(([phlag, env_values]) => {
                UI.hideLoading();
                this._populateForm(phlag, env_values);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to load flag: ' + error.message, 'error');
                
                if (error.status === 404) {
                    setTimeout(() => {
                        window.location.href = this.base_url + '/flags';
                    }, 2000);
                }
            });
    },
    
    /**
     * Initializes the phlag form with event handlers
     * 
     * ## Breaking Change (v2.0)
     * 
     * The form now includes environment value sections. On page load:
     * - Loads all environments
     * - Renders environment value input sections
     * - Sets up type change handlers for each environment
     * - Handles form submission with environment values
     */
    initForm: function() {
        const form = document.getElementById('phlag-form');
        
        // Load environments and render inputs
        UI.showLoading();
        this.loadEnvironments()
            .then(() => {
                UI.hideLoading();
                this._renderEnvironmentInputs();
                this._initEnvironmentTypeHandlers();
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to load environments: ' + error.message, 'error');
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
            
            // Extract environment values from form
            const env_values = this._extractEnvironmentValues();
            
            if (mode === 'create') {
                this.create(form_data, env_values);
            } else if (mode === 'edit') {
                const phlag_id = form.dataset.phlagId;
                this.update(phlag_id, form_data, env_values);
            }
        });
    },
    
    /**
     * Initializes the type change handler for dynamic value input
     * 
     * When the type is changed to SWITCH, the value input becomes a select
     * with true/false options. For other types, it remains a text input.
     */
    initTypeChangeHandler: function() {
        const type_select = document.getElementById('type');
        const value_input = document.getElementById('value-input');
        const value_select = document.getElementById('value-select');
        
        if (!type_select || !value_input || !value_select) {
            return;
        }
        
        // Handle type changes
        type_select.addEventListener('change', () => {
            this._toggleValueInput(type_select.value, value_input, value_select);
        });
        
        // Initialize on page load based on current type
        if (type_select.value) {
            this._toggleValueInput(type_select.value, value_input, value_select);
        }
    },
    
    /**
     * Renders environment value input sections
     * 
     * Creates a section for each environment with inputs for value,
     * start_datetime, and end_datetime. The value input type changes
     * based on the flag type.
     * 
     * ## Structure
     * 
     * Each environment gets a section with:
     * - Environment name (h3)
     * - Value input (text or select based on type)
     * - Start datetime input (datetime-local)
     * - End datetime input (datetime-local)
     * 
     * @private
     */
    _renderEnvironmentInputs: function() {
        const container = document.getElementById('environment-values-container');
        if (!container) {
            return;
        }
        
        container.innerHTML = '';
        
        if (this.environments.length === 0) {
            container.innerHTML = '<p class="help-text">No environments configured. <a href="' + this.base_url + '/environments/create">Create an environment</a> to get started.</p>';
            return;
        }
        
        this.environments.forEach(env => {
            const section = this._createEnvironmentSection(env);
            container.appendChild(section);
        });
    },
    
    /**
     * Creates an environment value input section
     * 
     * Builds a complete form section for one environment with all
     * necessary input fields.
     * 
     * @param {object} env - Environment object
     * 
     * @return {HTMLElement} Section element
     * 
     * @private
     */
    _createEnvironmentSection: function(env) {
        const section = document.createElement('div');
        section.className = 'environment-value-section';
        section.dataset.environmentId = env.phlag_environment_id;
        
        const env_id = env.phlag_environment_id;
        
        section.innerHTML = `
            <h3>${this._escapeHtml(env.name)}</h3>
            <div class="form-row">
                <div class="form-group">
                    <label for="env_value_${env_id}">Value</label>
                    <input type="text" id="env_value_${env_id}" class="env-value-input" placeholder="Leave empty to not configure">
                    <select id="env_value_select_${env_id}" class="env-value-select hidden">
                        <option value="">-- Not Configured --</option>
                        <option value="true">true</option>
                        <option value="false">false</option>
                    </select>
                    <p class="help-text">Leave empty if not configured for this environment.</p>
                </div>
                <div class="form-group">
                    <label for="env_start_${env_id}">Start Date/Time</label>
                    <input type="datetime-local" id="env_start_${env_id}" class="env-start-input">
                    <p class="help-text">When this value becomes active (optional)</p>
                </div>
                <div class="form-group">
                    <label for="env_end_${env_id}">End Date/Time</label>
                    <input type="datetime-local" id="env_end_${env_id}" class="env-end-input">
                    <p class="help-text">When this value expires (optional)</p>
                </div>
            </div>
        `;
        
        return section;
    },
    
    /**
     * Initializes type change handlers for environment value inputs
     * 
     * When the flag type changes, all environment value inputs need to
     * update to match (text input vs select for SWITCH, number type, etc.).
     * 
     * @private
     */
    _initEnvironmentTypeHandlers: function() {
        const type_select = document.getElementById('type');
        if (!type_select) {
            return;
        }
        
        // Handle type changes
        type_select.addEventListener('change', () => {
            this._updateEnvironmentInputTypes(type_select.value);
        });
        
        // Initialize on page load based on current type
        if (type_select.value) {
            this._updateEnvironmentInputTypes(type_select.value);
        }
    },
    
    /**
     * Updates all environment value inputs based on flag type
     * 
     * Switches between text input and select for SWITCH type,
     * and updates input type attributes for INTEGER/FLOAT types.
     * 
     * @param {string} type - The selected phlag type
     * 
     * @private
     */
    _updateEnvironmentInputTypes: function(type) {
        const is_switch = (type === 'SWITCH');
        
        this.environments.forEach(env => {
            const env_id = env.phlag_environment_id;
            const value_input = document.getElementById(`env_value_${env_id}`);
            const value_select = document.getElementById(`env_value_select_${env_id}`);
            
            if (!value_input || !value_select) {
                return;
            }
            
            if (is_switch) {
                // Show select, hide input
                value_input.classList.add('hidden');
                value_select.classList.remove('hidden');
                
                // Transfer value if it exists
                if (value_input.value === 'true' || value_input.value === 'false') {
                    value_select.value = value_input.value;
                }
            } else {
                // Show input, hide select
                value_select.classList.add('hidden');
                value_input.classList.remove('hidden');
                
                // Transfer value if it exists
                if (value_select.value === 'true' || value_select.value === 'false') {
                    value_input.value = value_select.value;
                }
                
                // Update input type based on flag type
                if (type === 'INTEGER') {
                    value_input.setAttribute('type', 'number');
                    value_input.setAttribute('step', '1');
                    value_input.placeholder = 'e.g., 100';
                } else if (type === 'FLOAT') {
                    value_input.setAttribute('type', 'number');
                    value_input.setAttribute('step', 'any');
                    value_input.placeholder = 'e.g., 3.14';
                } else {
                    value_input.setAttribute('type', 'text');
                    value_input.removeAttribute('step');
                    value_input.placeholder = 'e.g., hello world';
                }
            }
        });
    },
    
    /**
     * Extracts environment values from the form
     * 
     * Collects all environment value inputs and builds an array of
     * environment value objects to be saved via the API. Only includes
     * environments that have a value set.
     * 
     * @return {Array} Array of environment value objects
     * 
     * @private
     */
    _extractEnvironmentValues: function() {
        const env_values = [];
        const type_select = document.getElementById('type');
        const is_switch = (type_select && type_select.value === 'SWITCH');
        
        this.environments.forEach(env => {
            const env_id = env.phlag_environment_id;
            const value_input = document.getElementById(`env_value_${env_id}`);
            const value_select = document.getElementById(`env_value_select_${env_id}`);
            const start_input = document.getElementById(`env_start_${env_id}`);
            const end_input = document.getElementById(`env_end_${env_id}`);
            
            if (!value_input) {
                return;
            }
            
            // Get value from appropriate input
            const value = is_switch ? value_select.value : value_input.value;
            
            // Only include if value is set or if temporal constraints exist
            if (value !== '' || start_input.value !== '' || end_input.value !== '') {
                env_values.push({
                    phlag_environment_id: env_id,
                    value: value === '' ? null : value,
                    start_datetime: start_input.value ? this._formatDateTime(start_input.value) : null,
                    end_datetime: end_input.value ? this._formatDateTime(end_input.value) : null
                });
            }
        });
        
        return env_values;
    },
    
    /**
     * Toggles between text input and select for the value field
     * 
     * When SWITCH type is selected, shows a select dropdown with true/false options.
     * When INTEGER or FLOAT type is selected, changes input to type="number" with
     * appropriate step value. For STRING type, uses regular text input.
     * 
     * @param {string} type - The selected phlag type
     * @param {HTMLElement} value_input - The text input element
     * @param {HTMLElement} value_select - The select element
     * @private
     */
    _toggleValueInput: function(type, value_input, value_select) {
        if (type === 'SWITCH') {
            // Show select, hide input
            value_input.classList.add('hidden');
            value_input.removeAttribute('name');
            
            value_select.classList.remove('hidden');
            value_select.setAttribute('name', 'value');
            
            // Transfer value if it exists
            const current_value = value_input.value;
            if (current_value === 'true' || current_value === 'false') {
                value_select.value = current_value;
            }
        } else {
            // Show input, hide select
            value_select.classList.add('hidden');
            value_select.removeAttribute('name');
            
            value_input.classList.remove('hidden');
            value_input.setAttribute('name', 'value');
            
            // Transfer value if it exists
            const current_value = value_select.value;
            if (current_value) {
                value_input.value = current_value;
            }
            
            // Update input type and attributes based on type
            this._updateValueInputType(type, value_input);
            
            // Update placeholder based on type
            this._updateValuePlaceholder(type, value_input);
        }
    },
    
    /**
     * Updates the input type and attributes based on the selected phlag type
     * 
     * For INTEGER: Sets type="number" with step="1" (integers only)
     * For FLOAT: Sets type="number" with step="any" (allows decimals)
     * For STRING: Sets type="text"
     * 
     * @param {string} type - The selected phlag type
     * @param {HTMLElement} value_input - The text input element
     * @private
     */
    _updateValueInputType: function(type, value_input) {
        if (type === 'INTEGER') {
            value_input.setAttribute('type', 'number');
            value_input.setAttribute('step', '1');
        } else if (type === 'FLOAT') {
            value_input.setAttribute('type', 'number');
            value_input.setAttribute('step', 'any');
        } else {
            value_input.setAttribute('type', 'text');
            value_input.removeAttribute('step');
        }
    },
    
    /**
     * Updates the placeholder text based on the selected type
     * 
     * @param {string} type - The selected phlag type
     * @param {HTMLElement} value_input - The text input element
     * @private
     */
    _updateValuePlaceholder: function(type, value_input) {
        const placeholders = {
            'INTEGER': 'e.g., 100',
            'FLOAT': 'e.g., 3.14',
            'STRING': 'e.g., hello world',
        };
        
        value_input.placeholder = placeholders[type] || '';
    },
    
    /**
     * Creates a new phlag
     * 
     * Creates the flag first, then saves all environment values.
     * If any environment value fails to save, shows an error but doesn't
     * roll back the flag creation.
     * 
     * @param {object} form_data - Form data to submit (name, description, type)
     * @param {Array} env_values - Array of environment value objects
     */
    create: function(form_data, env_values) {
        UI.showLoading();
        
        // Step 1: Create the flag
        this.api.post('/Phlag/', form_data)
            .then(phlag => {
                // Step 2: Save environment values
                return this._saveEnvironmentValues(phlag.phlag_id, env_values)
                    .then(() => phlag);
            })
            .then(phlag => {
                UI.hideLoading();
                UI.showMessage('Flag created successfully!', 'success');
                
                // Redirect to the new phlag's view page
                setTimeout(() => {
                    window.location.href = this.base_url + '/flags/' + phlag.phlag_id;
                }, 1000);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to create flag: ' + error.message, 'error');
            });
    },
    
    /**
     * Updates an existing phlag
     * 
     * Updates the flag metadata, then updates all environment values.
     * Deletes existing environment values and creates new ones to ensure
     * clean state.
     * 
     * @param {number} id - Phlag ID to update
     * @param {object} form_data - Form data to submit (description only, name/type immutable)
     * @param {Array} env_values - Array of environment value objects
     */
    update: function(id, form_data, env_values) {
        UI.showLoading();
        
        // Add the ID to the form data
        form_data.phlag_id = parseInt(id);
        
        // Step 1: Update the flag
        this.api.put('/Phlag/' + id + '/', form_data)
            .then(() => {
                // Step 2: Update environment values
                return this._updateEnvironmentValues(id, env_values);
            })
            .then(() => {
                UI.hideLoading();
                UI.showMessage('Flag updated successfully!', 'success');
                
                // Redirect to view page
                setTimeout(() => {
                    window.location.href = this.base_url + '/flags/' + id;
                }, 1000);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to update flag: ' + error.message, 'error');
            });
    },
    
    /**
     * Saves environment values for a flag
     * 
     * Creates environment value records via the API. Used when creating
     * a new flag.
     * 
     * @param {number} phlag_id - Phlag ID
     * @param {Array} env_values - Array of environment value objects
     * 
     * @return {Promise} Resolves when all values are saved
     * 
     * @private
     */
    _saveEnvironmentValues: function(phlag_id, env_values) {
        const promises = env_values.map(ev => {
            return this.api.post('/PhlagEnvironmentValue/', {
                phlag_id: phlag_id,
                phlag_environment_id: ev.phlag_environment_id,
                value: ev.value,
                start_datetime: ev.start_datetime,
                end_datetime: ev.end_datetime
            });
        });
        
        return Promise.all(promises);
    },
    
    /**
     * Updates environment values for a flag
     * 
     * Deletes all existing environment values and creates new ones.
     * This is simpler than trying to diff and update individually.
     * 
     * @param {number} phlag_id - Phlag ID
     * @param {Array} env_values - Array of environment value objects
     * 
     * @return {Promise} Resolves when all values are updated
     * 
     * @private
     */
    _updateEnvironmentValues: function(phlag_id, env_values) {
        // Step 1: Load existing environment values
        return this.loadEnvironmentValues(phlag_id)
            .then(existing => {
                // Step 2: Delete all existing values
                const delete_promises = existing.map(ev => {
                    return this.api.delete('/PhlagEnvironmentValue/' + ev.phlag_environment_value_id + '/');
                });
                
                return Promise.all(delete_promises);
            })
            .then(() => {
                // Step 3: Create new values
                return this._saveEnvironmentValues(phlag_id, env_values);
            });
    },
    
    /**
     * Deletes a phlag
     * 
     * @param {number} id - Phlag ID to delete
     */
    delete: function(id) {
        if (!UI.confirm('Are you sure you want to delete this flag? This action cannot be undone.')) {
            return;
        }
        
        UI.showLoading();
        
        this.api.delete('/Phlag/' + id + '/')
            .then(() => {
                UI.hideLoading();
                UI.showMessage('Flag deleted successfully!', 'success');
                
                // Redirect to list
                setTimeout(() => {
                    window.location.href = this.base_url + '/flags';
                }, 1000);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to delete flag: ' + error.message, 'error');
            });
    },
    
    /**
     * Creates a table row for a phlag
     * 
     * @param {object} phlag - Phlag object
     * @returns {HTMLElement} Table row element
     * @private
     */
    /**
     * Creates a table row element for a phlag
     * 
     * Builds a complete table row with all phlag data including
     * status badge and action buttons. The ID column is not included
     * in the display.
     * 
     * @param {object} phlag - Phlag object with all properties
     * 
     * @return {HTMLElement} Table row element ready to append
     * 
     * @private
     */
    /**
     * Creates a table row for a phlag
     * 
     * Builds a complete table row with all phlag data. Note that environment
     * values are not loaded at list time, so the Environments column shows
     * a prompt to view details.
     * 
     * ## Breaking Change (v2.0)
     * 
     * The row no longer shows value, start_datetime, or end_datetime columns.
     * Instead it shows description and environment information.
     * 
     * @param {object} phlag - Phlag object with all properties
     * 
     * @return {HTMLElement} Table row element ready to append
     * 
     * @private
     */
    _createTableRow: function(phlag) {
        const row = document.createElement('tr');
        
        const description = phlag.description ? 
            `<div class="flag-description">${this._escapeHtml(phlag.description)}</div>` : '';
        
        row.innerHTML = `
            <td>
                <a href="${this.base_url}/flags/${phlag.phlag_id}">${this._escapeHtml(phlag.name)}</a>
                ${description}
            </td>
            <td class="hide-mobile">
                <div class="flag-description-truncate">${phlag.description ? this._escapeHtml(phlag.description) : '<em>No description</em>'}</div>
            </td>
            <td class="hide-mobile"><span class="badge">${phlag.type || 'N/A'}</span></td>
            <td class="hide-mobile">
                <a href="${this.base_url}/flags/${phlag.phlag_id}" class="view-environments">View Details</a>
            </td>
            <td class="actions">
                <a href="${this.base_url}/flags/${phlag.phlag_id}" class="btn btn-small btn-primary">View</a>
                <a href="${this.base_url}/flags/${phlag.phlag_id}/edit" class="btn btn-small btn-secondary">Edit</a>
                <button onclick="PhlagManager.delete(${phlag.phlag_id})" class="btn btn-small btn-danger">Delete</button>
            </td>
        `;
        
        return row;
    },
    
    /**
     * Populates the detail view with phlag data
     * 
     * Displays the flag's basic information and environment-specific values
     * in a table format.
     * 
     * ## Breaking Change (v2.0)
     * 
     * Now accepts environment values and renders them in a table. The old
     * single value/temporal fields are no longer displayed.
     * 
     * @param {object} phlag - Phlag object
     * @param {Array} env_values - Array of environment value objects
     * 
     * @private
     */
    _populateDetails: function(phlag, env_values) {
        document.getElementById('detail-name').textContent = phlag.name;
        document.getElementById('detail-description').textContent = phlag.description || 'N/A';
        document.getElementById('detail-type').textContent = phlag.type || 'N/A';
        document.getElementById('detail-created').textContent = this._formatDate(phlag.create_datetime);
        document.getElementById('detail-updated').textContent = this._formatDate(phlag.update_datetime);
        
        // Render environment values table
        this._renderEnvironmentValuesDisplay(phlag, env_values);
    },
    
    /**
     * Renders the environment values display table
     * 
     * Creates a table showing all environments and their configured values
     * for this flag. Shows "Not configured" for environments without values.
     * 
     * @param {object} phlag - Phlag object
     * @param {Array} env_values - Array of environment value objects
     * 
     * @private
     */
    _renderEnvironmentValuesDisplay: function(phlag, env_values) {
        const container = document.getElementById('environment-values-display');
        
        if (this.environments.length === 0) {
            container.innerHTML = '<p class="text-center"><em>No environments configured.</em></p>';
            return;
        }
        
        // Create a map of environment values by environment ID
        const env_value_map = {};
        env_values.forEach(ev => {
            env_value_map[ev.phlag_environment_id] = ev;
        });
        
        // Build table
        let table_html = `
            <table class="environment-values-table">
                <thead>
                    <tr>
                        <th>Environment</th>
                        <th>Value</th>
                        <th>Start Date/Time</th>
                        <th>End Date/Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
        `;
        
        this.environments.forEach(env => {
            const env_value = env_value_map[env.phlag_environment_id];
            
            if (!env_value || env_value.value === null) {
                // Not configured or explicitly disabled
                const display_value = env_value ? '<em>Disabled</em>' : '<em>Not configured</em>';
                table_html += `
                    <tr class="env-not-configured">
                        <td><strong>${this._escapeHtml(env.name)}</strong></td>
                        <td>${display_value}</td>
                        <td colspan="3">—</td>
                    </tr>
                `;
            } else {
                // Format value based on type
                let display_value = env_value.value;
                if (phlag.type === 'SWITCH') {
                    display_value = env_value.value === 'true' ? '✓ true' : '✗ false';
                }
                
                // Determine status
                const now = new Date();
                let status_label = 'Active';
                let status_class = 'active';
                
                if (env_value.start_datetime) {
                    const start = new Date(env_value.start_datetime);
                    if (now < start) {
                        status_label = 'Scheduled';
                        status_class = 'scheduled';
                    }
                }
                
                if (env_value.end_datetime) {
                    const end = new Date(env_value.end_datetime);
                    if (now > end) {
                        status_label = 'Expired';
                        status_class = 'expired';
                    }
                }
                
                table_html += `
                    <tr>
                        <td><strong>${this._escapeHtml(env.name)}</strong></td>
                        <td><code>${this._escapeHtml(display_value)}</code></td>
                        <td>${this._formatDate(env_value.start_datetime)}</td>
                        <td>${this._formatDate(env_value.end_datetime)}</td>
                        <td><span class="status-badge status-${status_class}">${status_label}</span></td>
                    </tr>
                `;
            }
        });
        
        table_html += `
                </tbody>
            </table>
        `;
        
        container.innerHTML = table_html;
    },
    
    /**
     * Populates the form with phlag data for editing
     * 
     * @param {object} phlag - Phlag object
     * @private
     */
    /**
     * Populates the form with phlag data for editing
     * 
     * Sets all form fields with the flag's current values including
     * name, description, type, and environment-specific values.
     * 
     * ## Breaking Change (v2.0)
     * 
     * Now accepts environment values as second parameter and populates
     * environment value inputs for each configured environment.
     * 
     * @param {object} phlag - Phlag object
     * @param {Array} env_values - Array of environment value objects
     * 
     * @private
     */
    _populateForm: function(phlag, env_values) {
        document.getElementById('name').value = phlag.name || '';
        document.getElementById('description').value = phlag.description || '';
        
        const type_select = document.getElementById('type');
        type_select.value = phlag.type || '';
        
        // Trigger type change for environment inputs
        if (phlag.type) {
            this._updateEnvironmentInputTypes(phlag.type);
        }
        
        // Populate environment values if provided
        if (env_values && env_values.length > 0) {
            this._populateEnvironmentValues(env_values, phlag.type);
        }
    },
    
    /**
     * Populates environment value inputs with existing data
     * 
     * Fills in the value, start_datetime, and end_datetime inputs for
     * each environment that has a configured value.
     * 
     * @param {Array} env_values - Array of environment value objects
     * @param {string} type - Flag type (SWITCH, INTEGER, FLOAT, STRING)
     * 
     * @private
     */
    _populateEnvironmentValues: function(env_values, type) {
        const is_switch = (type === 'SWITCH');
        
        env_values.forEach(ev => {
            const env_id = ev.phlag_environment_id;
            const value_input = document.getElementById(`env_value_${env_id}`);
            const value_select = document.getElementById(`env_value_select_${env_id}`);
            const start_input = document.getElementById(`env_start_${env_id}`);
            const end_input = document.getElementById(`env_end_${env_id}`);
            
            if (!value_input) {
                return;
            }
            
            // Set value in appropriate input
            if (is_switch && value_select) {
                value_select.value = ev.value || '';
            } else {
                value_input.value = ev.value || '';
            }
            
            // Set temporal constraints
            if (ev.start_datetime && start_input) {
                start_input.value = this._toDateTimeLocal(ev.start_datetime);
            }
            if (ev.end_datetime && end_input) {
                end_input.value = this._toDateTimeLocal(ev.end_datetime);
            }
        });
    },
    
    /**
     * Determines the status of a phlag based on dates
     * 
     * @param {object} phlag - Phlag object
     * @returns {object} Status with label and class
     * @private
     */
    _determineStatus: function(phlag) {
        const now = new Date();
        
        if (phlag.start_datetime) {
            const start = new Date(phlag.start_datetime);
            if (now < start) {
                return { label: 'Scheduled', class: 'scheduled' };
            }
        }
        
        if (phlag.end_datetime) {
            const end = new Date(phlag.end_datetime);
            if (now > end) {
                return { label: 'Expired', class: 'expired' };
            }
        }
        
        return { label: 'Active', class: 'active' };
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
     * Formats a datetime-local value to datetime string
     * 
     * @param {string} datetime_local - datetime-local value
     * @returns {string} Formatted datetime string
     * @private
     */
    _formatDateTime: function(datetime_local) {
        if (!datetime_local) {
            return null;
        }
        
        // Convert from YYYY-MM-DDTHH:MM to YYYY-MM-DD HH:MM:SS
        return datetime_local.replace('T', ' ') + ':00';
    },
    
    /**
     * Converts datetime string to datetime-local format
     * 
     * @param {string} datetime - Datetime string
     * @returns {string} datetime-local formatted string
     * @private
     */
    _toDateTimeLocal: function(datetime) {
        if (!datetime) {
            return '';
        }
        
        const date = new Date(datetime);
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        
        return `${year}-${month}-${day}T${hours}:${minutes}`;
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
