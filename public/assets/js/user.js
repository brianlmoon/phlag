/**
 * User Manager
 * 
 * Handles all CRUD operations for PhlagUser objects via AJAX calls
 * to the REST API. Manages the UI for listing, creating, editing,
 * and viewing users. Passwords are handled securely and never displayed.
 */

const UserManager = {
    
    /**
     * API client instance
     */
    api: null,
    
    /**
     * Base URL for the application
     */
    base_url: '',
    
    /**
     * Initializes the User Manager
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
     * Loads and displays the list of users
     * 
     * Makes a GET request to /api/PhlagUser and populates the table
     * with the results. Shows empty state if no users exist.
     * Sorts users by username in ascending order.
     */
    loadList: function() {
        UI.showLoading();
        
        this.api.get('/PhlagUser/')
            .then(data => {
                UI.hideLoading();
                
                const tbody = document.querySelector('#users-table tbody');
                
                // Clear existing rows
                tbody.innerHTML = '';
                
                if (!data || data.length === 0) {
                    const row = document.createElement('tr');
                    row.innerHTML = '<td colspan="4" style="text-align: center;">No users found</td>';
                    tbody.appendChild(row);
                    return;
                }
                
                // Sort users by username ascending
                data.sort((a, b) => {
                    const name_a = (a.username || '').toLowerCase();
                    const name_b = (b.username || '').toLowerCase();
                    return name_a.localeCompare(name_b);
                });
                
                // Populate table with users
                data.forEach(user => {
                    const row = this._createTableRow(user);
                    tbody.appendChild(row);
                });
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to load users: ' + error.message, 'error');
            });
    },
    
    /**
     * Creates a table row element for a user
     * 
     * @param {object} user - User object from API
     * @returns {HTMLElement} Table row element
     * @private
     */
    _createTableRow: function(user) {
        const row = document.createElement('tr');
        
        const username = this._escapeHtml(user.username || '');
        const full_name = this._escapeHtml(user.full_name || '');
        const created = user.create_datetime || '';
        
        row.innerHTML = `
            <td>${username}</td>
            <td class="hide-mobile">${full_name}</td>
            <td class="hide-mobile">${created}</td>
            <td class="table-actions">
                <a href="${this.base_url}/users/${user.phlag_user_id}" class="btn btn-sm">View</a>
                <a href="${this.base_url}/users/${user.phlag_user_id}/edit" class="btn btn-sm btn-primary">Edit</a>
            </td>
        `;
        
        return row;
    },
    
    /**
     * Loads a single user for viewing
     * 
     * @param {number} id - User ID to load
     */
    loadSingle: function(id) {
        UI.showLoading();
        
        this.api.get(`/PhlagUser/${id}/`)
            .then(user => {
                UI.hideLoading();
                this._populateView(user);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to load user: ' + error.message, 'error');
            });
    },
    
    /**
     * Populates the view page with user data
     * 
     * @param {object} user - User object from API
     * @private
     */
    _populateView: function(user) {
        document.getElementById('username').textContent = user.username || '';
        document.getElementById('full_name').textContent = user.full_name || '';
        document.getElementById('email').textContent = user.email || 'Not set';
        document.getElementById('create_datetime').textContent = user.create_datetime || 'N/A';
        document.getElementById('update_datetime').textContent = user.update_datetime || 'N/A';
    },
    
    /**
     * Loads a user for editing
     * 
     * @param {number} id - User ID to load
     */
    loadForEdit: function(id) {
        UI.showLoading();
        
        this.api.get(`/PhlagUser/${id}/`)
            .then(user => {
                UI.hideLoading();
                this._populateForm(user);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to load user: ' + error.message, 'error');
            });
    },
    
    /**
     * Populates the edit form with user data
     * 
     * Password field is left empty for security - it will only be
     * updated if a new password is entered.
     * 
     * @param {object} user - User object from API
     * @private
     */
    _populateForm: function(user) {
        document.getElementById('username').value = user.username || '';
        document.getElementById('full_name').value = user.full_name || '';
        document.getElementById('email').value = user.email || '';
        // Never populate password field
    },
    
    /**
     * Creates a new user
     * 
     * Validates the form, checks password confirmation, and submits
     * to the API. Redirects to the user list on success.
     */
    create: function() {
        const form = document.getElementById('user-form');
        
        // Validate required fields
        if (!FormUtils.validateRequired(form, ['username', 'full_name', 'email', 'password', 'password_confirm'])) {
            UI.showMessage('Please fill in all required fields', 'error');
            return;
        }
        
        const data = FormUtils.serialize(form);
        
        // Validate email format
        const email_pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email_pattern.test(data.email)) {
            UI.showMessage('Please enter a valid email address', 'error');
            return;
        }
        
        // Validate password confirmation
        if (data.password !== data.password_confirm) {
            UI.showMessage('Passwords do not match', 'error');
            return;
        }
        
        // Validate password length
        if (data.password.length < 8) {
            UI.showMessage('Password must be at least 8 characters', 'error');
            return;
        }
        
        // Remove password_confirm from data
        delete data.password_confirm;
        
        UI.showLoading();
        FormUtils.clearErrors(form);
        
        this.api.post('/PhlagUser/', data)
            .then(user => {
                UI.hideLoading();
                UI.showMessage('User created successfully', 'success');
                window.location.href = `${this.base_url}/users/${user.phlag_user_id}`;
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to create user: ' + error.message, 'error');
            });
    },
    
    /**
     * Updates an existing user
     * 
     * If password field is empty, it won't be updated. Otherwise,
     * the new password will be set.
     * 
     * @param {number} id - User ID to update
     */
    update: function(id) {
        const form = document.getElementById('user-form');
        
        // Validate required fields (password not required on edit)
        if (!FormUtils.validateRequired(form, ['username', 'full_name', 'email'])) {
            UI.showMessage('Please fill in all required fields', 'error');
            return;
        }
        
        const data = FormUtils.serialize(form);
        
        // Validate email format
        const email_pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!email_pattern.test(data.email)) {
            UI.showMessage('Please enter a valid email address', 'error');
            return;
        }
        
        // If password is empty, remove it from data (don't update it)
        if (!data.password || data.password.trim() === '') {
            delete data.password;
        } else {
            // Validate password length if provided
            if (data.password.length < 8) {
                UI.showMessage('Password must be at least 8 characters', 'error');
                return;
            }
        }
        
        UI.showLoading();
        FormUtils.clearErrors(form);
        
        this.api.put(`/PhlagUser/${id}/`, data)
            .then(user => {
                UI.hideLoading();
                UI.showMessage('User updated successfully', 'success');
                window.location.href = `${this.base_url}/users/${id}`;
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to update user: ' + error.message, 'error');
            });
    },
    
    /**
     * Deletes a user
     * 
     * Shows a confirmation dialog before deleting. Redirects to
     * user list on success.
     * 
     * @param {number} id - User ID to delete
     */
    delete: function(id) {
        if (!UI.confirm('Are you sure you want to delete this user? This action cannot be undone.')) {
            return;
        }
        
        UI.showLoading();
        
        this.api.delete(`/PhlagUser/${id}/`)
            .then(() => {
                UI.hideLoading();
                UI.showMessage('User deleted successfully', 'success');
                window.location.href = `${this.base_url}/users`;
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to delete user: ' + error.message, 'error');
            });
    },
    
    /**
     * Escapes HTML special characters in text
     * 
     * Prevents XSS attacks by converting HTML special characters
     * to their entity equivalents.
     * 
     * @param {string} text - Text to escape
     * @returns {string} Escaped text safe for HTML insertion
     * @private
     */
    _escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};
