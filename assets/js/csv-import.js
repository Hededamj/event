/**
 * CSV Guest Import - Shared JS
 * Used by app/events/pages/guests.php (and formerly admin/guests.php)
 * Handles file parsing, column mapping, preview, and form submission.
 */

let csvHeaders = [];
let csvRows = [];
let columnMapping = {};

function handleFileSelect(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        const content = e.target.result;
        parseCSV(content);
    };
    reader.readAsText(file, 'UTF-8');
}

function parseTextArea() {
    const content = document.getElementById('csv-paste-area').value;
    if (content.trim()) {
        parseCSV(content);
    }
}

function parseCSV(content) {
    // Detect separator (semicolon or comma)
    const firstLine = content.split('\n')[0];
    const separator = firstLine.includes(';') ? ';' : ',';

    // Parse CSV
    const lines = content.trim().split('\n');
    if (lines.length < 2) {
        alert('CSV-filen skal have mindst en header-raekke og en data-raekke');
        return;
    }

    // Parse header
    csvHeaders = parseCSVLine(lines[0], separator);

    // Parse data rows
    csvRows = [];
    for (let i = 1; i < lines.length; i++) {
        const line = lines[i].trim();
        if (!line) continue;

        const values = parseCSVLine(line, separator);
        const row = {};
        csvHeaders.forEach((header, index) => {
            row[index] = values[index] || '';
        });
        csvRows.push(row);
    }

    if (csvRows.length === 0) {
        alert('Ingen data-raekker fundet i CSV-filen');
        return;
    }

    // Initialize column mapping and go to step 2
    initializeColumnMapping();
    goToStep(2);
}

function parseCSVLine(line, separator) {
    const values = [];
    let current = '';
    let inQuotes = false;

    for (let i = 0; i < line.length; i++) {
        const char = line[i];

        if (char === '"') {
            inQuotes = !inQuotes;
        } else if (char === separator && !inQuotes) {
            values.push(current.trim());
            current = '';
        } else {
            current += char;
        }
    }
    values.push(current.trim());

    return values;
}

function initializeColumnMapping() {
    const container = document.getElementById('column-mapping-container');
    container.innerHTML = '';

    // Field options
    const fieldOptions = [
        { value: '', label: '-- Spring over --' },
        { value: 'name', label: 'Navn (pakraevet)' },
        { value: 'email', label: 'Email' },
        { value: 'phone', label: 'Telefon' },
        { value: 'max_guests', label: 'Max gaester' },
        { value: 'notes', label: 'Noter' }
    ];

    // Auto-detect mapping based on header names
    const autoMapping = {};
    csvHeaders.forEach((header, index) => {
        const headerLower = header.toLowerCase().trim();
        if (headerLower.includes('navn') || headerLower === 'name') {
            autoMapping[index] = 'name';
        } else if (headerLower.includes('email') || headerLower.includes('mail') || headerLower.includes('e-mail')) {
            autoMapping[index] = 'email';
        } else if (headerLower.includes('telefon') || headerLower.includes('phone') || headerLower.includes('tlf') || headerLower.includes('mobil')) {
            autoMapping[index] = 'phone';
        } else if (headerLower.includes('max') || headerLower.includes('antal') || headerLower.includes('gaester')) {
            autoMapping[index] = 'max_guests';
        } else if (headerLower.includes('note') || headerLower.includes('kommentar') || headerLower.includes('bemaerk')) {
            autoMapping[index] = 'notes';
        }
    });

    // Create mapping UI
    csvHeaders.forEach((header, index) => {
        const row = document.createElement('div');
        row.style.cssText = 'display: grid; grid-template-columns: 1fr auto 1fr; gap: 16px; align-items: center; margin-bottom: 12px;';

        // Sample data from first row
        const sampleData = csvRows[0] ? (csvRows[0][index] || '(tom)') : '(tom)';

        row.innerHTML = `
            <div>
                <strong>${escapeHtml(header)}</strong>
                <div style="font-size: 12px; color: var(--text-secondary);">${escapeHtml(sampleData.substring(0, 30))}${sampleData.length > 30 ? '...' : ''}</div>
            </div>
            <div style="color: var(--text-secondary);">\u2192</div>
            <select class="form-input column-mapping-select" data-col="${index}">
                ${fieldOptions.map(opt =>
                    `<option value="${opt.value}" ${autoMapping[index] === opt.value ? 'selected' : ''}>${opt.label}</option>`
                ).join('')}
            </select>
        `;

        container.appendChild(row);
    });

    // Check if name column is mapped
    validateMapping();

    // Add change listeners
    container.querySelectorAll('.column-mapping-select').forEach(select => {
        select.addEventListener('change', validateMapping);
    });
}

function validateMapping() {
    const selects = document.querySelectorAll('.column-mapping-select');
    let hasName = false;

    selects.forEach(select => {
        if (select.value === 'name') hasName = true;
    });

    // Show warning if no name column
    let warning = document.getElementById('mapping-warning');
    if (!hasName) {
        if (!warning) {
            warning = document.createElement('div');
            warning.id = 'mapping-warning';
            warning.style.cssText = 'background: #fef3c7; border: 1px solid #fcd34d; padding: 12px 16px; border-radius: 8px; margin-top: 16px;';
            warning.textContent = 'Du skal vaelge en kolonne til "Navn"';
            document.getElementById('column-mapping-container').appendChild(warning);
        }
    } else if (warning) {
        warning.remove();
    }

    return hasName;
}

