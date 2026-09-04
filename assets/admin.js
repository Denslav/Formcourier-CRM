function formcourierCrmCreateMappingRow(table) {
    let body = table.querySelector('tbody');
    let crm = table.dataset.crm || '';
    let form = table.dataset.form || '';
    let removeLabel = table.dataset.removeLabel || 'Remove';

    if (!body || !crm || !form) {
        return null;
    }

    let nextIndex = 0;
    body.querySelectorAll('input[name]').forEach(function (input) {
        let match = input.name.match(/\[(\d+)\]\[(?:form|crm)\]$/);
        if (match) {
            nextIndex = Math.max(nextIndex, parseInt(match[1], 10) + 1);
        }
    });

    let optionName = 'formcourier_crm_settings';
    let baseName = optionName + '[field_mappings][' + crm + '][' + form + '][' + nextIndex + ']';
    let row = document.createElement('tr');
    let formCell = document.createElement('td');
    let crmCell = document.createElement('td');
    let actionCell = document.createElement('td');
    let formInput = document.createElement('input');
    let crmInput = document.createElement('input');
    let removeButton = document.createElement('button');

    formInput.type = 'text';
    formInput.className = 'regular-text';
    formInput.name = baseName + '[form]';

    crmInput.type = 'text';
    crmInput.className = 'regular-text';
    crmInput.name = baseName + '[crm]';

    removeButton.type = 'button';
    removeButton.className = 'button-link-delete formcourier-crm-remove-row';
    removeButton.textContent = removeLabel;

    formCell.appendChild(formInput);
    crmCell.appendChild(crmInput);
    actionCell.appendChild(removeButton);
    row.appendChild(formCell);
    row.appendChild(crmCell);
    row.appendChild(actionCell);
    body.appendChild(row);

    return formInput;
}

document.addEventListener('click', function (event) {
    let addButton = event.target.closest('.formcourier-crm-add-row');
    if (addButton) {
        let table = addButton.previousElementSibling;
        if (!table || !table.classList.contains('formcourier-crm-mapping')) {
            return;
        }
        let newInput = formcourierCrmCreateMappingRow(table);
        if (newInput) {
            newInput.focus();
        }
        return;
    }

    let removeButton = event.target.closest('.formcourier-crm-remove-row');
    if (removeButton) {
        let row = removeButton.closest('tr');
        let body = row ? row.parentElement : null;
        if (!row || !body) {
            return;
        }
        if (body.querySelectorAll('tr').length === 1) {
            row.querySelectorAll('input').forEach(function (input) {
                input.value = '';
                input.setCustomValidity('');
            });
        } else {
            row.remove();
        }
    }
});

document.addEventListener('submit', function (event) {
    let form = event.target;
    if (!(form instanceof HTMLFormElement)) {
        return;
    }

    let mappingTables = form.querySelectorAll('.formcourier-crm-mapping');
    let firstInvalid = null;

    mappingTables.forEach(function (table) {
        table.querySelectorAll('tbody tr').forEach(function (row) {
            let inputs = row.querySelectorAll('input[type="text"]');
            let formInput = inputs[0] || null;
            let crmInput = inputs[1] || null;

            if (!formInput || !crmInput) {
                return;
            }

            formInput.setCustomValidity('');
            crmInput.setCustomValidity('');

            let formValue = formInput.value.trim();
            let crmValue = crmInput.value.trim();

            if ((formValue && !crmValue) || (!formValue && crmValue)) {
                let missingInput = formValue ? crmInput : formInput;
                let validationMessage = window.formcourierCrmAdmin && window.formcourierCrmAdmin.completeMappingRow
                    ? window.formcourierCrmAdmin.completeMappingRow
                    : 'Complete both fields in this mapping row.';
                missingInput.setCustomValidity(validationMessage);
                if (!firstInvalid) {
                    firstInvalid = missingInput;
                }
            }
        });
    });

    if (firstInvalid) {
        event.preventDefault();
        firstInvalid.reportValidity();
        firstInvalid.focus();
    }
});

function formcourierCrmToggleHubSpotDealRows() {
    let select = document.getElementById('formcourier-crm-hubspot-submission-mode');
    if (!select) {
        return;
    }
    let showDealRows = select.value === 'contact_deal';
    document.querySelectorAll('[data-formcourier-crm-hubspot-deal-row]').forEach(function (row) {
        row.hidden = !showDealRows;
    });
}

document.addEventListener('DOMContentLoaded', formcourierCrmToggleHubSpotDealRows);
document.addEventListener('change', function (event) {
    if (event.target && event.target.id === 'formcourier-crm-hubspot-submission-mode') {
        formcourierCrmToggleHubSpotDealRows();
    }
});
