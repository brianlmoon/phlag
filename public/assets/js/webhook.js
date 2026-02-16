/**
 * Webhook Manager
 *
 * Handles all CRUD operations for PhlagWebhook objects via AJAX calls
 * to the REST API. Manages the UI for listing, creating, editing,
 * and testing webhooks.
 *
 * ## Key Features
 *
 * - Dynamic header management (add/remove custom HTTP headers)
 * - Event type filtering via checkboxes
 * - Twig template payload customization
 * - Test webhook delivery with result display
 */

const WebhookManager = {

    /**
     * API client instance
     */
    api: null,

    /**
     * Base URL for the application
     */
    base_url: '',

    /**
     * Initializes the Webhook Manager
     *
     * @param {string} api_url - Base URL for API endpoints (e.g., '/api')
     */
    init: function(api_url) {
        this.api = new ApiClient(api_url);

        // Get base URL from current window location
        const pathname = window.location.pathname;
        const routes = ['/flags', '/api-keys', '/webhooks', '/users', '/environments', '/login', '/logout'];
        let base_path = '';

        for (const route of routes) {
            const route_index = pathname.indexOf(route);
            if (route_index !== -1) {
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
     * Loads and displays the list of webhooks
     *
     * Makes a GET request to /api/PhlagWebhook and populates the table
     * with the results. Shows empty state if no webhooks exist.
     */
    loadList: function() {
        UI.showLoading();

        this.api.get('/PhlagWebhook/')
            .then(data => {
                UI.hideLoading();

                const loading = document.getElementById('loading');
                const container = document.getElementById('webhook-table-container');
                const tbody = document.getElementById('webhook-tbody');
                const empty_state = document.getElementById('empty-state');

                loading.classList.add('hidden');
                container.classList.remove('hidden');

                // Clear existing rows
                tbody.innerHTML = '';

                if (!data || data.length === 0) {
                    document.getElementById('webhook-table').classList.add('hidden');
                    empty_state.classList.remove('hidden');
                    return;
                }

                // Sort by name (A-Z)
                data.sort((a, b) => a.name.localeCompare(b.name));

                // Populate table
                data.forEach(webhook => {
                    const row = this._createTableRow(webhook);
                    tbody.appendChild(row);
                });

                // Initialize search functionality
                this._initSearch(data);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to load webhooks: ' + error.message, 'error');
                document.getElementById('loading').innerHTML =
                    '<p class="error">Error loading webhooks. Please try again.</p>';
            });
    },

    /**
     * Creates a table row for a webhook
     *
     * @param {object} webhook - Webhook data object
     * @returns {HTMLElement} Table row element
     * @protected
     */
    _createTableRow: function(webhook) {
        const row = document.createElement('tr');
        row.dataset.webhookId = webhook.phlag_webhook_id;
        row.dataset.name = webhook.name.toLowerCase();
        row.dataset.url = webhook.url.toLowerCase();

        // Name cell
        const name_cell = document.createElement('td');
        name_cell.textContent = webhook.name;
        row.appendChild(name_cell);

        // URL cell (truncate long URLs)
        const url_cell = document.createElement('td');
        url_cell.className = 'hide-mobile';
        const display_url = webhook.url.length > 50
            ? webhook.url.substring(0, 47) + '...'
            : webhook.url;
        url_cell.textContent = display_url;
        url_cell.title = webhook.url;
        row.appendChild(url_cell);

        // Events cell
        const events_cell = document.createElement('td');
        events_cell.className = 'hide-mobile';
        const event_types = JSON.parse(webhook.event_types_json || '[]');
        events_cell.textContent = event_types.length + ' event' + (event_types.length !== 1 ? 's' : '');
        events_cell.title = event_types.join(', ');
        row.appendChild(events_cell);

        // Status cell
        const status_cell = document.createElement('td');
        const status_badge = document.createElement('span');
        status_badge.className = 'status-badge ' + (webhook.is_active ? 'status-active' : 'status-inactive');
        status_badge.textContent = webhook.is_active ? 'Active' : 'Inactive';
        status_cell.appendChild(status_badge);
        row.appendChild(status_cell);

        // Actions cell
        const actions_cell = document.createElement('td');
        actions_cell.className = 'actions';

        const edit_link = document.createElement('a');
        edit_link.href = this.base_url + '/webhooks/' + webhook.phlag_webhook_id + '/edit';
        edit_link.textContent = 'Edit';
        edit_link.className = 'btn btn-small btn-secondary';
        actions_cell.appendChild(edit_link);

        const test_link = document.createElement('a');
        test_link.href = '#';
        test_link.textContent = 'Test';
        test_link.className = 'btn btn-small btn-primary';
        test_link.onclick = (e) => {
            e.preventDefault();
            this.testWebhook(webhook.phlag_webhook_id);
        };
        actions_cell.appendChild(test_link);

        const delete_link = document.createElement('a');
        delete_link.href = '#';
        delete_link.textContent = 'Delete';
        delete_link.className = 'btn btn-small btn-danger';
        delete_link.onclick = (e) => {
            e.preventDefault();
            this.deleteWebhook(webhook.phlag_webhook_id, webhook.name);
        };
        actions_cell.appendChild(delete_link);

        row.appendChild(actions_cell);

        return row;
    },

    /**
     * Initializes search functionality
     *
     * @param {Array} webhooks - Array of webhook objects
     * @protected
     */
    _initSearch: function(webhooks) {
        const search_input = document.getElementById('webhook-search');
        const clear_btn = document.getElementById('search-clear');
        const results_counter = document.getElementById('results-counter');
        const visible_count = document.getElementById('visible-count');
        const total_count = document.getElementById('total-count');
        const no_results = document.getElementById('no-results-message');

        if (!search_input) return;

        total_count.textContent = webhooks.length;

        const performSearch = () => {
            const query = search_input.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#webhook-tbody tr');
            let visible = 0;

            if (query === '') {
                rows.forEach(row => {
                    row.style.display = '';
                    visible++;
                });
                clear_btn.classList.add('hidden');
                results_counter.classList.add('hidden');
                no_results.classList.add('hidden');
            } else {
                rows.forEach(row => {
                    const name = row.dataset.name || '';
                    const url = row.dataset.url || '';

                    if (name.includes(query) || url.includes(query)) {
                        row.style.display = '';
                        visible++;
                    } else {
                        row.style.display = 'none';
                    }
                });
                clear_btn.classList.remove('hidden');
                results_counter.classList.remove('hidden');

                if (visible === 0) {
                    no_results.classList.remove('hidden');
                } else {
                    no_results.classList.add('hidden');
                }
            }

            visible_count.textContent = visible;
        };

        search_input.addEventListener('input', performSearch);

        clear_btn.addEventListener('click', () => {
            search_input.value = '';
            performSearch();
            search_input.focus();
        });
    },

    /**
     * Initializes the webhook form
     *
     * Sets up event handlers for dynamic header management, event type
     * checkboxes, and form submission.
     */
    initForm: function() {
        const form = document.getElementById('webhook-form');
        if (!form) return;

        // Set default payload template if creating
        if (form.dataset.mode === 'create') {
            this._setDefaultPayloadTemplate();
        }

        // Add header button
        const add_header_btn = document.getElementById('add-header-btn');
        if (add_header_btn) {
            add_header_btn.addEventListener('click', () => this._addHeaderRow());
        }

        // Initialize remove buttons for existing headers
        this._initRemoveHeaderButtons();

        // Form submission
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.saveWebhook();
        });
    },

    /**
     * Sets the default payload template in the textarea
     *
     * @protected
     */
    _setDefaultPayloadTemplate: function() {
        const textarea = document.getElementById('payload_template');
        if (!textarea || textarea.value.trim() !== '') return;

        const default_template = `{
  "event": "{{ event_type }}",
  "flag": {
    "name": "{{ flag.name }}",
    "type": "{{ flag.type }}",
    "description": "{{ flag.description }}",
    "environments": [
      {% for env in environments %}
      {
        "name": "{{ env.name }}",
        "value": {{ env.value|json_encode|raw }},
        "start_datetime": {{ env.start_datetime|json_encode|raw }},
        "end_datetime": {{ env.end_datetime|json_encode|raw }}
      }{% if not loop.last %},{% endif %}
      {% endfor %}
    ]
  }{% if previous %},
  "previous": {
    "name": "{{ previous.name }}",
    "type": "{{ previous.type }}",
    "description": "{{ previous.description }}"
  }{% endif %},
  "timestamp": "{{ timestamp }}"
}`;

        textarea.value = default_template;
    },

    /**
     * Adds a new header row to the headers container
     *
     * @protected
     */
    _addHeaderRow: function() {
        const container = document.getElementById('headers-container');
        const row = document.createElement('div');
        row.className = 'header-row';

        const name_input = document.createElement('input');
        name_input.type = 'text';
        name_input.name = 'header_name[]';
        name_input.placeholder = 'Header Name';
        name_input.className = 'header-name';

        const value_input = document.createElement('input');
        value_input.type = 'text';
        value_input.name = 'header_value[]';
        value_input.placeholder = 'Header Value';
        value_input.className = 'header-value';

        const remove_btn = document.createElement('button');
        remove_btn.type = 'button';
        remove_btn.className = 'btn-remove-header';
        remove_btn.textContent = 'Remove';
        remove_btn.title = 'Remove header';
        remove_btn.onclick = () => row.remove();

        row.appendChild(name_input);
        row.appendChild(value_input);
        row.appendChild(remove_btn);

        container.appendChild(row);
    },

    /**
     * Initializes remove button handlers for existing header rows
     *
     * @protected
     */
    _initRemoveHeaderButtons: function() {
        const buttons = document.querySelectorAll('.btn-remove-header');
        buttons.forEach(btn => {
            btn.onclick = () => btn.closest('.header-row').remove();
        });
    },

    /**
     * Loads a webhook for editing
     *
     * @param {number} id - Webhook ID to load
     */
    loadForEdit: function(id) {
        UI.showLoading();

        this.api.get('/PhlagWebhook/' + id + '/')
            .then(webhook => {
                UI.hideLoading();
                this._populateForm(webhook);
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to load webhook: ' + error.message, 'error');

                if (error.status === 404) {
                    alert('Webhook not found.');
                    window.location.href = this.base_url + '/webhooks';
                }
            });
    },

    /**
     * Populates the form with webhook data
     *
     * @param {object} webhook - Webhook data object
     * @protected
     */
    _populateForm: function(webhook) {
        document.getElementById('name').value = webhook.name || '';
        document.getElementById('url').value = webhook.url || '';
        document.getElementById('is_active').value = webhook.is_active ? '1' : '0';
        document.getElementById('include_environment_changes').checked = !!webhook.include_environment_changes;
        document.getElementById('payload_template').value = webhook.payload_template || '';

        // Event types
        const event_types = JSON.parse(webhook.event_types_json || '[]');
        document.querySelectorAll('input[name="event_types[]"]').forEach(checkbox => {
            checkbox.checked = event_types.includes(checkbox.value);
        });

        // Headers
        const headers = JSON.parse(webhook.headers_json || '{}');
        const container = document.getElementById('headers-container');
        container.innerHTML = '';

        if (Object.keys(headers).length === 0) {
            this._addHeaderRow();
        } else {
            for (const [name, value] of Object.entries(headers)) {
                const row = document.createElement('div');
                row.className = 'header-row';

                const name_input = document.createElement('input');
                name_input.type = 'text';
                name_input.name = 'header_name[]';
                name_input.value = name;
                name_input.className = 'header-name';

                const value_input = document.createElement('input');
                value_input.type = 'text';
                value_input.name = 'header_value[]';
                value_input.value = value;
                value_input.className = 'header-value';

                const remove_btn = document.createElement('button');
                remove_btn.type = 'button';
                remove_btn.className = 'btn-remove-header';
                remove_btn.textContent = 'Remove';
                remove_btn.title = 'Remove header';
                remove_btn.onclick = () => row.remove();

                row.appendChild(name_input);
                row.appendChild(value_input);
                row.appendChild(remove_btn);

                container.appendChild(row);
            }
        }
    },

    /**
     * Saves the webhook (create or update)
     */
    saveWebhook: function() {
        const form = document.getElementById('webhook-form');
        const mode = form.dataset.mode;
        const webhook_data = this._collectFormData();

        // Validate
        if (!webhook_data.name || !webhook_data.url) {
            UI.showMessage('Name and URL are required', 'error');
            return;
        }

        if (webhook_data.event_types_json === '[]') {
            UI.showMessage('At least one event type is required', 'error');
            return;
        }

        UI.showLoading();

        if (mode === 'create') {
            this.api.post('/PhlagWebhook/', webhook_data)
                .then(() => {
                    UI.hideLoading();
                    UI.showMessage('Webhook created successfully', 'success');
                    setTimeout(() => {
                        window.location.href = this.base_url + '/webhooks';
                    }, 1000);
                })
                .catch(error => {
                    UI.hideLoading();
                    UI.showMessage('Failed to create webhook: ' + error.message, 'error');
                });
        } else {
            const webhook_id = form.dataset.webhookId;
            this.api.put('/PhlagWebhook/' + webhook_id + '/', webhook_data)
                .then(() => {
                    UI.hideLoading();
                    UI.showMessage('Webhook updated successfully', 'success');
                    setTimeout(() => {
                        window.location.href = this.base_url + '/webhooks';
                    }, 1000);
                })
                .catch(error => {
                    UI.hideLoading();
                    UI.showMessage('Failed to update webhook: ' + error.message, 'error');
                });
        }
    },

    /**
     * Collects form data into an object
     *
     * @returns {object} Webhook data object
     * @protected
     */
    _collectFormData: function() {
        const data = {
            name: document.getElementById('name').value.trim(),
            url: document.getElementById('url').value.trim(),
            is_active: parseInt(document.getElementById('is_active').value),
            include_environment_changes: document.getElementById('include_environment_changes').checked ? 1 : 0,
            payload_template: document.getElementById('payload_template').value,
        };

        // Collect event types
        const event_types = [];
        document.querySelectorAll('input[name="event_types[]"]:checked').forEach(checkbox => {
            event_types.push(checkbox.value);
        });
        data.event_types_json = JSON.stringify(event_types);

        // Collect headers
        const headers = {};
        const header_names = document.querySelectorAll('input[name="header_name[]"]');
        const header_values = document.querySelectorAll('input[name="header_value[]"]');

        for (let i = 0; i < header_names.length; i++) {
            const name = header_names[i].value.trim();
            const value = header_values[i].value.trim();
            if (name && value) {
                headers[name] = value;
            }
        }
        data.headers_json = JSON.stringify(headers);

        return data;
    },

    /**
     * Shows the test webhook modal and loads flags
     *
     * @param {number} id - Webhook ID to test
     */
    testWebhook: function(id) {
        const modal = document.getElementById('test-webhook-modal');
        const result_div = document.getElementById('test-webhook-result');
        const flag_select = document.getElementById('test-flag-select');
        const send_btn = document.getElementById('send-test-btn');

        // Show modal
        modal.classList.remove('hidden');
        result_div.innerHTML = '';
        send_btn.disabled = true;

        // Reset and load flags
        flag_select.innerHTML = '<option value="">-- Choose a flag --</option>';
        flag_select.disabled = true;

        this.api.get('/Phlag/')
            .then(flags => {
                flags.forEach(flag => {
                    const option = document.createElement('option');
                    option.value = flag.phlag_id;
                    option.textContent = `${flag.name} (${flag.type})`;
                    flag_select.appendChild(option);
                });
                flag_select.disabled = false;
            })
            .catch(error => {
                result_div.innerHTML = `
                    <div class="test-result error">
                        <h3>✗ Error Loading Flags</h3>
                        <p>${this._escapeHtml(error.message)}</p>
                    </div>
                `;
            });

        // Enable Send Test button when flag selected
        flag_select.onchange = () => {
            send_btn.disabled = !flag_select.value;
        };

        // Send Test button click
        send_btn.onclick = () => {
            const phlag_id = parseInt(flag_select.value);
            if (!phlag_id) {
                return;
            }

            result_div.innerHTML = '<p>Sending test webhook...</p>';
            send_btn.disabled = true;

            this.api.post('/PhlagWebhook/test/', {
                phlag_webhook_id: id,
                phlag_id: phlag_id
            })
                .then(response => {
                    result_div.innerHTML = `
                        <div class="test-result success">
                            <h3>✓ Test Successful</h3>
                            <p><strong>Status Code:</strong> ${response.status_code}</p>
                            <p><strong>Response:</strong></p>
                            <pre>${this._escapeHtml(response.response_body)}</pre>
                        </div>
                    `;
                })
                .catch(error => {
                    result_div.innerHTML = `
                        <div class="test-result error">
                            <h3>✗ Test Failed</h3>
                            <p><strong>Error:</strong> ${this._escapeHtml(error.message)}</p>
                            ${error.details ? '<p><strong>Details:</strong> ' + this._escapeHtml(error.details) + '</p>' : ''}
                        </div>
                    `;
                })
                .finally(() => {
                    send_btn.disabled = !flag_select.value;
                });
        };

        // Close button
        const close_btn = document.getElementById('close-test-modal-btn');
        const close_x = modal.querySelector('.modal-close');

        close_btn.onclick = () => modal.classList.add('hidden');
        close_x.onclick = () => modal.classList.add('hidden');

        modal.onclick = (e) => {
            if (e.target === modal) {
                modal.classList.add('hidden');
            }
        };
    },

    /**
     * Deletes a webhook with confirmation
     *
     * @param {number} id - Webhook ID to delete
     * @param {string} name - Webhook name for confirmation message
     */
    deleteWebhook: function(id, name) {
        if (!confirm('Are you sure you want to delete the webhook "' + name + '"? This action cannot be undone.')) {
            return;
        }

        UI.showLoading();

        this.api.delete('/PhlagWebhook/' + id + '/')
            .then(() => {
                UI.hideLoading();
                UI.showMessage('Webhook deleted successfully', 'success');

                // Remove row from table
                const row = document.querySelector('tr[data-webhook-id="' + id + '"]');
                if (row) {
                    row.remove();
                }

                // Check if table is empty
                const tbody = document.getElementById('webhook-tbody');
                if (tbody.children.length === 0) {
                    document.getElementById('webhook-table').classList.add('hidden');
                    document.getElementById('empty-state').classList.remove('hidden');
                }
            })
            .catch(error => {
                UI.hideLoading();
                UI.showMessage('Failed to delete webhook: ' + error.message, 'error');
            });
    },

    /**
     * Escapes HTML special characters to prevent XSS
     *
     * @param {string} text - Text to escape
     * @returns {string} HTML-escaped text
     * @protected
     */
    _escapeHtml: function(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};