function goToStep(step) {
    document.getElementById('import-step-1').style.display = step === 1 ? 'block' : 'none';
    document.getElementById('import-step-2').style.display = step === 2 ? 'block' : 'none';
    document.getElementById('import-step-3').style.display = step === 3 ? 'block' : 'none';

    if (step === 3) {
        if (!validateMapping()) {
            alert('Du skal vaelge en kolonne til "Navn"');
            goToStep(2);
            return;
        }
        buildPreview();
    }
}

function buildPreview() {
    // Collect mapping
    columnMapping = {};
    document.querySelectorAll('.column-mapping-select').forEach(select => {
        const col = select.dataset.col;
        const field = select.value;
        if (field) {
            columnMapping[col] = field;
        }
    });

    // Build preview table
    const thead = document.querySelector('#preview-table thead');
    const tbody = document.querySelector('#preview-table tbody');

    // Headers
    const mappedFields = Object.values(columnMapping);
    const fieldLabels = {
        'name': 'Navn',
        'email': 'Email',
        'phone': 'Telefon',
        'max_guests': 'Max gaester',
        'notes': 'Noter'
    };

    thead.innerHTML = '<tr>' + mappedFields.map(f => `<th>${escapeHtml(fieldLabels[f] || f)}</th>`).join('') + '<th>Status</th></tr>';

    // Rows
    const errors = [];
    let validCount = 0;

    tbody.innerHTML = csvRows.slice(0, 20).map((row, idx) => {
        let rowErrors = [];

        // Get mapped values
        const mappedValues = {};
        for (const [col, field] of Object.entries(columnMapping)) {
            mappedValues[field] = row[col] || '';
        }

        // Validate
        if (!mappedValues.name || !mappedValues.name.trim()) {
            rowErrors.push('Mangler navn');
        }
        if (mappedValues.email && !isValidEmail(mappedValues.email)) {
            rowErrors.push('Ugyldig email');
        }

        if (rowErrors.length === 0) {
            validCount++;
        } else {
            errors.push({ row: idx + 2, errors: rowErrors });
        }

        const statusHtml = rowErrors.length === 0
            ? '<span class="status-badge status-accepted">OK</span>'
            : `<span class="status-badge status-pending" title="${escapeHtml(rowErrors.join(', '))}">${rowErrors.length} problem${rowErrors.length > 1 ? 'er' : ''}</span>`;

        return '<tr>' +
            mappedFields.map(f => `<td>${escapeHtml((mappedValues[f] || '').substring(0, 50))}</td>`).join('') +
            `<td>${statusHtml}</td></tr>`;
    }).join('');

    if (csvRows.length > 20) {
        tbody.innerHTML += `<tr><td colspan="${mappedFields.length + 1}" style="color: var(--text-secondary); text-align: center;">... og ${csvRows.length - 20} flere raekker</td></tr>`;
    }

    // Summary
    document.getElementById('preview-summary').innerHTML = `
        <div style="display: flex; gap: 24px;">
            <div><strong>${csvRows.length}</strong> raekker fundet</div>
            <div><strong style="color: #15803d;">${validCount}</strong> gyldige</div>
            ${errors.length > 0 ? `<div><strong style="color: #f59e0b;">${errors.length}</strong> med problemer</div>` : ''}
        </div>
    `;

    // Errors
    if (errors.length > 0) {
        document.getElementById('preview-errors').style.display = 'block';
        document.getElementById('error-list').innerHTML = errors.slice(0, 10).map(e =>
            `<li>Raekke ${e.row}: ${escapeHtml(e.errors.join(', '))}</li>`
        ).join('') + (errors.length > 10 ? `<li>... og ${errors.length - 10} flere</li>` : '');
    } else {
        document.getElementById('preview-errors').style.display = 'none';
    }

    // Prepare form data
    document.getElementById('csv-data-input').value = JSON.stringify(csvRows);

    // Create mapping inputs
    const form = document.getElementById('import-form');
    form.querySelectorAll('.mapping-field').forEach(el => el.remove());
    for (const [col, field] of Object.entries(columnMapping)) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = `mapping[${col}]`;
        input.value = field;
        input.className = 'mapping-field';
        form.appendChild(input);
    }

    // Skip duplicates
    if (document.getElementById('skip-duplicates').checked) {
        let skipInput = form.querySelector('input[name="skip_duplicates"]');
        if (!skipInput) {
            skipInput = document.createElement('input');
            skipInput.type = 'hidden';
            skipInput.name = 'skip_duplicates';
            skipInput.value = '1';
            form.appendChild(skipInput);
        }
    } else {
        const skipInput = form.querySelector('input[name="skip_duplicates"]');
        if (skipInput) skipInput.remove();
    }

    // Update button text
    document.getElementById('import-submit-btn').textContent = `Importer ${validCount} gaester`;
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
