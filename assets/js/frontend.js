/**
 * Frontend JavaScript for Volunteer Exchange Platform
 * Uses vanilla JavaScript (no jQuery)
 */

(function() {
    'use strict';

    function t(key, fallback) {
        if (typeof vepFrontend === 'undefined' || !vepFrontend.i18n) return fallback;
        return vepFrontend.i18n[key] || fallback;
    }
    
    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
        initEventCountdowns();
        initRegistrationForms();
        initUpdateParticipantForms();
        initAgreementForm();
        initAgreementsTableFilter();
        initParticipantsGridFilter();
        initChoices();
    });

    function initEventCountdowns() {
        const countdowns = Array.from(document.querySelectorAll('.vep-event-countdown[data-start-timestamp]'));
        if (countdowns.length === 0) return;

        countdowns.forEach(function(countdown) {
            const startTimestamp = Number(countdown.getAttribute('data-start-timestamp') || 0);
            const endTimestamp = Number(countdown.getAttribute('data-end-timestamp') || 0);
            const inProgressText = String(countdown.getAttribute('data-in-progress-text') || '');
            const eventOverText = String(countdown.getAttribute('data-event-over-text') || '');
            const unitMode = String(countdown.getAttribute('data-unit-mode') || 'months').toLowerCase();
            const includeMonths = unitMode !== 'days';
            const timer = countdown.querySelector('.vep-event-countdown-timer');
            const status = countdown.querySelector('.vep-event-countdown-status');
            const units = {
                months: countdown.querySelector('[data-unit="months"]'),
                days: countdown.querySelector('[data-unit="days"]'),
                hours: countdown.querySelector('[data-unit="hours"]'),
                minutes: countdown.querySelector('[data-unit="minutes"]'),
                seconds: countdown.querySelector('[data-unit="seconds"]')
            };

            if (!startTimestamp || !timer || !status) return;

            function addUtcMonths(date, monthsToAdd) {
                const result = new Date(date.getTime());
                const dayOfMonth = result.getUTCDate();

                result.setUTCDate(1);
                result.setUTCMonth(result.getUTCMonth() + monthsToAdd);

                const lastDayOfTargetMonth = new Date(Date.UTC(
                    result.getUTCFullYear(),
                    result.getUTCMonth() + 1,
                    0
                )).getUTCDate();

                result.setUTCDate(Math.min(dayOfMonth, lastDayOfTargetMonth));
                return result;
            }

            function getCountdownParts(nowDate, targetDate, withMonths) {
                if (targetDate.getTime() <= nowDate.getTime()) {
                    return {
                        months: 0,
                        days: 0,
                        hours: 0,
                        minutes: 0,
                        seconds: 0
                    };
                }

                let months = 0;
                let remainingMs = Math.max(0, targetDate.getTime() - nowDate.getTime());
                const dayMs = 24 * 60 * 60 * 1000;
                const hourMs = 60 * 60 * 1000;
                const minuteMs = 60 * 1000;
                const secondMs = 1000;

                if (withMonths) {
                    months = ((targetDate.getUTCFullYear() - nowDate.getUTCFullYear()) * 12)
                        + (targetDate.getUTCMonth() - nowDate.getUTCMonth());

                    let anchorDate = addUtcMonths(nowDate, months);
                    if (anchorDate.getTime() > targetDate.getTime()) {
                        months -= 1;
                        anchorDate = addUtcMonths(nowDate, months);
                    }

                    remainingMs = Math.max(0, targetDate.getTime() - anchorDate.getTime());
                }

                const days = Math.floor(remainingMs / dayMs);
                remainingMs -= days * dayMs;

                const hours = Math.floor(remainingMs / hourMs);
                remainingMs -= hours * hourMs;

                const minutes = Math.floor(remainingMs / minuteMs);
                remainingMs -= minutes * minuteMs;

                const seconds = Math.floor(remainingMs / secondMs);

                return {
                    months: Math.max(0, months),
                    days: Math.max(0, days),
                    hours: Math.max(0, hours),
                    minutes: Math.max(0, minutes),
                    seconds: Math.max(0, seconds)
                };
            }

            function getVisibleUnits(parts, withMonths) {
                if (!withMonths) {
                    if (parts.days > 0) {
                        return ['days', 'hours', 'minutes', 'seconds'];
                    }

                    if (parts.hours > 0) {
                        return ['hours', 'minutes', 'seconds'];
                    }

                    if (parts.minutes > 0) {
                        return ['minutes', 'seconds'];
                    }

                    return ['seconds'];
                }

                if (parts.months > 0) {
                    return ['months', 'days', 'hours', 'minutes', 'seconds'];
                }

                if (parts.days > 0) {
                    return ['days', 'hours', 'minutes', 'seconds'];
                }

                if (parts.hours > 0) {
                    return ['hours', 'minutes', 'seconds'];
                }

                if (parts.minutes > 0) {
                    return ['minutes', 'seconds'];
                }

                return ['seconds'];
            }

            function renderTimer(parts) {
                const visibleUnits = getVisibleUnits(parts, includeMonths);

                Object.keys(units).forEach(function(unitName) {
                    const unitElement = units[unitName];
                    if (!unitElement) return;

                    const valueElement = unitElement.querySelector('.vep-event-countdown-value');
                    if (valueElement) {
                        valueElement.textContent = String(parts[unitName]);
                    }

                    unitElement.style.display = visibleUnits.indexOf(unitName) !== -1 ? '' : 'none';
                });

                timer.style.display = '';
                status.style.display = 'none';
                status.textContent = '';
            }

            function renderStatus(message) {
                timer.style.display = 'none';
                status.textContent = message;
                status.style.display = 'block';
            }

            function tick() {
                const now = new Date();
                const startDate = new Date(startTimestamp * 1000);
                const endDate = endTimestamp ? new Date(endTimestamp * 1000) : null;

                if (endDate && now.getTime() >= endDate.getTime()) {
                    renderStatus(eventOverText);
                    return;
                }

                if (now.getTime() >= startDate.getTime()) {
                    renderStatus(inProgressText);
                    return;
                }

                renderTimer(getCountdownParts(now, startDate, includeMonths));
            }

            tick();
            window.setInterval(tick, 1000);
        });
    }

    function initAgreementsTableFilter() {
        const lists = Array.from(document.querySelectorAll('.vep-agreements-list'));
        if (lists.length === 0) return;

        lists.forEach(function(list) {
            const searchInput = list.querySelector('.vep-agreements-search-input');
            const rows = Array.from(list.querySelectorAll('.vep-agreements-table tbody tr'));

            if (!searchInput || rows.length === 0) return;

            function applyTableFilter() {
                const query = String(searchInput.value || '').toLowerCase().trim();

                rows.forEach(function(row) {
                    const rowText = String(row.textContent || '').toLowerCase();
                    const matches = query === '' || rowText.includes(query);
                    row.style.display = matches ? '' : 'none';
                });
            }

            searchInput.addEventListener('input', applyTableFilter);
            applyTableFilter();
        });
    }

    function initParticipantsGridFilter() {
        const wrappers = Array.from(document.querySelectorAll('.vep-participants-grid'));
        if (wrappers.length === 0) return;

        wrappers.forEach(function(gridWrapper) {
            if (!gridWrapper) return;

            const gridItems = Array.from(gridWrapper.querySelectorAll('.vep-grid-item'));
            if (gridItems.length === 0) return;

            const tagFilterButtons = Array.from(gridWrapper.querySelectorAll('.vep-grid-tag-filter-button[data-tag-filter]'));
            const typeFilterButtons = Array.from(gridWrapper.querySelectorAll('.vep-grid-type-filter-button[data-type-filter]'));
            const legacySelect = gridWrapper.querySelector('.vep-grid-tag-filter');
            const searchInput = gridWrapper.querySelector('.vep-grid-search-input');

            function getActiveFilterValue(buttons, attributeName) {
                const activeButton = buttons.find(function(button) {
                    return button.classList.contains('is-active');
                });
                return activeButton ? String(activeButton.getAttribute(attributeName) || '').trim() : '';
            }

            function applyFilter(selectedTagId, selectedTypeId, searchTerm) {
                const selectedTag = String(selectedTagId || '').trim();
                const selectedType = String(selectedTypeId || '').trim();
                const selectedSearch = String(searchTerm || '').toLowerCase().trim();

                gridItems.forEach(function(item) {
                    const tagIdsRaw = String(item.getAttribute('data-tag-ids') || '').trim();
                    const itemTagIds = tagIdsRaw ? tagIdsRaw.split(',').map(function(id) { return id.trim(); }).filter(Boolean) : [];
                    const itemType = String(item.getAttribute('data-participant-type') || '').trim();
                    const itemText = String(item.textContent || '').toLowerCase();

                    const matchesTag = selectedTag === '' || itemTagIds.includes(selectedTag);
                    const matchesType = selectedType === '' || itemType === selectedType;
                    const matchesSearch = selectedSearch === '' || itemText.includes(selectedSearch);
                    item.style.display = (matchesTag && matchesType && matchesSearch) ? '' : 'none';
                });
            }

            function setupButtonGroup(buttons) {
                if (buttons.length === 0) {
                    return;
                }

                buttons.forEach(function(button) {
                    button.addEventListener('click', function() {
                        buttons.forEach(function(btn) {
                            btn.classList.toggle('is-active', btn === button);
                        });

                        applyFilter(
                            getActiveFilterValue(tagFilterButtons, 'data-tag-filter'),
                            getActiveFilterValue(typeFilterButtons, 'data-type-filter'),
                            searchInput ? searchInput.value : ''
                        );
                    });
                });

                const initiallyActiveButton = buttons.find(function(button) {
                    return button.classList.contains('is-active');
                }) || buttons[0];

                if (initiallyActiveButton) {
                    buttons.forEach(function(btn) {
                        btn.classList.toggle('is-active', btn === initiallyActiveButton);
                    });
                }
            }

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    applyFilter(
                        getActiveFilterValue(tagFilterButtons, 'data-tag-filter'),
                        getActiveFilterValue(typeFilterButtons, 'data-type-filter'),
                        searchInput.value
                    );
                });
            }

            setupButtonGroup(tagFilterButtons);
            setupButtonGroup(typeFilterButtons);

            if (tagFilterButtons.length > 0 || typeFilterButtons.length > 0 || searchInput) {
                applyFilter(
                    getActiveFilterValue(tagFilterButtons, 'data-tag-filter'),
                    getActiveFilterValue(typeFilterButtons, 'data-type-filter'),
                    searchInput ? searchInput.value : ''
                );
            }

            if (!legacySelect) return;

            legacySelect.addEventListener('change', function() {
                applyFilter(legacySelect.value || '', '', searchInput ? searchInput.value : '');
            });
            applyFilter(legacySelect.value || '', '', searchInput ? searchInput.value : '');
        });
    }

    function getRegistrationMessageDiv(form) {
        if (!form) return null;
        return form.querySelector('.vep-registration-message') || form.querySelector('#vep-registration-message');
    }

    function initRegistrationForms() {
        const forms = Array.from(document.querySelectorAll('#vep-registration-form'));
        if (forms.length === 0) return;

        forms.forEach(function(form) {
            initRegistrationMultiStep(form);
            initRegistrationForm(form);
        });
    }

    function getUpdateParticipantMessageDiv(form) {
        if (!form) return null;
        return form.querySelector('.vep-update-participant-message');
    }

    function initUpdateParticipantForms() {
        const forms = Array.from(document.querySelectorAll('#vep-update-participant-form'));
        if (forms.length === 0) return;

        forms.forEach(function(form) {
            initUpdateParticipantForm(form);
        });
    }

    function initUpdateParticipantForm(form) {
        if (!form) return;

        const participantSelect = form.querySelector('#update_participant_id');
        const fieldsContainer = form.querySelector('.vep-update-participant-fields');
        const eventIdInput = form.querySelector('input[name="event_id"]');
        const organizationNameInput = form.querySelector('#update_organization_name');
        const participantTypeSelect = form.querySelector('#update_participant_type_id');
        const contactPersonInput = form.querySelector('#update_contact_person_name');
        const contactEmailInput = form.querySelector('#update_contact_email');
        const contactPhoneInput = form.querySelector('#update_contact_phone');
        const descriptionInput = form.querySelector('#update_description');
        const expectedCountInput = form.querySelector('#update_expected_participants_count');
        const expectedNamesInput = form.querySelector('#update_expected_participants_names');
        const tagsInputs = Array.from(form.querySelectorAll('input[type="checkbox"][name="tags[]"]'));
        const logoWrapper = form.querySelector('.vep-current-logo-wrapper');
        const logoPreviewImage = form.querySelector('.vep-current-logo-preview img');
        const logoRemoveBtn = form.querySelector('.vep-logo-remove-btn');
        const removeLogoInput = form.querySelector('#update_remove_logo');
        const logoFileInput = form.querySelector('#update_logo');
        const defaultLogoSrc = logoPreviewImage ? String(logoPreviewImage.getAttribute('src') || '') : '';
        let previewObjectUrl = null;

        function releasePreviewObjectUrl() {
            if (!previewObjectUrl) return;
            URL.revokeObjectURL(previewObjectUrl);
            previewObjectUrl = null;
        }

        function showLogoPreview(src) {
            if (!logoWrapper || !logoPreviewImage) return;
            logoPreviewImage.src = src;
            logoWrapper.classList.remove('is-hidden');
        }

        function hideLogoPreview() {
            if (!logoWrapper || !logoPreviewImage) return;
            logoPreviewImage.removeAttribute('src');
            logoWrapper.classList.add('is-hidden');
        }

        function setSelectValue(selectElement, value) {
            if (!selectElement) return;

            const normalizedValue = (value === null || typeof value === 'undefined') ? '' : String(value);
            if (selectElement.choicesInstance) {
                selectElement.choicesInstance.setChoiceByValue(normalizedValue);
            } else {
                selectElement.value = normalizedValue;
            }
        }

        function toggleUpdateFields() {
            if (!fieldsContainer || !participantSelect) return;
            fieldsContainer.style.display = participantSelect.value ? '' : 'none';
        }

        function clearUpdateFields() {
            if (organizationNameInput) organizationNameInput.value = '';
            setSelectValue(participantTypeSelect, '');
            if (contactPersonInput) contactPersonInput.value = '';
            if (contactEmailInput) contactEmailInput.value = '';
            if (contactPhoneInput) contactPhoneInput.value = '';
            if (descriptionInput) descriptionInput.value = '';
            if (expectedCountInput) expectedCountInput.value = '';
            if (expectedNamesInput) expectedNamesInput.value = '';
            tagsInputs.forEach(function(input) {
                input.checked = false;
            });
        }

        function setLoadingState(isLoading) {
            if (!participantSelect) return;
            participantSelect.disabled = isLoading;
            if (fieldsContainer) {
                fieldsContainer.style.opacity = isLoading ? '0.6' : '';
                fieldsContainer.style.pointerEvents = isLoading ? 'none' : '';
            }
        }

        function populateUpdateFields(participant) {
            if (organizationNameInput) {
                organizationNameInput.value = participant && participant.organization_name ? participant.organization_name : '';
            }

            setSelectValue(participantTypeSelect, participant && participant.participant_type_id ? participant.participant_type_id : '');

            if (contactPersonInput) {
                contactPersonInput.value = participant && participant.contact_person_name ? participant.contact_person_name : '';
            }

            if (contactEmailInput) {
                contactEmailInput.value = participant && participant.contact_email ? participant.contact_email : '';
            }

            if (contactPhoneInput) {
                contactPhoneInput.value = participant && participant.contact_phone ? participant.contact_phone : '';
            }

            if (descriptionInput) {
                descriptionInput.value = participant && participant.description ? participant.description : '';
            }

            if (expectedCountInput) {
                const count = participant ? participant.expected_participants_count : '';
                expectedCountInput.value = (count === null || typeof count === 'undefined' || count === '') ? '' : String(count);
            }

            if (expectedNamesInput) {
                expectedNamesInput.value = participant && participant.expected_participants_names ? participant.expected_participants_names : '';
            }

            const selectedTagIds = new Set(
                participant && Array.isArray(participant.tag_ids)
                    ? participant.tag_ids.map(function(tagId) { return String(tagId); })
                    : []
            );

            tagsInputs.forEach(function(input) {
                input.checked = selectedTagIds.has(input.value);
            });
        }

        function loadParticipantData() {
            if (!participantSelect || !eventIdInput) return;

            const participantId = participantSelect.value;
            if (!participantId) {
                clearUpdateFields();
                toggleUpdateFields();
                return;
            }

            clearUpdateFields();
            toggleUpdateFields();
            setLoadingState(true);

            const requestBody = new FormData();
            requestBody.append('action', 'vep_get_participant_for_update');
            requestBody.append('nonce', vepFrontend.nonce);
            requestBody.append('participant_id', participantId);
            requestBody.append('event_id', eventIdInput.value || '');

            fetch(vepFrontend.ajaxUrl, {
                method: 'POST',
                body: requestBody
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success || !data.data || !data.data.participant) {
                    const messageDiv = getUpdateParticipantMessageDiv(form);
                    if (messageDiv) {
                        messageDiv.className = 'vep-message vep-error vep-update-participant-message';
                        messageDiv.textContent = (data.data && data.data.message) ? data.data.message : t('genericError', 'An error occurred. Please try again.');
                        messageDiv.style.display = 'block';
                    }
                    setSelectValue(participantSelect, '');
                    toggleUpdateFields();
                    return;
                }

                const messageDiv = getUpdateParticipantMessageDiv(form);
                if (messageDiv) {
                    messageDiv.style.display = 'none';
                }

                populateUpdateFields(data.data.participant);
                toggleUpdateFields();
            })
            .catch(() => {
                const messageDiv = getUpdateParticipantMessageDiv(form);
                if (messageDiv) {
                    messageDiv.className = 'vep-message vep-error vep-update-participant-message';
                    messageDiv.textContent = t('genericError', 'An error occurred. Please try again.');
                    messageDiv.style.display = 'block';
                }
                setSelectValue(participantSelect, '');
                toggleUpdateFields();
            })
            .finally(() => {
                setLoadingState(false);
            });
        }

        if (participantSelect) {
            participantSelect.addEventListener('change', loadParticipantData);
            toggleUpdateFields();
        }

        if (logoRemoveBtn && logoWrapper && removeLogoInput) {
            logoRemoveBtn.addEventListener('click', function() {
                releasePreviewObjectUrl();
                if (logoFileInput) {
                    logoFileInput.value = '';
                }
                hideLogoPreview();
                removeLogoInput.value = '1';
            });
        }

        if (logoFileInput && removeLogoInput) {
            logoFileInput.addEventListener('change', function() {
                if (logoFileInput.files && logoFileInput.files.length > 0) {
                    releasePreviewObjectUrl();
                    previewObjectUrl = URL.createObjectURL(logoFileInput.files[0]);
                    showLogoPreview(previewObjectUrl);
                    removeLogoInput.value = '0';
                } else {
                    releasePreviewObjectUrl();
                    if (defaultLogoSrc) {
                        showLogoPreview(defaultLogoSrc);
                        removeLogoInput.value = '0';
                    } else {
                        hideLogoPreview();
                    }
                }
            });
        }

        form.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(form);
            formData.append('action', 'vep_update_participant');
            formData.append('nonce', vepFrontend.nonce);

            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton ? submitButton.textContent : '';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = t('submitting', 'Submitting...');
            }

            fetch(vepFrontend.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const messageDiv = getUpdateParticipantMessageDiv(form);
                if (!messageDiv) {
                    return;
                }

                if (data.success) {
                    messageDiv.className = 'vep-message vep-success vep-update-participant-message';
                    messageDiv.textContent = data.data.message;
                    messageDiv.style.display = 'block';

                    if (participantSelect) {
                        form.reset();

                        const choicesElements = form.querySelectorAll('.vep-choices');
                        choicesElements.forEach(element => {
                            if (element.choicesInstance) {
                                element.choicesInstance.setChoiceByValue('');
                            }
                        });

                        clearUpdateFields();
                        toggleUpdateFields();
                    }

                    messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    messageDiv.className = 'vep-message vep-error vep-update-participant-message';
                    messageDiv.textContent = data.data.message || t('genericError', 'An error occurred. Please try again.');
                    messageDiv.style.display = 'block';
                }

                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const messageDiv = getUpdateParticipantMessageDiv(form);
                if (messageDiv) {
                    messageDiv.className = 'vep-message vep-error vep-update-participant-message';
                    messageDiv.textContent = t('genericError', 'An error occurred. Please try again.');
                    messageDiv.style.display = 'block';
                }

                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }
            });
        });
    }

    /**
     * Initialize multistep registration form (if enabled via shortcode)
     */
    function initRegistrationMultiStep(form) {
        if (!form) return;
        if (!form.classList.contains('vep-registration-multistep')) return;

        const steps = Array.from(form.querySelectorAll('.vep-step[data-vep-step]'));
        if (steps.length === 0) return;

        const dots = Array.from(form.querySelectorAll('.vep-steps-indicator .vep-step-dot'));
        let currentIndex = steps.findIndex(s => s.classList.contains('is-active'));
        if (currentIndex < 0) currentIndex = 0;

        function setStep(index) {
            steps.forEach((step, i) => {
                const active = i === index;
                step.classList.toggle('is-active', active);
                step.setAttribute('aria-hidden', active ? 'false' : 'true');
            });
            dots.forEach((dot, i) => dot.classList.toggle('is-active', i === index));
            currentIndex = index;
            form.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Expose for other handlers (e.g., reset after submit)
        form.vepSetStep = setStep;

        function validateCurrentStep() {
            const step = steps[currentIndex];
            const messageDiv = getRegistrationMessageDiv(form);
            if (messageDiv) {
                messageDiv.style.display = 'none';
                messageDiv.textContent = '';
            }

            // Step with checkbox group: require at least one selection
            const tagCheckboxes = Array.from(step.querySelectorAll('input[type="checkbox"][name="tags[]"]'));
            if (tagCheckboxes.length > 0) {
                const hasChecked = tagCheckboxes.some(cb => cb.checked);
                if (!hasChecked) {
                    if (messageDiv) {
                        messageDiv.className = 'vep-message vep-error';
                        messageDiv.textContent = t('selectAtLeastOne', 'Please select at least one option.');
                        messageDiv.style.display = 'block';
                        messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                    return false;
                }
            }

            const fields = Array.from(step.querySelectorAll('input, select, textarea'));
            for (const field of fields) {
                if (typeof field.checkValidity === 'function' && !field.checkValidity()) {
                    if (typeof field.reportValidity === 'function') {
                        field.reportValidity();
                    }
                    return false;
                }
            }
            return true;
        }

        form.addEventListener('click', function(e) {
            const nextBtn = e.target.closest('[data-vep-next]');
            const prevBtn = e.target.closest('[data-vep-prev]');

            if (nextBtn) {
                e.preventDefault();
                if (!validateCurrentStep()) return;
                if (currentIndex < steps.length - 1) {
                    setStep(currentIndex + 1);
                }
            }

            if (prevBtn) {
                e.preventDefault();
                if (currentIndex > 0) {
                    setStep(currentIndex - 1);
                }
            }
        });

        // Ensure initial state is correct
        setStep(currentIndex);
    }
    
    /**
     * Initialize registration form
     */
    function initRegistrationForm(form) {
        if (!form) return;
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();

            // Multistep: ensure at least one tag is selected before submit
            if (form.classList.contains('vep-registration-multistep')) {
                const tagCheckboxes = Array.from(form.querySelectorAll('input[type="checkbox"][name="tags[]"]'));
                if (tagCheckboxes.length > 0) {
                    const hasChecked = tagCheckboxes.some(cb => cb.checked);
                    if (!hasChecked) {
                        const messageDiv = getRegistrationMessageDiv(form);
                        if (messageDiv) {
                            messageDiv.className = 'vep-message vep-error';
                            messageDiv.textContent = t('selectAtLeastOne', 'Please select at least one option.');
                            messageDiv.style.display = 'block';
                            messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                        }
                        return;
                    }
                }
            }
            
            const formData = new FormData(form);
            formData.append('action', 'vep_register_participant');
            formData.append('nonce', vepFrontend.nonce);
            
            // Disable submit button
            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton ? submitButton.textContent : '';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = t('submitting', 'Submitting...');
            }
            
            // Send AJAX request
            fetch(vepFrontend.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                const messageDiv = getRegistrationMessageDiv(form);
                if (!messageDiv) {
                    return;
                }
                
                if (data.success) {
                    messageDiv.className = 'vep-message vep-success';
                    messageDiv.textContent = data.data.message;
                    messageDiv.style.display = 'block';
                    
                    // Reset form
                    form.reset();

                    // Reset multistep UI back to the first step
                    if (typeof form.vepSetStep === 'function') {
                        form.vepSetStep(0);
                    }
                    
                    // Scroll to message
                    messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    messageDiv.className = 'vep-message vep-error';
                    messageDiv.textContent = data.data.message || t('genericError', 'An error occurred. Please try again.');
                    messageDiv.style.display = 'block';
                }
                
                // Re-enable submit button
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                const messageDiv = getRegistrationMessageDiv(form);
                if (messageDiv) {
                    messageDiv.className = 'vep-message vep-error';
                    messageDiv.textContent = t('genericError', 'An error occurred. Please try again.');
                    messageDiv.style.display = 'block';
                }
                
                // Re-enable submit button
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = originalText;
                }
            });
        });
    }
    
    /**
     * Initialize agreement form
     */
    function initAgreementForm() {
        const form = document.getElementById('vep-agreement-form');
        if (!form) return;

        const participant1Select = form.querySelector('#participant1_id');
        const participant2Select = form.querySelector('#participant2_id');
        const clearPresetButton = document.getElementById('vep-clear-participant1-preselection');
        const preferenceModal = document.getElementById('vep-participant-preference-modal');
        const preferenceOption1 = document.getElementById('vep-participant-preference-option-1');
        const preferenceOption2 = document.getElementById('vep-participant-preference-option-2');
        const storageKey = 'vepPreferredAgreementParticipantId';

        function getStoredParticipantId() {
            try {
                return localStorage.getItem(storageKey) || '';
            } catch (err) {
                return '';
            }
        }

        function setStoredParticipantId(participantId) {
            try {
                localStorage.setItem(storageKey, String(participantId));
            } catch (err) {
                // Ignore storage errors and continue without persistence.
            }
        }

        function clearStoredParticipantId() {
            try {
                localStorage.removeItem(storageKey);
            } catch (err) {
                // Ignore storage errors and continue without persistence.
            }
        }

        function setSelectValue(selectElement, value) {
            if (!selectElement) return;
            const normalizedValue = value ? String(value) : '';

            if (selectElement.choicesInstance) {
                selectElement.choicesInstance.setChoiceByValue(normalizedValue);
            } else {
                selectElement.value = normalizedValue;
            }
        }

        function hasOptionValue(selectElement, value) {
            if (!selectElement) return false;
            return Array.from(selectElement.options).some(function(option) {
                return String(option.value) === String(value);
            });
        }

        function updateClearPresetVisibility() {
            if (!clearPresetButton) return;
            clearPresetButton.style.display = getStoredParticipantId() ? 'inline-flex' : 'none';
        }

        function applyStoredParticipantPreference() {
            const storedId = getStoredParticipantId();
            if (!storedId) {
                updateClearPresetVisibility();
                return;
            }

            if (!hasOptionValue(participant1Select, storedId)) {
                clearStoredParticipantId();
                updateClearPresetVisibility();
                return;
            }

            setSelectValue(participant1Select, storedId);
            updateClearPresetVisibility();
        }

        function getSelectedParticipant(selectElement) {
            if (!selectElement || !selectElement.value) return null;
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            if (!selectedOption) return null;

            return {
                id: String(selectElement.value),
                label: String(selectedOption.textContent || '').trim()
            };
        }

        function openParticipantPreferenceModal(participantA, participantB) {
            if (!preferenceModal || !preferenceOption1 || !preferenceOption2) {
                return;
            }

            preferenceOption1.textContent = participantA.label;
            preferenceOption2.textContent = participantB.label;

            preferenceOption1.onclick = function() {
                setStoredParticipantId(participantA.id);
                preferenceModal.style.display = 'none';
                preferenceModal.setAttribute('aria-hidden', 'true');
                applyStoredParticipantPreference();
            };

            preferenceOption2.onclick = function() {
                setStoredParticipantId(participantB.id);
                preferenceModal.style.display = 'none';
                preferenceModal.setAttribute('aria-hidden', 'true');
                applyStoredParticipantPreference();
            };

            preferenceModal.style.display = 'flex';
            preferenceModal.setAttribute('aria-hidden', 'false');
        }

        if (clearPresetButton) {
            clearPresetButton.addEventListener('click', function(e) {
                e.preventDefault();
                clearStoredParticipantId();
                setSelectValue(participant1Select, '');
                updateClearPresetVisibility();
            });
        }

        applyStoredParticipantPreference();
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(form);
            
            // Convert FormData to URLSearchParams for easier handling
            const params = new URLSearchParams();
            for (const [key, value] of formData.entries()) {
                params.append(key, value);
            }
            params.append('action', 'vep_create_agreement');
            params.append('nonce', vepFrontend.nonce);
            
            // Disable submit button
            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = t('creating', 'Creating...');
            
            // Send AJAX request
            fetch(vepFrontend.ajaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: params
            })
            .then(response => response.json())
            .then(data => {
                const messageDiv = document.getElementById('vep-agreement-message');
                
                if (data.success) {
                    const selectedParticipant1 = getSelectedParticipant(participant1Select);
                    const selectedParticipant2 = getSelectedParticipant(participant2Select);

                    messageDiv.className = 'vep-message vep-success';
                    messageDiv.textContent = data.data.message;
                    messageDiv.style.display = 'block';

                    if (!getStoredParticipantId() && selectedParticipant1 && selectedParticipant2) {
                        openParticipantPreferenceModal(selectedParticipant1, selectedParticipant2);
                    }
                    
                    // Reset form
                    form.reset();
                    
                    // Reset Choices.js instances
                    const choicesElements = form.querySelectorAll('.vep-choices');
                    choicesElements.forEach(element => {
                        if (element.choicesInstance) {
                            element.choicesInstance.setChoiceByValue('');
                        }
                    });

                    applyStoredParticipantPreference();
                    
                    // Scroll to message
                    messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    messageDiv.className = 'vep-message vep-error';
                    messageDiv.textContent = data.data.message || t('genericError', 'An error occurred. Please try again.');
                    messageDiv.style.display = 'block';
                }
                
                // Re-enable submit button
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            })
            .catch(error => {
                console.error('Error:', error);
                const messageDiv = document.getElementById('vep-agreement-message');
                messageDiv.className = 'vep-message vep-error';
                messageDiv.textContent = t('genericError', 'An error occurred. Please try again.');
                messageDiv.style.display = 'block';
                
                // Re-enable submit button
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            });
        });
    }
    
    /**
     * Initialize Choices.js for select elements
     */
    function initChoices() {
        if (typeof Choices === 'undefined') return;

        const i18n = (typeof vepFrontend !== 'undefined' && vepFrontend.choicesI18n) ? vepFrontend.choicesI18n : {};
        
        const selectElements = document.querySelectorAll('.vep-choices');
        
        selectElements.forEach(function(element) {
            const choices = new Choices(element, {
                searchEnabled: true,
                searchFields: ['label'],
                searchPlaceholderValue: i18n.searchPlaceholderValue || 'Search...',
                itemSelectText: i18n.itemSelectText || 'Press to select',
                noResultsText: i18n.noResultsText || 'No results found',
                noChoicesText: i18n.noChoicesText || 'No options available',
                removeItemButton: false,
                shouldSort: false
            });
            
            // Store instance for later use
            element.choicesInstance = choices;
        });
    }
})();
