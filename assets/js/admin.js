/**
 * Toast notification utility — available globally as window.vepToast(message, type).
 * Types: 'success' (default), 'error', 'warning'.
 */
(function () {
    'use strict';
    var container = null;

    function getContainer() {
        if (!container) {
            container = document.createElement('div');
            container.id = 'vep-toast-container';
            document.body.appendChild(container);
        }
        return container;
    }

    window.vepToast = function (message, type) {
        var c = getContainer();
        var toast = document.createElement('div');
        toast.className = 'vep-toast vep-toast--' + (type || 'success');
        toast.textContent = message;
        c.appendChild(toast);
        requestAnimationFrame(function () { toast.classList.add('vep-toast--visible'); });
        setTimeout(function () {
            toast.classList.remove('vep-toast--visible');
            setTimeout(function () {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 300);
        }, 3500);
    };
}());

/**
 * AJAX for event participants list table.
 * Self-contained IIFE — no global pollution.
 */
(function () {
    'use strict';

    // Event ID read from the URL — null on pages that aren't event-view.
    var eventId = null;

    // Current navigation state, kept in sync across reloads.
    var state = {
        per_page:        '25',
        paged:           '1',
        approval_status: '',
        orderby:         'created_at',
        order:           'desc'
    };

    function showSpinner() {
        var section = document.getElementById('vep-event-participants-section');
        if (!section) return;
        var overlay = section.querySelector('.vep-ajax-spinner-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'vep-ajax-spinner-overlay';
            overlay.innerHTML = '<div class="vep-ajax-spinner"></div>';
            section.style.position = 'relative';
            section.appendChild(overlay);
        }
        overlay.style.display = 'flex';
    }

    function hideSpinner() {
        var overlay = document.querySelector('#vep-event-participants-section .vep-ajax-spinner-overlay');
        if (overlay) overlay.style.display = 'none';
    }

    /**
     * Reload the participants section via AJAX.
     * @param {Object} overrides  Key/value pairs to merge into state.
     *                            Special keys: participant_action, participant_id,
     *                            bulk_action, participants (array), _wpnonce.
     */
    function reload(overrides) {
        if (!eventId || typeof vepAdmin === 'undefined') return;

        // Separate navigation-state keys from action keys.
        var actionKeys = ['participant_action', 'participant_id', 'bulk_action', 'participants', '_wpnonce'];
        var extra = {};

        if (overrides) {
            Object.keys(overrides).forEach(function (k) {
                if (actionKeys.indexOf(k) !== -1) {
                    extra[k] = overrides[k];
                } else {
                    state[k] = overrides[k];
                }
            });
            // Reset to page 1 when the filter or rows-per-page changes.
            if (overrides.approval_status !== undefined || overrides.per_page !== undefined) {
                state.paged = '1';
            }
        }

        showSpinner();

        var data = new URLSearchParams();
        data.append('action',   'vep_reload_event_participants_table');
        data.append('event_id', eventId);
        Object.keys(state).forEach(function (k) {
            data.append(k, state[k]);
        });
        Object.keys(extra).forEach(function (k) {
            if (k === 'participants' && Array.isArray(extra[k])) {
                // Send as comma-separated string to avoid PHP %5B%5D parsing inconsistencies.
                data.append('participants_list', extra[k].join(','));
            } else {
                data.append(k, extra[k]);
            }
        });

        fetch(vepAdmin.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                hideSpinner();
                if (!result || !result.success) {
                    if (typeof window.vepToast === 'function') {
                        window.vepToast(
                            (result && result.data) ? result.data : 'An error occurred.',
                            'error'
                        );
                    }
                    return;
                }
                if (!result.data || !result.data.html) return;
                var section = document.getElementById('vep-event-participants-section');
                if (!section) return;
                var temp = document.createElement('div');
                temp.innerHTML = result.data.html;
                var newSection = temp.querySelector('#vep-event-participants-section');
                if (newSection) {
                    section.parentNode.replaceChild(newSection, section);
                }
                if (result.data.message && typeof window.vepToast === 'function') {
                    window.vepToast(result.data.message);
                }
                // Re-attach listeners to the freshly inserted DOM.
                init();
            })
            .catch(function () { hideSpinner(); });
    }

    /** Attach all event listeners to the current section. */
    function init() {
        var section = document.getElementById('vep-event-participants-section');
        if (!section) return;

        // Block ALL native form navigation — we handle everything via AJAX.
        var form = section.querySelector('#vep-event-participants-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                return false;
            });
        }

        // Rows-per-page selector.
        section.querySelectorAll('.vep-per-page-select').forEach(function (sel) {
            sel.addEventListener('change', function () {
                reload({ per_page: this.value });
            });
        });

        // Pagination links (WP outputs these inside .tablenav-pages).
        section.querySelectorAll('.tablenav-pages a, .pagination-links a').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var url = new URL(this.href);
                reload({ paged: url.searchParams.get('paged') || '1' });
            });
        });

        // Filter/status view links (All / Pending Approval / Approved).
        section.querySelectorAll('.subsubsub a').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var url = new URL(this.href);
                reload({ approval_status: url.searchParams.get('approval_status') || '', paged: '1' });
            });
        });

        // Column sort links.
        section.querySelectorAll('th.sortable a, th.sorted a').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var url = new URL(this.href);
                reload({
                    orderby: url.searchParams.get('orderby') || 'created_at',
                    order:   url.searchParams.get('order')   || 'desc',
                    paged:   '1'
                });
            });
        });

        // Bulk-action Apply buttons (id="doaction" and id="doaction2").
        section.querySelectorAll('input[id^="doaction"]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                // Read from the select that belongs to THIS button (top → participant_bulk_action, bottom → participant_bulk_action2).
                var selectName = (this.id === 'doaction') ? 'participant_bulk_action' : 'participant_bulk_action2';
                var bulkSelect = section.querySelector('select[name="' + selectName + '"]');
                var bulk_action = bulkSelect ? bulkSelect.value : '-1';
                var selected = Array.from(section.querySelectorAll('input[name="participant[]"]:checked'))
                                    .map(function (cb) { return cb.value; });
                if (bulk_action === '-1') return;
                if (selected.length === 0) return;
                if (bulk_action === 'bulk_delete') {
                    var confirmMsg = (typeof vepAdmin !== 'undefined' && vepAdmin.i18n && vepAdmin.i18n.confirmBulkDelete)
                        ? vepAdmin.i18n.confirmBulkDelete
                        : 'Er du sikker på, at du vil slette de valgte deltagere?';
                    if (!window.confirm(confirmMsg)) return;
                }
                var nonceInput = section.querySelector('input[name="_wpnonce"]');
                reload({
                    bulk_action:  bulk_action,
                    participants: selected,
                    _wpnonce:     nonceInput ? nonceInput.value : ''
                });
            });
        });

        // Approve / Unapprove inline links.
        section.querySelectorAll('a[href*="participant_action=approve"], a[href*="participant_action=unapprove"]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var url = new URL(this.href);
                reload({
                    participant_action: url.searchParams.get('participant_action'),
                    participant_id:     url.searchParams.get('participant_id'),
                    _wpnonce:           url.searchParams.get('_wpnonce')
                });
            });
        });

        // Delete inline links — keep the confirm dialog, then AJAX.
        section.querySelectorAll('a[href*="participant_action=delete"]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                if (!window.confirm(link.dataset.confirmMsg || 'Are you sure?')) return;
                var url = new URL(this.href);
                reload({
                    participant_action: 'delete',
                    participant_id:     url.searchParams.get('participant_id'),
                    _wpnonce:           url.searchParams.get('_wpnonce')
                });
            });
        });

        // Edit participant via modal.
        section.querySelectorAll('a[data-participant-edit-modal]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var id = parseInt(this.dataset.participantEditModal, 10);
                if (typeof window.vepOpenParticipantModal === 'function') {
                    window.vepOpenParticipantModal(id);
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var q = new URLSearchParams(window.location.search);
        // Only activate on the event-view admin page.
        if (q.get('page') !== 'volunteer-exchange-events' || q.get('action') !== 'view' || !q.get('id')) return;
        eventId = q.get('id');
        // Seed state from the current page URL so the initial view is preserved.
        if (q.get('per_page'))        state.per_page        = q.get('per_page');
        if (q.get('paged'))           state.paged           = q.get('paged');
        if (q.get('approval_status')) state.approval_status = q.get('approval_status');
        if (q.get('orderby'))         state.orderby         = q.get('orderby');
        if (q.get('order'))           state.order           = q.get('order');
        init();
        document.addEventListener('vep:reload-participants', function () { reload(); });
    });

}());
/**
 * AJAX for event agreements list table.
 * Self-contained IIFE — no global pollution.
 */
(function () {
    'use strict';

    var eventId = null;

    var state = {
        per_page:         '25',
        paged:            '1',
        agreement_status: '',
        orderby:          'id',
        order:            'desc',
        s:                ''
    };

    function showSpinner() {
        var section = document.getElementById('vep-event-agreements-section');
        if (!section) return;
        var overlay = section.querySelector('.vep-ajax-spinner-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'vep-ajax-spinner-overlay';
            overlay.innerHTML = '<div class="vep-ajax-spinner"></div>';
            section.style.position = 'relative';
            section.appendChild(overlay);
        }
        overlay.style.display = 'flex';
    }

    function hideSpinner() {
        var overlay = document.querySelector('#vep-event-agreements-section .vep-ajax-spinner-overlay');
        if (overlay) overlay.style.display = 'none';
    }

    function reload(overrides) {
        if (!eventId || typeof vepAdmin === 'undefined') return;

        var actionKeys = ['agreement_action', 'agreement_id', '_wpnonce'];
        var extra = {};

        if (overrides) {
            Object.keys(overrides).forEach(function (k) {
                if (actionKeys.indexOf(k) !== -1) {
                    extra[k] = overrides[k];
                } else {
                    state[k] = overrides[k];
                }
            });
            if (overrides.agreement_status !== undefined || overrides.per_page !== undefined || overrides.s !== undefined) {
                state.paged = '1';
            }
        }

        showSpinner();

        var data = new URLSearchParams();
        data.append('action',   'vep_reload_event_agreements_table');
        data.append('event_id', eventId);
        Object.keys(state).forEach(function (k) { data.append(k, state[k]); });
        Object.keys(extra).forEach(function (k) { data.append(k, extra[k]); });

        fetch(vepAdmin.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                hideSpinner();
                if (!result || !result.success) {
                    if (typeof window.vepToast === 'function') {
                        window.vepToast(
                            (result && result.data) ? result.data : 'An error occurred.',
                            'error'
                        );
                    }
                    return;
                }
                if (!result.data || !result.data.html) return;
                var section = document.getElementById('vep-event-agreements-section');
                if (!section) return;
                var temp = document.createElement('div');
                temp.innerHTML = result.data.html;
                var newSection = temp.querySelector('#vep-event-agreements-section');
                if (newSection) {
                    section.parentNode.replaceChild(newSection, section);
                }
                if (result.data.message && typeof window.vepToast === 'function') {
                    window.vepToast(result.data.message);
                }
                init();
            })
            .catch(function () { hideSpinner(); });
    }

    function init() {
        var section = document.getElementById('vep-event-agreements-section');
        if (!section) return;

        // Block form navigation; Search button / Enter triggers search reload.
        var form = section.querySelector('#vep-event-agreements-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var searchInput = section.querySelector('#vep-agreements-search');
                reload({ s: searchInput ? searchInput.value : '' });
            });
        }

        // Rows-per-page selector.
        section.querySelectorAll('.vep-per-page-select').forEach(function (sel) {
            sel.addEventListener('change', function () {
                reload({ per_page: this.value });
            });
        });

        // Pagination links.
        section.querySelectorAll('.tablenav-pages a, .pagination-links a').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var url = new URL(this.href);
                reload({ paged: url.searchParams.get('paged') || '1' });
            });
        });

        // Status filter links (All / Awaiting / Completed / Cancelled).
        section.querySelectorAll('.subsubsub a').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var url = new URL(this.href);
                reload({ agreement_status: url.searchParams.get('agreement_status') || '', paged: '1' });
            });
        });

        // Column sort links.
        section.querySelectorAll('th.sortable a, th.sorted a').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var url = new URL(this.href);
                reload({
                    orderby: url.searchParams.get('orderby') || 'id',
                    order:   url.searchParams.get('order')   || 'desc',
                    paged:   '1'
                });
            });
        });

        // Delete row-action links.
        section.querySelectorAll('a[data-agreement-action="delete"]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var msg = link.dataset.confirmMsg || 'Are you sure you want to delete this agreement?';
                if (!window.confirm(msg)) return;
                var url = new URL(this.href);
                reload({
                    agreement_action: 'delete',
                    agreement_id:     url.searchParams.get('id'),
                    _wpnonce:         url.searchParams.get('_wpnonce')
                });
            });
        });

        // Edit agreement via modal.
        section.querySelectorAll('a[data-agreement-edit-modal]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var id = parseInt(this.dataset.agreementEditModal, 10);
                if (typeof window.vepOpenAgreementModal === 'function') {
                    window.vepOpenAgreementModal(id);
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var q = new URLSearchParams(window.location.search);
        if (q.get('page') !== 'volunteer-exchange-events' || q.get('action') !== 'view' || !q.get('id')) return;
        eventId = q.get('id');
        if (q.get('per_page'))         state.per_page         = q.get('per_page');
        if (q.get('paged'))            state.paged            = q.get('paged');
        if (q.get('agreement_status')) state.agreement_status = q.get('agreement_status');
        if (q.get('orderby'))          state.orderby          = q.get('orderby');
        if (q.get('order'))            state.order            = q.get('order');
        if (q.get('s'))                state.s                = q.get('s');
        init();
        document.addEventListener('vep:reload-agreements', function () { reload(); });
    });

}());
/**
 * Agreement create/edit modal IIFE.
 * Exposes window.vepOpenAgreementModal(agreementId).
 * agreementId = 0 → Add New mode; agreementId > 0 → Edit mode.
 */
(function () {
    'use strict';

    var i18n   = (typeof vepAdmin !== 'undefined' && vepAdmin.i18n)   ? vepAdmin.i18n   : {};
    var ajaxUrl = (typeof vepAdmin !== 'undefined') ? vepAdmin.ajaxUrl : ajaxurl;
    var nonce   = (typeof vepAdmin !== 'undefined') ? vepAdmin.nonce   : '';

    // Choices.js instances for participant selects.
    var choicesP1 = null;
    var choicesP2 = null;

    function getChoicesOpts() {
        var ci18n = (typeof vepAdmin !== 'undefined' && vepAdmin.choicesI18n) ? vepAdmin.choicesI18n : {};
        return {
            searchEnabled: true,
            searchFields: ['label'],
            searchPlaceholderValue: ci18n.searchPlaceholderValue || 'Search...',
            itemSelectText: ci18n.itemSelectText || '',
            noResultsText: ci18n.noResultsText || 'No results found',
            noChoicesText: ci18n.noChoicesText || 'No options available',
            removeItemButton: false,
            shouldSort: false
        };
    }

    function initChoices() {
        if (typeof Choices === 'undefined') return;
        var p1el = document.getElementById('vep-modal-p1-id');
        var p2el = document.getElementById('vep-modal-p2-id');
        if (p1el && !choicesP1) { choicesP1 = new Choices(p1el, getChoicesOpts()); }
        if (p2el && !choicesP2) { choicesP2 = new Choices(p2el, getChoicesOpts()); }
    }

    function resetChoices() {
        try { if (choicesP1) { choicesP1.destroy(); choicesP1 = null; } } catch(e) { choicesP1 = null; }
        try { if (choicesP2) { choicesP2.destroy(); choicesP2 = null; } } catch(e) { choicesP2 = null; }
    }

    function openModal() {
        var overlay = document.getElementById('vep-agreement-modal-overlay');
        if (overlay) { overlay.style.display = 'flex'; }
        initChoices();
    }

    function closeModal() {
        var overlay = document.getElementById('vep-agreement-modal-overlay');
        if (overlay) { overlay.style.display = 'none'; }
    }

    function setTitle(text) {
        var el = document.getElementById('vep-agreement-modal-title');
        if (el) { el.textContent = text; }
    }

    function setSaveNewVisible(visible) {
        var btn = document.getElementById('vep-agreement-modal-save-new');
        if (btn) { btn.style.display = visible ? '' : 'none'; }
    }

    function showNewFields() {
        var n = document.getElementById('vep-agreement-modal-new-fields');
        var e = document.getElementById('vep-agreement-modal-edit-fields');
        if (n) { n.style.display = ''; }
        if (e) { e.style.display = 'none'; }
    }

    function showEditFields() {
        var n = document.getElementById('vep-agreement-modal-new-fields');
        var e = document.getElementById('vep-agreement-modal-edit-fields');
        if (n) { n.style.display = 'none'; }
        if (e) { e.style.display = ''; }
    }

    function showLoading() {
        var loading = document.getElementById('vep-agreement-modal-loading');
        var form    = document.getElementById('vep-agreement-modal-form');
        var saveBtn = document.getElementById('vep-agreement-modal-save');
        if (loading) loading.style.display = 'flex';
        if (form)    form.style.display = 'none';
        if (saveBtn) saveBtn.disabled = true;
    }

    function hideLoading() {
        var loading = document.getElementById('vep-agreement-modal-loading');
        var form    = document.getElementById('vep-agreement-modal-form');
        var saveBtn = document.getElementById('vep-agreement-modal-save');
        if (loading) loading.style.display = 'none';
        if (form)    form.style.display = '';
        if (saveBtn) saveBtn.disabled = false;
    }

    function resetForm() {
        var form = document.getElementById('vep-agreement-modal-form');
        if (form) { form.reset(); }
        resetChoices();
        document.getElementById('vep-agreement-modal-agreement-id').value = '0';
    }

    function saveAgreement(stayOpen) {
        var form = document.getElementById('vep-agreement-modal-form');
        if (!form) return;

        var saveBtn = document.getElementById('vep-agreement-modal-save');
        var saveNewBtn = document.getElementById('vep-agreement-modal-save-new');
        var activeBtn = stayOpen ? saveNewBtn : saveBtn;
        var origText = activeBtn ? activeBtn.textContent : '';

        var fd = new FormData(form);

        // Client-side validation for create mode.
        if ('0' === String(fd.get('agreement_id') || '0')) {
            var p1val      = String(fd.get('participant1_id') || '');
            var p2val      = String(fd.get('participant2_id') || '');
            var descval    = String(fd.get('description') || '').trim();
            var valError   = '';
            if (!p1val)                 { valError = i18n.selectP1 || 'Select Participant 1'; }
            else if (!p2val)            { valError = i18n.selectP2 || 'Select Participant 2'; }
            else if (p1val === p2val)   { valError = i18n.participantsMustDiffer || 'Participants must be different'; }
            else if (!descval)          { valError = i18n.enterDescription || 'Enter a description'; }
            if (valError) {
                if (typeof window.vepToast === 'function') { window.vepToast(valError, 'error'); }
                return;
            }
        }

        if (activeBtn) {
            activeBtn.textContent = i18n.saving || 'Saving\u2026';
            activeBtn.disabled = true;
        }

        fd.append('action', 'vep_admin_save_agreement');

        fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    if (typeof window.vepToast === 'function') {
                        window.vepToast(res.data && res.data.message ? res.data.message : (i18n.agreementSaved || 'Saved.'), 'success');
                    }
                    document.dispatchEvent(new CustomEvent('vep:reload-agreements'));
                    if (stayOpen) {
                        resetForm();
                        initChoices();
                        showNewFields();
                        setSaveNewVisible(true);
                        setTitle(i18n.addNewAgreement || 'Add New Agreement');
                    } else {
                        closeModal();
                    }
                } else {
                    var msg = res.data && res.data.message ? res.data.message : 'Error';
                    if (typeof window.vepToast === 'function') { window.vepToast(msg, 'error'); }
                }
            })
            .catch(function () {
                if (typeof window.vepToast === 'function') { window.vepToast('Network error', 'error'); }
            })
            .finally(function () {
                if (activeBtn) {
                    activeBtn.textContent = origText;
                    activeBtn.disabled = false;
                }
            });
    }

    window.vepOpenAgreementModal = function (agreementId) {
        agreementId = parseInt(agreementId, 10) || 0;

        if (agreementId > 0) {
            // Edit mode: open immediately with spinner, then load data.
            setTitle(i18n.editAgreement || 'Edit Agreement');
            showEditFields();
            setSaveNewVisible(false);
            document.getElementById('vep-agreement-modal-agreement-id').value = String(agreementId);
            showLoading();
            openModal();

            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'vep_admin_get_agreement_data', nonce: nonce, id: String(agreementId) })
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) {
                        closeModal();
                        if (typeof window.vepToast === 'function') { window.vepToast(res.data && res.data.message ? res.data.message : 'Error', 'error'); }
                        return;
                    }
                    var d = res.data;
                    // Populate participant names.
                    var p1span = document.getElementById('vep-agreement-modal-p1-name');
                    var p2span = document.getElementById('vep-agreement-modal-p2-name');
                    if (p1span) p1span.textContent = d.participant1_name || '';
                    if (p2span) p2span.textContent = d.participant2_name || '';
                    // Populate initiator select.
                    var opt1 = document.getElementById('vep-modal-initiator-opt1');
                    var opt2 = document.getElementById('vep-modal-initiator-opt2');
                    var selInitiator = document.getElementById('vep-modal-initiator-id');
                    if (opt1) { opt1.value = String(d.participant1_id); opt1.textContent = d.participant1_name || ''; }
                    if (opt2) { opt2.value = String(d.participant2_id); opt2.textContent = d.participant2_name || ''; }
                    if (selInitiator) { selInitiator.value = String(d.initiator_id); }
                    // Populate description.
                    var desc = document.getElementById('vep-modal-agreement-desc');
                    if (desc) desc.value = d.description || '';
                    // Populate status.
                    var statusSel = document.getElementById('vep-modal-agreement-status');
                    if (statusSel) { statusSel.value = d.status || 'agreed'; }
                    hideLoading();
                })
                .catch(function () {
                    closeModal();
                    if (typeof window.vepToast === 'function') { window.vepToast('Network error', 'error'); }
                });
        } else {
            // Add New mode.
            resetForm();
            showNewFields();
            setSaveNewVisible(true);
            setTitle(i18n.addNewAgreement || 'Add New Agreement');
            openModal();
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        var closeBtn = document.getElementById('vep-agreement-modal-close');
        var cancelBtn = document.getElementById('vep-agreement-modal-cancel');
        var saveBtn = document.getElementById('vep-agreement-modal-save');
        var saveNewBtn = document.getElementById('vep-agreement-modal-save-new');
        var overlay = document.getElementById('vep-agreement-modal-overlay');
        var addNewBtn = document.getElementById('vep-open-add-agreement-modal');

        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeModal();
            });
        }
        if (saveBtn) saveBtn.addEventListener('click', function () { saveAgreement(false); });
        if (saveNewBtn) saveNewBtn.addEventListener('click', function () { saveAgreement(true); });
        if (addNewBtn) {
            addNewBtn.addEventListener('click', function () {
                window.vepOpenAgreementModal(0);
            });
        }
        document.addEventListener('keydown', function (e) {
            if ('Escape' === e.key && overlay && overlay.style.display !== 'none') {
                closeModal();
            }
        });
    });

}());
/**
 * Participant create/edit modal IIFE.
 * Exposes window.vepOpenParticipantModal(participantId).
 * participantId = 0 → Add New mode; participantId > 0 → Edit mode.
 */
(function () {
    'use strict';

    var i18n    = (typeof vepAdmin !== 'undefined' && vepAdmin.i18n)   ? vepAdmin.i18n   : {};
    var ajaxUrl = (typeof vepAdmin !== 'undefined') ? vepAdmin.ajaxUrl : ajaxurl;
    var nonce   = (typeof vepAdmin !== 'undefined') ? vepAdmin.nonce   : '';

    function openModal() {
        var overlay = document.getElementById('vep-participant-modal-overlay');
        if (overlay) overlay.style.display = 'flex';
    }

    function closeModal() {
        var overlay = document.getElementById('vep-participant-modal-overlay');
        if (overlay) overlay.style.display = 'none';
    }

    function setTitle(text) {
        var el = document.getElementById('vep-participant-modal-title');
        if (el) el.textContent = text;
    }

    function resetForm() {
        var form = document.getElementById('vep-participant-modal-form');
        if (form) form.reset();
        document.getElementById('vep-participant-modal-participant-id').value = '0';
        // Reset names list to one empty row.
        setNamesField('');
        // Hide logo preview.
        var preview = document.getElementById('vep-participant-modal-logo-preview');
        if (preview) preview.style.display = 'none';
    }

    function showLoading() {
        var loading = document.getElementById('vep-participant-modal-loading');
        var form    = document.getElementById('vep-participant-modal-form');
        var saveBtn = document.getElementById('vep-participant-modal-save');
        if (loading) loading.style.display = 'flex';
        if (form)    form.style.display = 'none';
        if (saveBtn) saveBtn.disabled = true;
    }

    function hideLoading() {
        var loading = document.getElementById('vep-participant-modal-loading');
        var form    = document.getElementById('vep-participant-modal-form');
        var saveBtn = document.getElementById('vep-participant-modal-save');
        if (loading) loading.style.display = 'none';
        if (form)    form.style.display = '';
        if (saveBtn) saveBtn.disabled = false;
    }

    function setFieldValue(id, value) {
        var el = document.getElementById(id);
        if (!el) return;
        if (el.type === 'checkbox') {
            el.checked = (value === 1 || value === '1' || value === true);
        } else {
            el.value = value !== null && value !== undefined ? String(value) : '';
        }
    }

    function makeNameRow(value) {
        var row = document.createElement('div');
        row.style.cssText = 'display:flex; gap:6px; margin-bottom:4px;';
        var input = document.createElement('input');
        input.type = 'text';
        input.name = 'expected_participants_names[]';
        input.value = value || '';
        input.placeholder = (typeof vepAdmin !== 'undefined' && vepAdmin.i18n && vepAdmin.i18n.namePlaceholder) || 'e.g., Jane Doe';
        input.className = 'regular-text';
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.textContent = '\u2715';
        btn.className = 'button';
        btn.setAttribute('aria-label', (typeof vepAdmin !== 'undefined' && vepAdmin.i18n && vepAdmin.i18n.remove) || 'Remove');
        btn.addEventListener('click', function () {
            var list = document.getElementById('vep-modal-names-list');
            if (list && list.children.length > 1) {
                row.remove();
            } else {
                input.value = '';
            }
        });
        row.appendChild(input);
        row.appendChild(btn);
        return row;
    }

    function setNamesField(rawValue) {
        var list = document.getElementById('vep-modal-names-list');
        if (!list) return;
        list.innerHTML = '';
        var names = [];
        if (rawValue) {
            var decoded = null;
            try {
                decoded = JSON.parse(rawValue);
            } catch (e) {
                decoded = null;
            }
            if (Array.isArray(decoded)) {
                names = decoded.filter(function (n) { return typeof n === 'string' && n.trim(); });
            } else {
                // Legacy plain text: one name per line (commas may be part of a name).
                names = String(rawValue).split(/[\r\n]+/).map(function (n) { return n.trim(); }).filter(Boolean);
            }
        }
        if (names.length === 0) names = [''];
        names.forEach(function (name) { list.appendChild(makeNameRow(name)); });
    }

    window.vepOpenParticipantModal = function (participantId) {
        participantId = parseInt(participantId, 10) || 0;

        if (participantId > 0) {
            setTitle(i18n.editParticipant || 'Edit Participant');
            document.getElementById('vep-participant-modal-participant-id').value = String(participantId);
            showLoading();
            openModal();

            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'vep_admin_get_participant_data', nonce: nonce, id: String(participantId) })
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) {
                        closeModal();
                        if (typeof window.vepToast === 'function') { window.vepToast(res.data && res.data.message ? res.data.message : 'Error', 'error'); }
                        return;
                    }
                    var d = res.data;
                    setFieldValue('vep-modal-org-name',        d.organization_name);
                    setFieldValue('vep-modal-description',     d.description);
                    setFieldValue('vep-modal-expected-count',  d.expected_participants_count);
                    setNamesField(d.expected_participants_names);
                    setFieldValue('vep-modal-type-id',         String(d.participant_type_id));
                    setFieldValue('vep-modal-contact-person',  d.contact_person_name);
                    setFieldValue('vep-modal-contact-email',   d.contact_email);
                    setFieldValue('vep-modal-contact-phone',   d.contact_phone);
                    setFieldValue('vep-modal-link',            d.link);
                    setFieldValue('vep-modal-is-approved',     d.is_approved);

                    // Logo preview.
                    var preview = document.getElementById('vep-participant-modal-logo-preview');
                    var img = document.getElementById('vep-participant-modal-logo-img');
                    if (d.logo_url) {
                        if (img) img.src = d.logo_url;
                        if (preview) preview.style.display = '';
                    } else {
                        if (preview) preview.style.display = 'none';
                    }

                    // Tag checkboxes.
                    var tagIds = Array.isArray(d.tag_ids) ? d.tag_ids : [];
                    document.querySelectorAll('#vep-participant-modal-form .vep-modal-tag-cb').forEach(function (cb) {
                        cb.checked = tagIds.indexOf(parseInt(cb.value, 10)) !== -1;
                    });

                    hideLoading();
                })
                .catch(function () {
                    closeModal();
                    if (typeof window.vepToast === 'function') { window.vepToast('Network error', 'error'); }
                });
        } else {
            resetForm();
            setTitle(i18n.addNewParticipant || 'Add New Participant');
            openModal();
        }
    };

    function saveParticipant() {
        var form = document.getElementById('vep-participant-modal-form');
        if (!form) return;

        var saveBtn = document.getElementById('vep-participant-modal-save');
        var origText = saveBtn ? saveBtn.textContent : '';
        if (saveBtn) { saveBtn.textContent = i18n.saving || 'Saving\u2026'; saveBtn.disabled = true; }

        var fd = new FormData(form);
        fd.append('action', 'vep_admin_save_participant');

        fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    if (typeof window.vepToast === 'function') {
                        window.vepToast(res.data && res.data.message ? res.data.message : (i18n.participantSaved || 'Saved.'), 'success');
                    }
                    document.dispatchEvent(new CustomEvent('vep:reload-participants'));
                    closeModal();
                } else {
                    var msg = res.data && res.data.message ? res.data.message : 'Error';
                    if (typeof window.vepToast === 'function') { window.vepToast(msg, 'error'); }
                }
            })
            .catch(function () {
                if (typeof window.vepToast === 'function') { window.vepToast('Network error', 'error'); }
            })
            .finally(function () {
                if (saveBtn) { saveBtn.textContent = origText; saveBtn.disabled = false; }
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var closeBtn  = document.getElementById('vep-participant-modal-close');
        var cancelBtn = document.getElementById('vep-participant-modal-cancel');
        var saveBtn   = document.getElementById('vep-participant-modal-save');
        var overlay   = document.getElementById('vep-participant-modal-overlay');
        var addNewBtn = document.getElementById('vep-open-add-participant-modal');

        if (closeBtn)  closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeModal();
            });
        }
        if (saveBtn) saveBtn.addEventListener('click', saveParticipant);
        if (addNewBtn) {
            addNewBtn.addEventListener('click', function () {
                window.vepOpenParticipantModal(0);
            });
        }
        var addNameBtn = document.getElementById('vep-modal-add-name-btn');
        if (addNameBtn) {
            addNameBtn.addEventListener('click', function () {
                var list = document.getElementById('vep-modal-names-list');
                if (list) list.appendChild(makeNameRow(''));
            });
        }
        document.addEventListener('keydown', function (e) {
            if ('Escape' === e.key && overlay && overlay.style.display !== 'none') {
                closeModal();
            }
        });
        // On the view-participant page, reload after a successful participant save.
        var qp = new URLSearchParams(window.location.search);
        if (qp.get('page') === 'volunteer-exchange-events' && qp.get('action') === 'view-participant') {
            document.addEventListener('vep:reload-participants', function () {
                window.location.reload();
            });
        }
    });

}());
/**
 * Admin JavaScript for Volunteer Exchange Platform
 */

(function() {
    'use strict';
    
    document.addEventListener('DOMContentLoaded', function() {
        initEventDisplay();
        initDisplayBackgroundSettings();
        initCompetitionsPage();
        initCopyActiveEmails();
        initAdminChoices();
        initDisplayNavBox();
        initPrintModal();
        initPerPageControls();
    });

    function initCopyActiveEmails() {
        if (typeof vepAdmin === 'undefined') {
            return;
        }

        const i18n = vepAdmin.i18n || {};

        const execCommandCopy = (text) => new Promise((resolve, reject) => {
            const helper = document.createElement('textarea');
            helper.value = text;
            helper.setAttribute('readonly', 'readonly');
            helper.style.position = 'absolute';
            helper.style.left = '-9999px';
            document.body.appendChild(helper);
            helper.select();
            try {
                const ok = document.execCommand('copy');
                document.body.removeChild(helper);
                ok ? resolve() : reject(new Error('Copy command failed'));
            } catch (error) {
                document.body.removeChild(helper);
                reject(error);
            }
        });

        const copyText = (text) => {
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                // Falls back to execCommand if the clipboard API fails due to focus issues.
                return navigator.clipboard.writeText(text).catch(() => execCommandCopy(text));
            }
            return execCommandCopy(text);
        };

        // Use event delegation so the listener survives AJAX DOM replacements.
        document.addEventListener('click', function (e) {
            const button = e.target.closest('#vep-copy-selected-emails');
            if (!button) return;

            const selectedCheckboxes = document.querySelectorAll('#vep-event-participants-section input[name="participant[]"]:checked');
            if (!selectedCheckboxes.length) {
                window.alert(i18n.printNoSelection || 'Select at least one participant in the table.');
                return;
            }

            button.disabled = true;

            const emails = Array.from(selectedCheckboxes)
                .map((checkbox) => (checkbox.dataset.contactEmail || '').trim().toLowerCase())
                .filter((email, index, list) => email && list.indexOf(email) === index);

            Promise.resolve()
                .then(() => {
                    if (!emails.length) {
                        throw new Error(i18n.noSelectedEmailsFound || 'No selected participant emails found.');
                    }

                    return copyText(emails.join('; ')).then(() => {
                        const messageTemplate = i18n.copySelectedEmailsSuccess || 'Copied %d selected participant emails to clipboard.';
                        window.alert(messageTemplate.replace('%d', String(emails.length)));
                    });
                })
                .catch((error) => {
                    window.alert(error.message || i18n.copySelectedEmailsFailed || 'An error occurred. Please try again.');
                })
                .finally(() => {
                    button.disabled = false;
                });
        });
    }
    
    /**
     * Escape a value for safe use in innerHTML (text and attribute contexts).
     */
    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function(ch) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch];
        });
    }

    /**
     * POST to admin-ajax with the admin nonce and resolve with the parsed JSON.
     * An expired nonce / session (HTTP 403 "-1", or "0") rejects with
     * {vepAuth: true} and fires a 'vep:auth-expired' event so the UI can show a
     * visible "reload" notice instead of silently freezing.
     */
    function vepAjax(action, fields) {
        const data = new FormData();
        data.append('action', action);
        data.append('nonce', vepAdmin.nonce);
        Object.keys(fields || {}).forEach(function(key) {
            data.append(key, fields[key]);
        });

        const authFailed = function() {
            document.dispatchEvent(new CustomEvent('vep:auth-expired'));
            const err = new Error('auth');
            err.vepAuth = true;
            return err;
        };

        return fetch(vepAdmin.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function(response) {
                return response.text().then(function(text) {
                    const trimmed = text.trim();
                    if (response.status === 403 || response.status === 401 || trimmed === '-1' || (response.status === 400 && trimmed === '0')) {
                        throw authFailed();
                    }
                    if (!response.ok) {
                        throw new Error('HTTP ' + response.status);
                    }
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error('Invalid JSON response');
                    }
                });
            });
    }

    /**
     * Helper function to switch views
     * @param {string} viewId - The ID of the view to show ('countdown', 'statistics', 'competitions')
     * @param {string|null} actionButtonsId - The ID of the action buttons to show, or null to hide all
     */
    function switchView(viewId, actionButtonsId = null) {
        // Hide all views
        const allViews = document.querySelectorAll('.vep-view');
        allViews.forEach(view => {
            view.classList.remove('vep-view-active');
            view.style.display = 'none';
        });

        // Show selected view
        const selectedView = document.getElementById(viewId);
        if (selectedView) {
            selectedView.style.display = 'flex';
            selectedView.classList.add('vep-view-active');
        }

        // Hide all action buttons
        const allActionButtons = document.querySelectorAll('.vep-action-buttons');
        allActionButtons.forEach(btn => btn.style.display = 'none');

        // Show selected action buttons (unless hide buttons setting is active)
        if (actionButtonsId && !(vepAdmin && vepAdmin.hideButtons)) {
            const selectedActionButtons = document.getElementById(actionButtonsId);
            if (selectedActionButtons) {
                selectedActionButtons.style.display = 'flex';
            }
        }
    }
    
    /**
     * Initialize Event Display functionality
     */
    function initEventDisplay() {
        const startButton = document.getElementById('vep-start-event-display');
        const displayModal = document.getElementById('vep-fullscreen-display');
        const closeButton = document.getElementById('vep-close-display');
        const countdownView = document.getElementById('vep-countdown-view');
        const postTimeActions = document.getElementById('vep-post-time-actions');
        const statisticsView = document.getElementById('vep-statistics-view');
        const displayMainContent = document.querySelector('.vep-display-main-content');
        const rightPanel = document.getElementById('vep-display-right');
        
        if (!startButton || !displayModal) return;
        
        let countdownInterval = null;
        let pollingInterval = null;
        let fireworksAnimation = null;
        let fireworksStopTimeout = null;
        let postTimeRevealTimeout = null;
        let loadedCompetitions = null;
        let winnerRevealTimeout = null;
        let currentWinnerIndex = 0;
        let winnersForDisplay = [];
        let commandPollInterval = null;
        let currentDisplayView = 'countdown';
        let currentAutoSwitch = false;
        let competitionsFingerprint = null;
        let competitionsRefreshInterval = null;
        let currentStatistics = null;

        // Shared-presentation / clock state (see applySync / serverNow)
        const dI18n = window.vepDisplayI18n || {};
        let displayOpen = false;
        let endTsMs = 0;
        let clockOffset = 0;
        let clockSamples = [];
        let phase = null;
        let endedLive = false;
        let endedHandled = false;
        let autoRequested = false;
        let localKey = null;
        let serverState = null;
        let syncedOnce = false;
        let pollInFlight = false;
        // Local request ordering: responses older than the newest applied one are dropped.
        let reqCounter = 0;
        let lastAppliedReqId = 0;
        let countPollInFlight = false;
        let connFails = 0;
        let applyToken = 0;
        let screenId = '';
        let noticeEl = null;
        let noticeKind = '';
        let fsButton = null;

        function syncRightPanelHeight() {
            // Height equality is now handled entirely by CSS:
            // - .vep-display-main-content uses align-items: stretch
            // - .vep-display-right is position: relative and contributes 0 intrinsic height
            //   (because .vep-leaderboard is position: absolute and removed from flow)
            // - The flexbox row therefore takes exactly the left column's height,
            //   and the absolutely-positioned leaderboard fills it precisely.
            // Clear any leftover inline styles from previous JS-driven attempts.
            if (rightPanel) {
                rightPanel.style.height = '';
            }
            if (rightPanel) {
                const leaderboardPanel = rightPanel.querySelector('.vep-leaderboard');
                if (leaderboardPanel) {
                    leaderboardPanel.style.height = '';
                    leaderboardPanel.style.maxHeight = '';
                }
            }
        }
        
        function generateCompetitionsFingerprint(competitions) {
            return (competitions || [])
                .slice()
                .sort((a, b) => a.id - b.id)
                .map(c => c.id + ':' + (c.winner_id || '') + ':' + (c.winner_name || ''))
                .join(',');
        }

        function stopCompetitionsRefresh() {
            if (competitionsRefreshInterval) {
                clearInterval(competitionsRefreshInterval);
                competitionsRefreshInterval = null;
            }
        }

        // ---------------------------------------------------------------
        // Server clock, connection/notice handling
        // ---------------------------------------------------------------

        /**
         * Server time in ms. The display PC's clock/timezone is never trusted:
         * clockOffset = server_now_ms - local time, smoothed (median of last 5,
         * RTT/2 corrected) from every poll response.
         */
        function serverNow() {
            return Date.now() + clockOffset;
        }

        function recordClockSample(serverNowMs, t0, t1) {
            if (!serverNowMs) return;
            // The server stamped the response roughly half-way through the round trip.
            clockSamples.push(serverNowMs - (t0 + t1) / 2);
            if (clockSamples.length > 5) clockSamples.shift();
            const sorted = clockSamples.slice().sort((a, b) => a - b);
            clockOffset = sorted[Math.floor(sorted.length / 2)];
        }

        function getScreenId() {
            if (screenId) return screenId;
            try {
                screenId = window.sessionStorage.getItem('vep_display_screen_id') || '';
            } catch (e) { screenId = ''; }
            if (!screenId) {
                screenId = 's' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36);
                try { window.sessionStorage.setItem('vep_display_screen_id', screenId); } catch (e) { /* ignore */ }
            }
            return screenId;
        }

        function ensureDisplayChrome() {
            if (!noticeEl) {
                noticeEl = document.createElement('div');
                noticeEl.id = 'vep-display-notice';
                noticeEl.setAttribute('role', 'alert');
                noticeEl.style.cssText = 'position:fixed;top:0;left:0;right:0;z-index:100001;padding:10px 16px;background:#b32d2e;color:#fff;font-size:18px;text-align:center;display:none;';
                displayModal.appendChild(noticeEl);
            }
            if (!fsButton) {
                fsButton = document.createElement('button');
                fsButton.type = 'button';
                fsButton.id = 'vep-reenter-fullscreen';
                fsButton.className = 'button button-secondary';
                fsButton.textContent = dI18n.reenterFullscreen || 'Re-enter fullscreen';
                fsButton.style.cssText = 'position:fixed;right:16px;bottom:16px;z-index:100001;opacity:0.85;display:none;';
                fsButton.addEventListener('click', function() {
                    requestFullscreen(displayModal);
                });
                displayModal.appendChild(fsButton);
            }
        }

        /** kind: 'auth' | 'conn' | '' (hide) */
        function showDisplayNotice(kind) {
            ensureDisplayChrome();
            if (noticeKind === kind) return;
            noticeKind = kind;
            noticeEl.textContent = '';
            if (!kind) {
                noticeEl.style.display = 'none';
                return;
            }
            const msg = document.createElement('span');
            msg.textContent = kind === 'auth'
                ? (dI18n.sessionExpired || 'Your session has expired. Please reload the page to continue.')
                : (dI18n.connectionLost || 'Connection to the server was lost. Trying to reconnect…');
            noticeEl.appendChild(msg);
            if (kind === 'auth') {
                const reloadBtn = document.createElement('button');
                reloadBtn.type = 'button';
                reloadBtn.className = 'button';
                reloadBtn.style.marginLeft = '12px';
                reloadBtn.textContent = dI18n.reload || 'Reload';
                reloadBtn.addEventListener('click', function() { window.location.reload(); });
                noticeEl.appendChild(reloadBtn);
            }
            noticeEl.style.display = 'block';
        }

        document.addEventListener('vep:auth-expired', function() {
            if (displayOpen) showDisplayNotice('auth');
        });

        /**
         * POST via the shared helper; records the server clock and the
         * (possibly edited) event end from every response and tracks connectivity.
         */
        function displayAjax(action, fields) {
            const t0 = Date.now();
            return vepAjax(action, fields).then(function(resp) {
                const t1 = Date.now();
                connFails = 0;
                if (noticeKind === 'conn') showDisplayNotice('');
                if (resp && resp.success && resp.data) {
                    recordClockSample(resp.data.server_now_ms, t0, t1);
                    if (resp.data.end_ts_ms) endTsMs = resp.data.end_ts_ms;
                }
                return resp;
            }, function(err) {
                if (!(err && err.vepAuth)) {
                    connFails++;
                    if (connFails >= 3 && displayOpen && noticeKind !== 'auth') showDisplayNotice('conn');
                }
                throw err;
            });
        }

        // ---------------------------------------------------------------
        // Shared presentation state: screens are read-only followers
        // ---------------------------------------------------------------

        /**
         * Apply one server response (poll, navigation or auto-transition).
         * Renders when the server state key (gen:seq) differs from what this
         * screen currently shows; the transition to "ended" is driven by the
         * server-decided phase, never by the local clock.
         */
        function applySync(data, reqId) {
            if (!displayOpen || !data) return;
            if (reqId && reqId < lastAppliedReqId) return; // stale response, issued before a newer applied one
            if (reqId) lastAppliedReqId = reqId;

            if (typeof data.auto_switch !== 'undefined') currentAutoSwitch = !!data.auto_switch;

            const first = !syncedOnce;
            syncedOnce = true;

            const prevPhase = phase;
            if (data.phase) phase = data.phase;
            if (phase !== 'ended') {
                endedLive = false;
                endedHandled = false;
                autoRequested = false;
            } else if (prevPhase && prevPhase !== 'ended') {
                endedLive = true; // the event ended while this screen was open
            }
            const phaseLeftEnded = prevPhase === 'ended' && phase !== 'ended';

            let state = data.state || null;
            if (state && serverState && state.gen === serverState.gen && state.seq < serverState.seq) {
                state = serverState; // a slower, older response must not roll the view back
            }
            const key = state ? state.gen + ':' + state.seq : null;
            const changed = first || key !== localKey;
            serverState = state;

            if (changed) {
                localKey = key;
                autoRequested = false;
                if (state) {
                    applyState(state, first);
                } else {
                    showCountdownView({ restore: first });
                }
                return;
            }

            if (currentDisplayView === 'countdown') {
                if (phase === 'ended') {
                    if (prevPhase !== 'ended') {
                        enterEndedCountdown();
                    } else {
                        maybeAutoTransition();
                    }
                } else if (phaseLeftEnded) {
                    showRunningCountdownUI();
                }
            }
        }

        function applyState(state, restore) {
            const token = ++applyToken;
            const view = state.view;
            if (view === 'countdown') {
                showCountdownView({ restore: restore });
            } else if (view === 'statistics') {
                showStatisticsView({ auto: !!state.auto });
            } else if (view === 'competitions') {
                showCompetitionsView();
            } else if (view === 'winner') {
                const idx = parseInt(state.winner_index, 10) || 0;
                loadCompetitions().then(function() {
                    if (token !== applyToken || !displayOpen) return;
                    if (winnersForDisplay[idx]) {
                        showWinnerView(idx, { instant: restore });
                    } else {
                        showCompetitionsView();
                    }
                }).catch(function() {
                    if (token === applyToken && displayOpen) showCompetitionsView();
                });
            } else if (view === 'closing') {
                showClosingView();
            }
        }

        /** Explicit navigation: the server increments seq; every screen follows. */
        function navigate(action, winnerIndex) {
            const reqId = ++reqCounter;
            return displayAjax('vep_display_send_command', {
                event_id: startButton.dataset.eventId,
                nav_action: action,
                winner_index: winnerIndex || 0,
                screen_id: getScreenId()
            }).then(function(resp) {
                if (resp && resp.success && resp.data) applySync(resp.data, reqId);
            }).catch(function() { /* notice already shown by displayAjax */ });
        }

        function startCommandPolling(eventId) {
            if (commandPollInterval) return;
            function pollOnce() {
                if (!displayOpen || pollInFlight) return;
                pollInFlight = true;
                const reqId = ++reqCounter;
                displayAjax('vep_display_poll_command', { event_id: eventId, screen_id: getScreenId() })
                    .then(function(resp) {
                        if (resp && resp.success) applySync(resp.data, reqId);
                    })
                    .catch(function() {})
                    .then(function() { pollInFlight = false; });
            }
            pollOnce();
            commandPollInterval = setInterval(pollOnce, 1000);
        }

        function stopCommandPolling() {
            if (commandPollInterval) {
                clearInterval(commandPollInterval);
                commandPollInterval = null;
            }
        }

        // ---------------------------------------------------------------
        // Open / close
        // ---------------------------------------------------------------

        function setDisplayOpenFlag(open) {
            try {
                const key = 'vep_display_open_' + startButton.dataset.eventId;
                if (open) {
                    window.sessionStorage.setItem(key, '1');
                } else {
                    window.sessionStorage.removeItem(key);
                }
            } catch (e) { /* ignore */ }
        }

        function openDisplay(opts) {
            opts = opts || {};
            const btn = startButton;
            const eventId = btn.dataset.eventId;
            const displayTitle = btn.dataset.displayTitle;
            const displayMode = btn.dataset.displayMode || 'leaderboard';
            const backgroundType = btn.getAttribute('data-background-type') || 'gradient';
            const solidColor = btn.getAttribute('data-background-solid-color') || '#1e3c72';
            const gradientColor1 = btn.getAttribute('data-background-gradient-color-1') || '#db870f';
            const gradientColor2 = btn.getAttribute('data-background-gradient-color-2') || '#db870f';
            const gradientColor3 = btn.getAttribute('data-background-gradient-color-3') || '#b014db';
            const gradientStop1 = parseInt(btn.getAttribute('data-background-gradient-stop-1'), 10);
            const gradientStop2 = parseInt(btn.getAttribute('data-background-gradient-stop-2'), 10);
            const gradientStop3 = parseInt(btn.getAttribute('data-background-gradient-stop-3'), 10);
            const gradientAngle = parseInt(btn.getAttribute('data-background-gradient-angle'), 10);
            const textColor = btn.getAttribute('data-text-color') || '#ffffff';

            // Absolute epoch from the server; refreshed by every poll response.
            const initialEndTs = parseInt(btn.dataset.eventEndTs, 10) || 0;
            if (!initialEndTs) {
                alert(vepAdmin.i18n.setCountdownTimeFirst);
                return;
            }
            endTsMs = initialEndTs * 1000;
            clockOffset = 0;
            clockSamples = [];

            // Reset per-session state: this screen renders whatever the SERVER says.
            displayOpen = true;
            syncedOnce = false;
            localKey = null;
            serverState = null;
            phase = null;
            endedLive = false;
            endedHandled = false;
            autoRequested = false;
            connFails = 0;
            currentAutoSwitch = btn.dataset.autoSwitchStats === '1';
            setDisplayOpenFlag(true);

            // Show modal
            displayModal.style.display = 'flex';
            ensureDisplayChrome();
            showDisplayNotice('');

            // Nothing is shown until the first server sync tells us which view to render.
            document.querySelectorAll('.vep-view').forEach(function(view) {
                view.classList.remove('vep-view-active');
                view.style.display = 'none';
            });
            document.querySelectorAll('.vep-action-buttons').forEach(function(el) { el.style.display = 'none'; });

            document.getElementById('vep-display-event-name').textContent = displayTitle;

            applyRightPanelMode(displayMode);

            applyDisplayBackground(
                displayModal,
                backgroundType,
                solidColor,
                gradientColor1,
                gradientColor2,
                gradientColor3,
                Number.isFinite(gradientStop1) ? gradientStop1 : 0,
                Number.isFinite(gradientStop2) ? gradientStop2 : 50,
                Number.isFinite(gradientStop3) ? gradientStop3 : 100,
                Number.isFinite(gradientAngle) ? gradientAngle : 128
            );

            applyDisplayTextColor(displayModal, textColor);

            // Set dynamic text from settings
            const timeUpTextVal = (vepAdmin && vepAdmin.timeUpText) ? vepAdmin.timeUpText : 'Tiden er gået';
            const timeUpHeadingEl = document.getElementById('vep-display-time-up');
            if (timeUpHeadingEl) {
                timeUpHeadingEl.textContent = timeUpTextVal;
                timeUpHeadingEl.style.display = 'none';
            }
            const expiredTextEl = document.getElementById('vep-countdown-expired-text');
            if (expiredTextEl) expiredTextEl.textContent = timeUpTextVal;

            syncRightPanelHeight();

            if (postTimeRevealTimeout) {
                clearTimeout(postTimeRevealTimeout);
                postTimeRevealTimeout = null;
            }

            if (opts.restore) {
                // Browsers only allow fullscreen from a user gesture.
                showFullscreenButton(true);
            } else {
                requestFullscreen(displayModal);
            }

            // Polling: agreement count (3 s) and shared state / heartbeat (1 s).
            startPolling(eventId, displayMode);
            startCommandPolling(eventId);
        }

        // Start display
        startButton.addEventListener('click', function() {
            openDisplay({ restore: false });
        });

        // Navigation buttons: every button asks the server to change the shared
        // state (seq + 1); this screen and all others then follow the new state.
        function bindNav(id, action, indexFn) {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('click', function() {
                const idx = indexFn ? indexFn() : 0;
                if (idx === false) return;
                navigate(action, idx);
            });
        }

        bindNav('vep-show-statistics', 'showStatistics');
        bindNav('vep-show-competitions', 'showCompetitions');
        bindNav('vep-show-closing-from-countdown', 'showClosing');
        bindNav('vep-show-statistics-from-competitions', 'showStatistics');
        bindNav('vep-show-first-winner', 'showWinner', function() { return winnersForDisplay.length > 0 ? 0 : false; });
        bindNav('vep-winner-back-to-list', 'showCompetitions');
        bindNav('vep-winner-prev', 'showWinner', function() { return currentWinnerIndex > 0 ? currentWinnerIndex - 1 : false; });
        bindNav('vep-winner-next', 'showWinner', function() { return currentWinnerIndex < winnersForDisplay.length - 1 ? currentWinnerIndex + 1 : false; });
        bindNav('vep-winner-finish', 'showClosing');
        bindNav('vep-show-closing-from-statistics', 'showClosing');
        bindNav('vep-show-closing-from-competitions', 'showClosing');
        bindNav('vep-closing-back-to-competitions', 'showCompetitions');
        bindNav('vep-closing-back-to-statistics', 'showStatistics');
        bindNav('vep-show-competitions-from-statistics', 'showCompetitions');
        bindNav('vep-back-to-countdown', 'showCountdown');

        // Close display (X button) — two-click confirmation shown inside the display.
        // A native confirm() is not used: browsers drop fullscreen when a JS dialog
        // opens and the dialog can end up hidden behind the fullscreen layer.
        // ESC never closes the display.
        let closeArmedTimeout = null;
        let closeHintEl = null;
        function disarmClose() {
            if (closeArmedTimeout) {
                clearTimeout(closeArmedTimeout);
                closeArmedTimeout = null;
            }
            if (closeHintEl) closeHintEl.style.display = 'none';
        }
        closeButton.addEventListener('click', function(e) {
            e.preventDefault();
            if (closeArmedTimeout) {
                disarmClose();
                closeDisplay();
                return;
            }
            if (!closeHintEl) {
                closeHintEl = document.createElement('div');
                closeHintEl.id = 'vep-close-display-hint';
                closeHintEl.style.cssText = 'position:fixed;top:44px;right:104px;z-index:1000000;padding:8px 14px;border-radius:6px;background:rgba(0,0,0,0.75);color:#fff;font-size:16px;display:none;';
                displayModal.appendChild(closeHintEl);
            }
            closeHintEl.textContent = dI18n.confirmCloseHint || 'Click again to close the display';
            closeHintEl.style.display = '';
            closeArmedTimeout = setTimeout(disarmClose, 4000);
        });

        // Swallow ESC while the display is open (the browser itself still leaves
        // fullscreen on ESC; the re-enter button below handles that).
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && displayOpen) {
                e.preventDefault();
            }
        });

        function isFullscreen() {
            return !!(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
        }

        function showFullscreenButton(show) {
            ensureDisplayChrome();
            fsButton.style.display = show ? '' : 'none';
        }

        function onFullscreenChange() {
            if (!displayOpen) return;
            showFullscreenButton(!isFullscreen());
        }
        document.addEventListener('fullscreenchange', onFullscreenChange);
        document.addEventListener('webkitfullscreenchange', onFullscreenChange);

        // Leaving/reloading the admin page while the display is open needs a confirmation.
        window.addEventListener('beforeunload', function(e) {
            if (!displayOpen) return undefined;
            e.preventDefault();
            e.returnValue = '';
            return '';
        });

        // Remove only THIS screen's presence when the page goes away.
        window.addEventListener('pagehide', function() {
            if (displayOpen) sendScreenLeave();
        });

        function sendScreenLeave() {
            try {
                const data = new FormData();
                data.append('action', 'vep_display_screen_leave');
                data.append('nonce', vepAdmin.nonce);
                data.append('event_id', startButton.dataset.eventId);
                data.append('screen_id', getScreenId());
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(vepAdmin.ajaxUrl, data);
                } else {
                    fetch(vepAdmin.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin', keepalive: true }).catch(function() {});
                }
            } catch (e) { /* ignore */ }
        }

        function requestFullscreen(element) {
            let result;
            try {
                if (element.requestFullscreen) {
                    result = element.requestFullscreen();
                } else if (element.webkitRequestFullscreen) {
                    result = element.webkitRequestFullscreen();
                } else if (element.msRequestFullscreen) {
                    result = element.msRequestFullscreen();
                }
            } catch (e) {
                showFullscreenButton(true);
                return;
            }
            if (result && typeof result.catch === 'function') {
                result.catch(function() { if (displayOpen) showFullscreenButton(true); });
            }
        }

        function exitFullscreen() {
            if (!isFullscreen()) return;
            let result;
            if (document.exitFullscreen) {
                result = document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                result = document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                result = document.msExitFullscreen();
            }
            if (result && typeof result.catch === 'function') result.catch(function() {});
        }

        /**
         * Close this screen only. Shared presentation state is NEVER cleared
         * here (only the explicit admin reset does that), so reopening resumes
         * exactly where the presentation was.
         */
        function closeDisplay() {
            displayOpen = false;
            setDisplayOpenFlag(false);
            sendScreenLeave();

            cleanupCountdownTimers();
            stopFireworks();
            if (pollingInterval) {
                clearInterval(pollingInterval);
                pollingInterval = null;
            }
            stopCommandPolling();
            stopCompetitionsRefresh();
            applyToken++;

            // Cancel any pending winner reveal
            if (winnerRevealTimeout) {
                clearTimeout(winnerRevealTimeout);
                winnerRevealTimeout = null;
            }

            // Hide all subheadings
            const timeUpEl = document.getElementById('vep-display-time-up');
            if (timeUpEl) timeUpEl.style.display = 'none';
            const compHeadingEl = document.getElementById('vep-display-competitions-heading');
            if (compHeadingEl) compHeadingEl.style.display = 'none';
            const winnerHeadingEl = document.getElementById('vep-display-winner-heading');
            if (winnerHeadingEl) winnerHeadingEl.style.display = 'none';

            showDisplayNotice('');
            showFullscreenButton(false);
            exitFullscreen();

            // Hide modal
            displayModal.style.display = 'none';
        }

        // Re-open automatically after an (accepted) page reload.
        setTimeout(function() {
            try {
                if (window.sessionStorage.getItem('vep_display_open_' + startButton.dataset.eventId) === '1') {
                    openDisplay({ restore: true });
                }
            } catch (e) { /* ignore */ }
        }, 0);

        function applyDisplayBackground(modal, backgroundType, solidColor, color1, color2, color3, stop1, stop2, stop3, angle) {
            const clamp = (value) => Math.min(100, Math.max(0, value));
            const clampAngle = (value) => Math.min(360, Math.max(0, value));
            const safeStop1 = clamp(stop1);
            const safeStop2 = clamp(stop2);
            const safeStop3 = clamp(stop3);
            const safeAngle = clampAngle(angle);

            if (backgroundType === 'solid') {
                modal.style.background = solidColor;
                return;
            }

            modal.style.background = `linear-gradient(${safeAngle}deg, ${color1} ${safeStop1}%, ${color2} ${safeStop2}%, ${color3} ${safeStop3}%)`;
        }

        function applyDisplayTextColor(modal, textColor) {
            const color = /^#([A-Fa-f0-9]{6})$/.test(textColor) ? textColor : '#ffffff';
            const r = parseInt(color.slice(1, 3), 16);
            const g = parseInt(color.slice(3, 5), 16);
            const b = parseInt(color.slice(5, 7), 16);

            modal.style.setProperty('--vep-display-text-color', color);
            modal.style.setProperty('--vep-display-text-strong', `rgba(${r}, ${g}, ${b}, 0.95)`);
            modal.style.setProperty('--vep-display-text-muted', `rgba(${r}, ${g}, ${b}, 0.8)`);
            modal.style.setProperty('--vep-display-text-soft', `rgba(${r}, ${g}, ${b}, 0.7)`);
        }

        function applyRightPanelMode(displayMode) {
            const showRightPanel = displayMode !== 'none';

            if (rightPanel) {
                rightPanel.style.display = showRightPanel ? '' : 'none';
            }

            if (displayMainContent) {
                displayMainContent.classList.toggle('vep-display-main-content-no-right', !showRightPanel);
            }

            syncRightPanelHeight();
        }
        
        // ---------------------------------------------------------------
        // Countdown view (display only: the "ended" decision comes from the server)
        // ---------------------------------------------------------------

        function cleanupCountdownTimers() {
            if (countdownInterval) {
                clearInterval(countdownInterval);
                countdownInterval = null;
            }
            if (postTimeRevealTimeout) {
                clearTimeout(postTimeRevealTimeout);
                postTimeRevealTimeout = null;
            }
        }

        function updateTicker() {
            // Remaining time against SERVER time; clamped at zero. The local clock
            // reaching zero does not end anything — the server phase does.
            const remainingSeconds = Math.max(0, Math.ceil((endTsMs - serverNow()) / 1000));
            const totalHours = Math.floor(remainingSeconds / 3600);
            const minutes = Math.floor((remainingSeconds % 3600) / 60);
            const seconds = remainingSeconds % 60;
            const timeText = `${String(totalHours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            const timeEl = document.getElementById('vep-display-timer-time');
            if (timeEl && timeEl.textContent !== timeText) {
                timeEl.textContent = timeText;
                syncRightPanelHeight();
            }
        }

        function startTicker() {
            if (countdownInterval) clearInterval(countdownInterval);
            updateTicker();
            countdownInterval = setInterval(updateTicker, 250);
        }

        function setCountdownExpiredUI(expired) {
            const timerElement = displayModal.querySelector('.vep-countdown-timer');
            const expiredElement = displayModal.querySelector('.vep-countdown-expired');
            if (timerElement) timerElement.style.display = expired ? 'none' : '';
            if (expiredElement) expiredElement.style.display = expired ? 'block' : 'none';
            syncRightPanelHeight();
        }

        function showRunningCountdownUI() {
            stopFireworks();
            if (postTimeRevealTimeout) {
                clearTimeout(postTimeRevealTimeout);
                postTimeRevealTimeout = null;
            }
            setCountdownExpiredUI(false);
            if (postTimeActions) postTimeActions.style.display = 'none';
            const timeUpEl = document.getElementById('vep-display-time-up');
            if (timeUpEl) timeUpEl.style.display = 'none';
        }

        /** Fireworks for the live "time's up" moment — once per open, never on restore. */
        function startEndFireworksOnce() {
            if (endedLive && !endedHandled) {
                endedHandled = true;
                startFireworks();
                return true;
            }
            return false;
        }

        function showCountdownView(opts) {
            stopCompetitionsRefresh();
            if (winnerRevealTimeout) {
                clearTimeout(winnerRevealTimeout);
                winnerRevealTimeout = null;
            }
            cleanupCountdownTimers();

            ['vep-display-time-up', 'vep-display-competitions-heading', 'vep-display-winner-heading'].forEach(function(id) {
                const el = document.getElementById(id);
                if (el) el.style.display = 'none';
            });

            currentDisplayView = 'countdown';
            switchView('vep-countdown-view', null);
            if (postTimeActions) postTimeActions.style.display = 'none';
            startTicker();

            if (phase === 'ended') {
                enterEndedCountdown();
            } else {
                stopFireworks();
                setCountdownExpiredUI(false);
            }
        }

        /** Countdown view after the server says the event has ended. */
        function enterEndedCountdown() {
            if (currentDisplayView !== 'countdown') return;

            setCountdownExpiredUI(true);
            const justEnded = startEndFireworksOnce();
            prefetchCompetitions();

            // One-time automatic switch to statistics (server compare-and-set).
            // A manual "back to countdown" after the end is never bounced.
            const auto = maybeAutoTransition();

            if (postTimeRevealTimeout) clearTimeout(postTimeRevealTimeout);
            // Live end: 10 s grace before the buttons appear. Already ended when
            // (re)opened: immediately. If an automatic switch is pending, the
            // buttons are only a fallback in case it fails.
            const delay = (justEnded || auto) ? 10000 : 0;
            postTimeRevealTimeout = setTimeout(function() {
                postTimeRevealTimeout = null;
                if (!displayOpen || currentDisplayView !== 'countdown' || phase !== 'ended') return;
                if (postTimeActions && !(vepAdmin && vepAdmin.hideButtons)) postTimeActions.style.display = 'flex';
                updateCompetitionButtonVisibility();
            }, delay);
        }

        /**
         * Ask the server to perform the automatic end transition. Allowed only
         * when no explicit navigation happened after the countdown was set up
         * before the end; N screens produce exactly one transition.
         *
         * @return {boolean} whether an automatic transition is (or was just) pending.
         */
        function maybeAutoTransition() {
            if (!currentAutoSwitch || phase !== 'ended' || currentDisplayView !== 'countdown') return false;
            const st = serverState;
            const eligible = !st || (st.view === 'countdown' && (parseInt(st.updated_at, 10) || 0) * 1000 < endTsMs);
            if (!eligible) return false;
            if (autoRequested) return true;
            autoRequested = true;
            const reqId = ++reqCounter;

            displayAjax('vep_display_auto_transition', {
                event_id: startButton.dataset.eventId,
                screen_id: getScreenId()
            }).then(function(resp) {
                if (resp && resp.success && resp.data) {
                    applySync(resp.data, reqId);
                } else {
                    autoRequested = false;
                }
            }).catch(function() {
                autoRequested = false;
            });
            return true;
        }

        function startPolling(eventId, displayMode) {
            if (pollingInterval) clearInterval(pollingInterval);

            function fetchAgreementCount() {
                if (!displayOpen || countPollInFlight) return;
                countPollInFlight = true;

                displayAjax('vep_get_agreement_count', {
                    event_id: eventId,
                    display_mode: displayMode
                })
                .then(data => {
                    if (data.success && displayOpen) {
                        document.getElementById('vep-agreements-count').textContent = data.data.count;

                        if (data.data.display_mode === 'none') {
                            return;
                        }

                        if (data.data.display_mode === 'recent_agreements') {
                            updateRecentAgreements(data.data.recent_agreements);
                        } else {
                            updateLeaderboard(data.data.leaderboard);
                        }
                    }
                })
                .catch(() => {})
                .then(() => { countPollInFlight = false; });
            }

            // Initial fetch
            fetchAgreementCount();

            // Poll every 3 seconds. Polling is NOT stopped when the countdown
            // reaches zero (counts are capped server-side at the event end, and
            // an edited end date must be picked up).
            pollingInterval = setInterval(fetchAgreementCount, 3000);
        }

        function updateLeaderboard(leaderboard) {
            const listElement = document.getElementById('vep-leaderboard-list');
            const titleElement = document.getElementById('vep-display-right-panel-title');
            const iconElement = document.getElementById('vep-display-right-panel-icon');

            if (titleElement) {
                titleElement.textContent = vepAdmin.i18n.topParticipantsTitle;
            }

            if (iconElement) {
                iconElement.style.display = '';
            }
            
            if (!leaderboard || leaderboard.length === 0) {
                listElement.innerHTML = '<p class="vep-leaderboard-empty">' + escapeHtml(vepAdmin.i18n.noAgreementsYet) + '</p>';
                return;
            }
            
            let html = '';
            leaderboard.slice(0, 4).forEach((participant, index) => {
                const position = index + 1;
                const medal = position === 1 ? '🥇' : position === 2 ? '🥈' : position === 3 ? '🥉' : position + '.';
                const logoHtml = participant.logo_url 
                    ? `<img src="${escapeHtml(participant.logo_url)}" alt="${escapeHtml(participant.organization_name)}" class="vep-leaderboard-logo">`
                    : '<div class="vep-leaderboard-logo-placeholder"></div>';
                
                html += `
                    <div class="vep-leaderboard-item ${position <= 3 ? 'vep-leaderboard-top' : ''}">
                        <div class="vep-leaderboard-position">${medal}</div>
                        ${logoHtml}
                        <div class="vep-leaderboard-info">
                            <div class="vep-leaderboard-name">${escapeHtml(participant.organization_name)}</div>
                            <div class="vep-leaderboard-count">${escapeHtml(participant.agreement_count)} ${participant.agreement_count > 1 ? vepAdmin.i18n.agreementPlural : vepAdmin.i18n.agreementSingular}</div>
                        </div>
                    </div>
                `;
            });
            
            listElement.innerHTML = html;
            syncRightPanelHeight();
        }

        function updateRecentAgreements(recentAgreements) {
            const listElement = document.getElementById('vep-leaderboard-list');
            const titleElement = document.getElementById('vep-display-right-panel-title');
            const iconElement = document.getElementById('vep-display-right-panel-icon');

            if (titleElement) {
                titleElement.textContent = vepAdmin.i18n.latestAgreementsTitle;
            }

            if (iconElement) {
                iconElement.style.display = 'none';
            }

            if (!recentAgreements || recentAgreements.length === 0) {
                listElement.innerHTML = '<p class="vep-leaderboard-empty">' + escapeHtml(vepAdmin.i18n.noRecentAgreementsYet) + '</p>';
                syncRightPanelHeight();
                return;
            }

            let html = '';
            recentAgreements.forEach((agreement) => {
                const participant1 = agreement.participant1_name || '';
                const participant2 = agreement.participant2_name || '';
                const participant1Id = parseInt(agreement.participant1_id, 10) || 0;
                const participant2Id = parseInt(agreement.participant2_id, 10) || 0;
                const initiatorId = parseInt(agreement.initiator_id, 10) || 0;
                const participant1IsInitiator = participant1Id > 0 && participant1Id === initiatorId;
                const participant2IsInitiator = participant2Id > 0 && participant2Id === initiatorId;

                html += `
                    <div class="vep-recent-agreement-item">
                        <div class="vep-recent-agreement-info">
                            <div class="vep-recent-agreement-actor-row">
                                <span class="vep-recent-agreement-check ${participant1IsInitiator ? 'is-visible' : ''}">${participant1IsInitiator ? '&#10003;' : ''}</span>
                                <span class="vep-recent-agreement-actor-name">${escapeHtml(participant1)}</span>
                            </div>
                            <div class="vep-recent-agreement-actor-row">
                                <span class="vep-recent-agreement-check ${participant2IsInitiator ? 'is-visible' : ''}">${participant2IsInitiator ? '&#10003;' : ''}</span>
                                <span class="vep-recent-agreement-actor-name">${escapeHtml(participant2)}</span>
                            </div>
                        </div>
                    </div>
                `;
            });

            listElement.innerHTML = html;
            syncRightPanelHeight();
        }

        window.addEventListener('resize', function() {
            syncRightPanelHeight();

            if (currentDisplayView === 'statistics' && currentStatistics) {
                drawStatisticsChart(currentStatistics.per_minute, currentStatistics.duration_minutes);
            }
        });
        
        function showStatisticsView(opts) {
            opts = opts || {};
            const showTimeUp = opts.auto === true;
            const eventId = startButton.dataset.eventId;

            stopCompetitionsRefresh();
            cleanupCountdownTimers();
            if (winnerRevealTimeout) {
                clearTimeout(winnerRevealTimeout);
                winnerRevealTimeout = null;
            }

            // The automatic end transition keeps the "time's up" fireworks running;
            // any manual navigation stops them.
            if (showTimeUp) {
                startEndFireworksOnce();
            } else {
                stopFireworks();
            }

            // Show/hide the header subheadings
            const timeUpEl = document.getElementById('vep-display-time-up');
            if (timeUpEl) timeUpEl.style.display = showTimeUp ? '' : 'none';
            const compHeadingEl = document.getElementById('vep-display-competitions-heading');
            if (compHeadingEl) compHeadingEl.style.display = 'none';
            const winnerHeadingElStat = document.getElementById('vep-display-winner-heading');
            if (winnerHeadingElStat) winnerHeadingElStat.style.display = 'none';

            // Show back-to-countdown button whenever we are in statistics view
            const backToCountdownBtn = document.getElementById('vep-back-to-countdown');
            if (backToCountdownBtn) backToCountdownBtn.style.display = '';

            currentDisplayView = 'statistics';

            // Switch to statistics view with action buttons (switchView handles hiding all other views)
            switchView('vep-statistics-view', 'vep-statistics-actions');
            updateCompetitionButtonVisibility();
            prefetchCompetitions();

            // Fetch statistics from server (only agreements up to the event end count)
            displayAjax('vep_get_event_statistics', { event_id: eventId })
            .then(data => {
                if (!data.success) throw new Error(data.data && data.data.message);
                const statisticsView = document.getElementById('vep-statistics-view');
                const errorEl = statisticsView ? statisticsView.querySelector('.vep-statistics-error') : null;
                if (errorEl) errorEl.style.display = 'none';
                renderStatistics(data.data);
            })
            .catch(() => {
                const statisticsView = document.getElementById('vep-statistics-view');
                const errorEl = statisticsView ? statisticsView.querySelector('.vep-statistics-error') : null;
                if (errorEl) {
                    errorEl.textContent = vepAdmin.i18n.statsLoadingError || 'Could not load statistics.';
                    errorEl.style.display = 'block';
                }
            });
        }

        function formatShortDuration(template, value) {
            return String(template).replace('%d', String(value));
        }

        function renderStatistics(stats) {
            currentStatistics = stats;

            const totalEl = document.getElementById('vep-stat-total');
            const avgEl   = document.getElementById('vep-stat-avg');
            const maxEl   = document.getElementById('vep-stat-max');
            const firstEl = document.getElementById('vep-stat-first');

            if (totalEl) totalEl.textContent = stats.total;
            if (avgEl)   avgEl.textContent   = stats.avg_per_minute;
            if (maxEl)   maxEl.textContent   = stats.max_by_actor;

            if (firstEl) {
                const s = stats.first_agreement_seconds;
                if (s === null || s === undefined || s < 0) {
                    firstEl.textContent = '—';
                } else if (s < 60) {
                    firstEl.textContent = formatShortDuration(dI18n.secondsShort || '%d sec.', Math.round(s));
                } else {
                    firstEl.textContent = formatShortDuration(dI18n.minutesShort || '%d min.', Math.round(s / 60));
                }
            }

            drawStatisticsChart(stats.per_minute, stats.duration_minutes);
        }

        function drawStatisticsChart(perMinute, durationMinutes) {
            const chartCanvas = document.getElementById('vep-statistics-chart');
            if (!chartCanvas) return;

            const ctx = chartCanvas.getContext('2d');

            // Measure the rendered size of the canvas element
            const rect  = chartCanvas.getBoundingClientRect();
            const width  = rect.width  || chartCanvas.offsetWidth  || 800;
            const height = rect.height || chartCanvas.offsetHeight || 300;
            chartCanvas.width  = width;
            chartCanvas.height = height;

            // Build full data array, filling minutes with no agreements as 0
            const data = [];
            for (let i = 0; i < durationMinutes; i++) {
                data.push(perMinute[i] || 0);
            }

            const maxVal  = Math.max(1, ...data);
            const padding = { top: 30, right: 20, bottom: 50, left: 50 };
            const cw      = width  - padding.left - padding.right;
            const ch      = height - padding.top  - padding.bottom;
            const barW    = Math.max(1, cw / data.length);

            ctx.clearRect(0, 0, width, height);

            // Always use fixed light colours so the chart is readable on any
            // background. The dark panel behind the statistics view provides contrast.
            const textColor = 'rgba(255, 255, 255, 0.9)';
            const gridColor = 'rgba(255, 255, 255, 0.25)';
            const barColor  = 'rgba(255, 210, 60, 0.92)';

            // Horizontal grid lines + Y-axis labels
            const ySteps = 5;
            ctx.lineWidth = 1;
            for (let i = 0; i <= ySteps; i++) {
                const y = padding.top + ch - (i / ySteps) * ch;
                ctx.strokeStyle = gridColor;
                ctx.beginPath();
                ctx.moveTo(padding.left, y);
                ctx.lineTo(padding.left + cw, y);
                ctx.stroke();

                ctx.fillStyle = textColor;
                ctx.font = '20px sans-serif';
                ctx.textAlign = 'right';
                ctx.fillText(Math.round((i / ySteps) * maxVal), padding.left - 6, y + 4);
            }

            // Bars
            data.forEach((val, i) => {
                const barH = (val / maxVal) * ch;
                const x    = padding.left + i * barW;
                const y    = padding.top  + ch - barH;
                ctx.fillStyle = barColor;
                ctx.fillRect(x + 1, y, barW - 2, barH);
            });

            // X-axis label (chart title)
            ctx.fillStyle = textColor;
            ctx.font = '22px sans-serif';
            ctx.textAlign = 'center';
            ctx.fillText(
                vepAdmin.i18n.statsChartLabel || 'Agreements per minute',
                padding.left + cw / 2,
                height - 6
            );

            // X-axis minute ticks (at most 10 visible ticks), 1-based labels
            const tickEvery = Math.max(1, Math.ceil(durationMinutes / 10));
            ctx.font = '18px sans-serif';
            for (let i = 0; i < durationMinutes; i += tickEvery) {
                const x = padding.left + (i + 0.5) * barW;
                ctx.fillText(i + 1, x, padding.top + ch + 16);
            }
            // Always show the last minute tick if not already included
            const lastMinute = durationMinutes - 1;
            if (lastMinute > 0 && lastMinute % tickEvery !== 0) {
                const x = padding.left + (lastMinute + 0.5) * barW;
                ctx.fillText(lastMinute + 1, x, padding.top + ch + 16);
            }
        }

        function showCompetitionsView() {
            const eventId = startButton.dataset.eventId;

            cleanupCountdownTimers();
            if (winnerRevealTimeout) {
                clearTimeout(winnerRevealTimeout);
                winnerRevealTimeout = null;
            }
            // Stop fireworks (manual navigation)
            stopFireworks();

            // Show competitions subheading, hide others
            const compHeadingEl = document.getElementById('vep-display-competitions-heading');
            if (compHeadingEl) compHeadingEl.style.display = '';
            const timeUpEl = document.getElementById('vep-display-time-up');
            if (timeUpEl) timeUpEl.style.display = 'none';
            const winnerHeadingElComp = document.getElementById('vep-display-winner-heading');
            if (winnerHeadingElComp) winnerHeadingElComp.style.display = 'none';

            currentDisplayView = 'competitions';

            // Switch to competitions view with action buttons
            switchView('vep-competitions-view', 'vep-competitions-actions');

            // Fetch competitions from server
            loadCompetitions()
            .then(() => {
                const compView = document.getElementById('vep-competitions-view');
                const errorEl = compView ? compView.querySelector('.vep-competitions-error') : null;
                if (errorEl) errorEl.style.display = 'none';
                renderCompetitions(loadedCompetitions);
            })
            .catch(() => {
                const compView = document.getElementById('vep-competitions-view');
                const errorEl = compView ? compView.querySelector('.vep-competitions-error') : null;
                if (errorEl) {
                    errorEl.textContent = vepAdmin.i18n.competitionsLoadingError || 'Could not load competitions.';
                    errorEl.style.display = 'block';
                }
            });

            // Background refresh: re-check for winner changes every 10 seconds
            stopCompetitionsRefresh();
            competitionsRefreshInterval = setInterval(function() {
                displayAjax('vep_get_event_competitions', { event_id: eventId })
                    .then(data => {
                        if (!data.success) return;
                        const newFingerprint = generateCompetitionsFingerprint(data.data.competitions);
                        if (newFingerprint !== competitionsFingerprint) {
                            loadedCompetitions = data.data.competitions;
                            renderCompetitions(loadedCompetitions);
                        }
                    })
                    .catch(() => {});
            }, 10000);
        }

        /** Fetch the event competitions and refresh winnersForDisplay. */
        function loadCompetitions() {
            return displayAjax('vep_get_event_competitions', { event_id: startButton.dataset.eventId })
                .then(data => {
                    if (!data.success) throw new Error(data.data && data.data.message);
                    loadedCompetitions = data.data.competitions;
                    winnersForDisplay = (loadedCompetitions || []).filter(c => c.winner_name);
                    updateCompetitionButtonVisibility();
                    return loadedCompetitions;
                });
        }

        function renderCompetitions(competitions) {
            const listElement = document.getElementById('vep-competitions-list');

            if (!listElement) return;

            // Only show competitions that have a winner selected
            const winnersOnly = (competitions || []).filter(c => c.winner_id);
            winnersForDisplay = (competitions || []).filter(c => c.winner_name);
            competitionsFingerprint = generateCompetitionsFingerprint(competitions);
            updateCompetitionButtonVisibility();

            if (winnersOnly.length === 0) {
                listElement.innerHTML = '<p class="vep-competitions-empty">' + escapeHtml(vepAdmin.i18n.noCompetitionsAvailable || 'No competitions available.') + '</p>';
                return;
            }

            let html = '<div class="vep-competitions-grid">';
            winnersOnly.forEach((competition) => {
                const winnerIndex = winnersForDisplay.findIndex(c => c.id === competition.id);
                const clickable  = winnerIndex !== -1;
                html += `
                    <div class="vep-competition-card vep-competition-display-card${clickable ? ' vep-competition-clickable' : ''}" data-competition-id="${escapeHtml(competition.id)}">
                        <div class="vep-competition-display-title">${escapeHtml(competition.title || '')}</div>
                        ${competition.description ? '<div class="vep-competition-display-description">' + escapeHtml(competition.description) + '</div>' : ''}
                    </div>
                `;
            });
            html += '</div>';

            listElement.innerHTML = html;

            // Distribute cards evenly across the full width when fewer than 4
            const grid = listElement.querySelector('.vep-competitions-grid');
            if (grid) {
                const cols = Math.min(winnersOnly.length, 4);
                grid.style.gridTemplateColumns = `repeat(${cols}, 1fr)`;
            }
        }

        // Single delegated click handler (attached once, not per render).
        const competitionsListEl = document.getElementById('vep-competitions-list');
        if (competitionsListEl) {
            competitionsListEl.addEventListener('click', function(e) {
                const card = e.target.closest('.vep-competition-clickable');
                if (!card) return;
                const idx = winnersForDisplay.findIndex(c => String(c.id) === card.dataset.competitionId);
                if (idx !== -1) navigate('showWinner', idx);
            });
        }

        function showWinnerView(index, opts) {
            opts = opts || {};
            const competition = winnersForDisplay[index];
            if (!competition) return;

            currentWinnerIndex = index;
            currentDisplayView = 'winner';
            stopCompetitionsRefresh();
            cleanupCountdownTimers();
            stopFireworks();

            if (winnerRevealTimeout) {
                clearTimeout(winnerRevealTimeout);
                winnerRevealTimeout = null;
            }

            // Show competition title as subheading, hide others
            const winnerHeadingEl = document.getElementById('vep-display-winner-heading');
            if (winnerHeadingEl) {
                winnerHeadingEl.textContent = competition.title || '';
                winnerHeadingEl.style.display = '';
            }
            const compHeadingEl = document.getElementById('vep-display-competitions-heading');
            if (compHeadingEl) compHeadingEl.style.display = 'none';
            const timeUpEl = document.getElementById('vep-display-time-up');
            if (timeUpEl) timeUpEl.style.display = 'none';

            // Switch to winner view
            switchView('vep-winner-view', 'vep-winner-actions');

            // Update navigation buttons for this position
            updateWinnerButtons(index, winnersForDisplay.length);

            const winnerNameEl = document.getElementById('vep-winner-name');
            if (winnerNameEl) {
                winnerNameEl.textContent = competition.winner_name || '';
            }

            // Restoring a view (reopened screen): show the name directly, no
            // reveal delay and no fireworks.
            if (opts.instant) {
                if (winnerNameEl) {
                    winnerNameEl.style.transition = 'none';
                    winnerNameEl.style.opacity = '1';
                }
                return;
            }

            // Set winner name hidden initially
            if (winnerNameEl) {
                winnerNameEl.style.opacity = '0';
                winnerNameEl.style.transition = 'none';
            }

            // Fade in winner name after 2 seconds
            winnerRevealTimeout = setTimeout(() => {
                winnerRevealTimeout = null;
                if (winnerNameEl) {
                    winnerNameEl.style.transition = 'opacity 1s ease-in';
                    winnerNameEl.style.opacity = '1';
                }
                // Start fireworks when the winner's name is revealed
                startFireworks();
            }, 2000);
        }

        function updateWinnerButtons(index, total) {
            const backBtn  = document.getElementById('vep-winner-back-to-list');
            const prevBtn  = document.getElementById('vep-winner-prev');
            const nextBtn  = document.getElementById('vep-winner-next');
            const finishBtn = document.getElementById('vep-winner-finish');

            if (backBtn) {
                backBtn.textContent = index === 0
                    ? (vepAdmin.i18n.winnerBackFirst || 'Tilbage til konkurrence oversigt')
                    : (vepAdmin.i18n.winnerBackOther || 'Konkurrence oversigt');
            }

            if (prevBtn)   prevBtn.style.display   = index > 0                ? '' : 'none';
            if (nextBtn)   nextBtn.style.display   = index < total - 1        ? '' : 'none';
            if (finishBtn) finishBtn.style.display = index === total - 1      ? '' : 'none';
        }

        function showClosingView() {
            stopCompetitionsRefresh();
            cleanupCountdownTimers();
            stopFireworks();
            currentDisplayView = 'closing';

            if (winnerRevealTimeout) {
                clearTimeout(winnerRevealTimeout);
                winnerRevealTimeout = null;
            }

            // Hide all subheadings
            const winnerHeadingEl = document.getElementById('vep-display-winner-heading');
            if (winnerHeadingEl) winnerHeadingEl.style.display = 'none';
            const compHeadingEl = document.getElementById('vep-display-competitions-heading');
            if (compHeadingEl) compHeadingEl.style.display = 'none';
            const timeUpEl = document.getElementById('vep-display-time-up');
            if (timeUpEl) timeUpEl.style.display = 'none';

            // Populate closing text from localized option
            const closingTextEl = document.getElementById('vep-closing-text');
            if (closingTextEl) {
                closingTextEl.textContent = (vepAdmin && vepAdmin.closingText) ? vepAdmin.closingText : 'Tak for denne gang';
            }

            // Switch to closing view
            switchView('vep-closing-view', 'vep-closing-actions');

            // Toggle back buttons based on showCompetitions setting
            const compsEnabled = vepAdmin ? !!vepAdmin.showCompetitions : true;
            const backToCompsBtn = document.getElementById('vep-closing-back-to-competitions');
            const backToStatsBtn = document.getElementById('vep-closing-back-to-statistics');
            if (backToCompsBtn) backToCompsBtn.style.display = compsEnabled ? '' : 'none';
            if (backToStatsBtn) backToStatsBtn.style.display = !compsEnabled ? '' : 'none';

        }

        function updateCompetitionButtonVisibility() {
            const hasWinners = winnersForDisplay.length > 0;
            const compsEnabled = vepAdmin ? !!vepAdmin.showCompetitions : true;
            const visible = hasWinners && compsEnabled;
            const showCompBtn = document.getElementById('vep-show-competitions');
            const showCompFromStatsBtn = document.getElementById('vep-show-competitions-from-statistics');
            const showFirstWinnerBtn = document.getElementById('vep-show-first-winner');
            const showClosingFromCountdownBtn = document.getElementById('vep-show-closing-from-countdown');
            const showClosingFromCompetitionsBtn = document.getElementById('vep-show-closing-from-competitions');
            if (showCompBtn) showCompBtn.style.display = visible ? '' : 'none';
            if (showCompFromStatsBtn) showCompFromStatsBtn.style.display = visible ? '' : 'none';
            if (showFirstWinnerBtn) showFirstWinnerBtn.style.display = hasWinners ? '' : 'none';
            if (showClosingFromCountdownBtn) showClosingFromCountdownBtn.style.display = !visible ? '' : 'none';
            if (showClosingFromCompetitionsBtn) showClosingFromCompetitionsBtn.style.display = !hasWinners ? '' : 'none';
            // Show Afslut on statistics view when competition buttons are hidden
            const closingFromStatsBtn = document.getElementById('vep-show-closing-from-statistics');
            if (closingFromStatsBtn) closingFromStatsBtn.style.display = !visible ? '' : 'none';
        }

        function prefetchCompetitions() {
            // Refreshes winnersForDisplay (button visibility) in the background.
            loadCompetitions().catch(() => {});
        }

        function stopFireworks() {
            if (fireworksAnimation) {
                cancelAnimationFrame(fireworksAnimation);
                fireworksAnimation = null;
            }
            if (fireworksStopTimeout) {
                clearTimeout(fireworksStopTimeout);
                fireworksStopTimeout = null;
            }
            const fwCanvas = document.getElementById('vep-fireworks-canvas');
            if (fwCanvas) fwCanvas.style.display = 'none';
        }

        function startFireworks() {
            const canvas = document.getElementById('vep-fireworks-canvas');
            if (!canvas) {
                console.error('Fireworks canvas not found');
                return;
            }
            
            const ctx = canvas.getContext('2d');
            
            // Set canvas size to match window
            function resizeCanvas() {
                canvas.width = window.innerWidth;
                canvas.height = window.innerHeight;
            }
            resizeCanvas();
            
            // Show canvas
            canvas.style.display = 'block';
            console.log('Fireworks started!');

            // Auto-stop after 2 minutes regardless of which view is showing.
            if (fireworksStopTimeout) clearTimeout(fireworksStopTimeout);
            fireworksStopTimeout = setTimeout(stopFireworks, 120000);
            
            const particles = [];
            const particleCount = 100;
            
            class Particle {
                constructor(x, y, color) {
                    this.x = x;
                    this.y = y;
                    this.color = color;
                    this.velocity = {
                        x: (Math.random() - 0.5) * 10,
                        y: (Math.random() - 0.5) * 10
                    };
                    this.alpha = 1;
                    this.decay = Math.random() * 0.02 + 0.01;
                    this.size = Math.random() * 3 + 2;
                }
                
                update() {
                    this.velocity.y += 0.1; // Gravity
                    this.x += this.velocity.x;
                    this.y += this.velocity.y;
                    this.alpha -= this.decay;
                }
                
                draw() {
                    ctx.save();
                    ctx.globalAlpha = this.alpha;
                    ctx.fillStyle = this.color;
                    ctx.beginPath();
                    ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
                    ctx.fill();
                    ctx.restore();
                }
            }
            
            function createFirework(x, y) {
                const colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff', '#ffa500', '#ffffff'];
                const color = colors[Math.floor(Math.random() * colors.length)];
                
                for (let i = 0; i < particleCount; i++) {
                    particles.push(new Particle(x, y, color));
                }
            }
            
            function animate() {
                // Clear the canvas fully so the chosen background shows through unchanged.
                // Particle trails are handled by each particle's own alpha decay.
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                
                // Update and draw particles
                for (let i = particles.length - 1; i >= 0; i--) {
                    particles[i].update();
                    particles[i].draw();
                    
                    if (particles[i].alpha <= 0) {
                        particles.splice(i, 1);
                    }
                }
                
                // Create new fireworks randomly
                if (Math.random() < 0.05) {
                    const x = Math.random() * canvas.width;
                    const y = Math.random() * (canvas.height / 2);
                    createFirework(x, y);
                }
                
                fireworksAnimation = requestAnimationFrame(animate);
            }
            
            // Create initial burst
            for (let i = 0; i < 5; i++) {
                setTimeout(() => {
                    const x = Math.random() * canvas.width;
                    const y = Math.random() * (canvas.height / 2);
                    createFirework(x, y);
                }, i * 200);
            }
            
            animate();
        }
    }

    function initDisplayBackgroundSettings() {
        const backgroundTypeSelect = document.getElementById('vep_background_type');
        if (!backgroundTypeSelect) {
            return;
        }

        const solidRow = document.getElementById('vep-background-solid-row');
        const gradientRow = document.getElementById('vep-background-gradient-row');
        const previewElement = document.getElementById('vep-background-preview');
        const gradientStopsContainer = document.getElementById('vep-gradient-stops');
        const solidColorInput = document.getElementById('vep_background_solid_color');
        const resetButton = document.getElementById('vep-background-reset');
        const gradientAngleInput = document.getElementById('vep_background_gradient_angle');
        const gradientAngleValue = document.getElementById('vep-gradient-angle-value');
        const gradientAngleRow = document.getElementById('vep-background-gradient-angle-row');
        const textColorInput = document.getElementById('vep_display_text_color');
        const advancedToggleButton = document.getElementById('vep-toggle-advanced-settings');
        const advancedRows = Array.from(document.querySelectorAll('.vep-display-advanced-row'));
        let isAdvancedVisible = false;

        const updateRangeLabels = () => {
            if (!gradientStopsContainer) {
                return;
            }

            gradientStopsContainer.querySelectorAll('.vep-gradient-stop').forEach((stopRow) => {
                const range = stopRow.querySelector('.vep-gradient-stop-range');
                const valueLabel = stopRow.querySelector('.vep-gradient-stop-value');
                if (range && valueLabel) {
                    valueLabel.textContent = `${range.value}%`;
                }
            });
        };

        const updateBackgroundRows = () => {
            if (!isAdvancedVisible) {
                if (solidRow) {
                    solidRow.style.display = 'none';
                }
                if (gradientRow) {
                    gradientRow.style.display = 'none';
                }
                if (gradientAngleRow) {
                    gradientAngleRow.style.display = 'none';
                }
                return;
            }

            const isSolid = backgroundTypeSelect.value === 'solid';
            if (solidRow) {
                solidRow.style.display = isSolid ? '' : 'none';
            }
            if (gradientRow) {
                gradientRow.style.display = isSolid ? 'none' : '';
            }
            if (gradientAngleRow) {
                gradientAngleRow.style.display = isSolid ? 'none' : '';
            }
        };

        const updateAdvancedToggleLabel = () => {
            if (!advancedToggleButton) {
                return;
            }

            const showLabel = advancedToggleButton.getAttribute('data-label-show') || 'Advanced';
            const hideLabel = advancedToggleButton.getAttribute('data-label-hide') || 'Hide Advanced';
            advancedToggleButton.textContent = isAdvancedVisible ? hideLabel : showLabel;
            advancedToggleButton.setAttribute('aria-expanded', isAdvancedVisible ? 'true' : 'false');
        };

        const setAdvancedVisibility = (visible) => {
            isAdvancedVisible = visible;

            advancedRows.forEach((row) => {
                row.style.display = isAdvancedVisible ? '' : 'none';
            });

            updateBackgroundRows();
            updateAdvancedToggleLabel();
        };

        const updateAngleLabel = () => {
            if (gradientAngleInput && gradientAngleValue) {
                gradientAngleValue.textContent = `${gradientAngleInput.value}deg`;
            }
        };

        const buildGradientPreview = () => {
            if (!gradientStopsContainer) {
                return '';
            }

            const rows = Array.from(gradientStopsContainer.querySelectorAll('.vep-gradient-stop'));
            const parts = rows.map((row) => {
                const colorInput = row.querySelector('.vep-gradient-color');
                const rangeInput = row.querySelector('.vep-gradient-stop-range');
                const color = colorInput ? colorInput.value : '#000000';
                const stop = rangeInput ? Math.min(100, Math.max(0, parseInt(rangeInput.value, 10) || 0)) : 0;
                return `${color} ${stop}%`;
            });

            const angle = gradientAngleInput ? Math.min(360, Math.max(0, parseInt(gradientAngleInput.value, 10) || 0)) : 135;

            return `linear-gradient(${angle}deg, ${parts.join(', ')})`;
        };

        const updatePreview = () => {
            if (!previewElement) {
                return;
            }

            if (backgroundTypeSelect.value === 'solid') {
                previewElement.style.background = solidColorInput ? solidColorInput.value : '#1e3c72';
                return;
            }

            previewElement.style.background = buildGradientPreview();
        };

        const swapStopValues = (fromRow, toRow) => {
            const fromColor = fromRow.querySelector('.vep-gradient-color');
            const fromRange = fromRow.querySelector('.vep-gradient-stop-range');
            const toColor = toRow.querySelector('.vep-gradient-color');
            const toRange = toRow.querySelector('.vep-gradient-stop-range');

            if (!fromColor || !fromRange || !toColor || !toRange) {
                return;
            }

            const colorTmp = fromColor.value;
            const rangeTmp = fromRange.value;

            fromColor.value = toColor.value;
            fromRange.value = toRange.value;
            toColor.value = colorTmp;
            toRange.value = rangeTmp;
        };

        backgroundTypeSelect.addEventListener('change', () => {
            updateBackgroundRows();
            updatePreview();
        });

        if (solidColorInput) {
            solidColorInput.addEventListener('input', updatePreview);
            solidColorInput.addEventListener('change', updatePreview);
        }

        if (textColorInput) {
            textColorInput.addEventListener('input', updatePreview);
            textColorInput.addEventListener('change', updatePreview);
        }

        if (gradientStopsContainer) {
            gradientStopsContainer.querySelectorAll('.vep-gradient-stop-range').forEach((rangeInput) => {
                rangeInput.addEventListener('input', () => {
                    updateRangeLabels();
                    updatePreview();
                });
                rangeInput.addEventListener('change', () => {
                    updateRangeLabels();
                    updatePreview();
                });
            });

            gradientStopsContainer.querySelectorAll('.vep-gradient-color').forEach((colorInput) => {
                colorInput.addEventListener('input', updatePreview);
                colorInput.addEventListener('change', updatePreview);
            });

            gradientStopsContainer.addEventListener('click', (event) => {
                const target = event.target;
                if (!(target instanceof HTMLElement)) {
                    return;
                }

                const currentStop = target.closest('.vep-gradient-stop');
                if (!currentStop) {
                    return;
                }

                const allStops = Array.from(gradientStopsContainer.querySelectorAll('.vep-gradient-stop'));
                const index = allStops.indexOf(currentStop);
                if (index === -1) {
                    return;
                }

                if (target.classList.contains('vep-gradient-move-up') && index > 0) {
                    swapStopValues(currentStop, allStops[index - 1]);
                    updateRangeLabels();
                    updatePreview();
                }

                if (target.classList.contains('vep-gradient-move-down') && index < allStops.length - 1) {
                    swapStopValues(currentStop, allStops[index + 1]);
                    updateRangeLabels();
                    updatePreview();
                }
            });
        }

        if (gradientAngleInput) {
            gradientAngleInput.addEventListener('input', () => {
                updateAngleLabel();
                updatePreview();
            });
            gradientAngleInput.addEventListener('change', () => {
                updateAngleLabel();
                updatePreview();
            });
        }

        if (resetButton) {
            resetButton.addEventListener('click', () => {
                backgroundTypeSelect.value = resetButton.getAttribute('data-default-type') || 'gradient';

                if (solidColorInput) {
                    solidColorInput.value = resetButton.getAttribute('data-default-solid-color') || '#1e3c72';
                }

                if (textColorInput) {
                    textColorInput.value = resetButton.getAttribute('data-default-text-color') || '#ffffff';
                }

                if (gradientStopsContainer) {
                    const rows = Array.from(gradientStopsContainer.querySelectorAll('.vep-gradient-stop'));

                    const defaultColors = [
                        resetButton.getAttribute('data-default-gradient-color-1') || '#db870f',
                        resetButton.getAttribute('data-default-gradient-color-2') || '#db870f',
                        resetButton.getAttribute('data-default-gradient-color-3') || '#b014db'
                    ];

                    const defaultStops = [
                        resetButton.getAttribute('data-default-gradient-stop-1') || '0',
                        resetButton.getAttribute('data-default-gradient-stop-2') || '50',
                        resetButton.getAttribute('data-default-gradient-stop-3') || '100'
                    ];

                    if (gradientAngleInput) {
                        gradientAngleInput.value = resetButton.getAttribute('data-default-gradient-angle') || '128';
                    }

                    rows.forEach((row, index) => {
                        const colorInput = row.querySelector('.vep-gradient-color');
                        const rangeInput = row.querySelector('.vep-gradient-stop-range');

                        if (colorInput && defaultColors[index] !== undefined) {
                            colorInput.value = defaultColors[index];
                        }

                        if (rangeInput && defaultStops[index] !== undefined) {
                            rangeInput.value = defaultStops[index];
                        }
                    });
                }

                updateBackgroundRows();
                updateRangeLabels();
                updateAngleLabel();
                updatePreview();
            });
        }

        if (advancedToggleButton) {
            advancedToggleButton.addEventListener('click', () => {
                setAdvancedVisibility(!isAdvancedVisible);
            });
        }

        setAdvancedVisibility(false);
        updateRangeLabels();
        updateAngleLabel();
        updatePreview();
    }

    function initCompetitionsPage() {
        const page = document.querySelector('.vep-competitions-page');
        if (!page || typeof vepAdmin === 'undefined') {
            return;
        }

        const ajaxUrl = vepAdmin.ajaxUrl;
        const nonce = page.dataset.competitionNonce || vepAdmin.competitionNonce;
        const i18n = vepAdmin.i18n || {};
        const activeGrid = window.jQuery ? window.jQuery('#vep-active-competitions') : null;

        const showError = (message) => {
            window.alert(message || i18n.competitionActionFailed || 'An error occurred. Please try again.');
        };

        const showCardNotice = (sourceElement, message, type = 'success') => {
            const card = sourceElement ? sourceElement.closest('.vep-competition-card') : null;
            if (!card) {
                return;
            }

            const oldNotice = card.querySelector('.vep-competition-toast');
            if (oldNotice) {
                oldNotice.remove();
            }

            const toast = document.createElement('div');
            toast.className = `vep-competition-toast vep-competition-toast-${type}`;
            toast.textContent = message;
            card.appendChild(toast);

            // Trigger transition after insertion.
            window.requestAnimationFrame(() => {
                toast.classList.add('is-visible');
            });

            window.setTimeout(() => {
                toast.classList.remove('is-visible');
                window.setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 220);
            }, 1600);
        };

        const reloadPage = () => {
            window.location.reload();
        };

        const renumberCards = () => {
            const cards = document.querySelectorAll('#vep-active-competitions .vep-competition-card');
            cards.forEach((card, index) => {
                const number = card.querySelector('.vep-competition-number');
                if (number) {
                    number.textContent = String(index + 1);
                }
            });
        };

        const postAction = (action, payload = {}) => {
            const formData = new FormData();
            formData.append('action', action);
            formData.append('nonce', nonce);

            Object.entries(payload).forEach(([key, value]) => {
                if (Array.isArray(value)) {
                    value.forEach((item) => formData.append(`${key}[]`, item));
                    return;
                }

                if (value !== undefined && value !== null) {
                    formData.append(key, value);
                }
            });

            return fetch(ajaxUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin'
            }).then((response) => response.json());
        };

        if (activeGrid && activeGrid.length && typeof activeGrid.sortable === 'function') {
            activeGrid.sortable({
                items: '> .vep-competition-card',
                handle: '.vep-competition-drag-handle',
                placeholder: 'vep-sortable-ghost',
                tolerance: 'pointer',
                helper: function(event, item) {
                    const helper = item.clone();
                    helper.addClass('vep-sortable-helper');
                    helper.css('width', item.outerWidth());
                    return helper;
                },
                forceHelperSize: true,
                forcePlaceholderSize: true,
                start: function(event, ui) {
                    this.classList.add('vep-competitions-grid-sorting');
                    ui.placeholder.height(ui.item.outerHeight());
                    ui.placeholder.width(ui.item.outerWidth());
                },
                stop: function() {
                    this.classList.remove('vep-competitions-grid-sorting');
                },
                update: function() {
                    const order = Array.from(document.querySelectorAll('#vep-active-competitions .vep-competition-card'))
                        .map((card) => card.dataset.competitionId)
                        .filter(Boolean);

                    renumberCards();

                    if (order.length === 0) {
                        return;
                    }

                    postAction('vep_reorder_competitions', { order })
                        .then((data) => {
                            if (!data.success) {
                                showError((data.data && data.data.message) || i18n.reorderCompetitionFailed);
                                reloadPage();
                            }
                        })
                        .catch(() => {
                            showError(i18n.reorderCompetitionFailed);
                            reloadPage();
                        });
                }
            });
        }

        document.querySelectorAll('.vep-toggle-active').forEach((button) => {
            button.addEventListener('click', function() {
                const competitionId = this.dataset.competitionId;
                const actionType = this.dataset.action;

                this.disabled = true;

                postAction('vep_toggle_competition_active', {
                    competition_id: competitionId,
                    action_type: actionType
                })
                    .then((data) => {
                        if (!data.success) {
                            this.disabled = false;
                            showError(data.data && data.data.message);
                            return;
                        }

                        reloadPage();
                    })
                    .catch(() => {
                        this.disabled = false;
                        showError(i18n.competitionActionFailed);
                    });
            });
        });

        document.querySelectorAll('.vep-delete-competition').forEach((button) => {
            button.addEventListener('click', function() {
                if (!window.confirm(i18n.confirmCompetitionDelete || 'Are you sure?')) {
                    return;
                }

                const competitionId = this.dataset.competitionId;
                this.disabled = true;

                postAction('vep_delete_competition', {
                    competition_id: competitionId
                })
                    .then((data) => {
                        if (!data.success) {
                            this.disabled = false;
                            showError(data.data && data.data.message);
                            return;
                        }

                        reloadPage();
                    })
                    .catch(() => {
                        this.disabled = false;
                        showError(i18n.competitionActionFailed);
                    });
            });
        });

        document.querySelectorAll('.vep-reset-winner').forEach((button) => {
            button.addEventListener('click', function() {
                const competitionId = this.dataset.competitionId;
                const buttonNonce = this.dataset.nonce;

                if (!competitionId || !buttonNonce) {
                    showError(i18n.competitionActionFailed);
                    return;
                }

                this.disabled = true;

                const formData = new FormData();
                formData.append('action', 'vep_set_competition_winner');
                formData.append('competition_id', competitionId);
                formData.append('winner_id', '');
                formData.append('nonce', buttonNonce);

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (!data.success) {
                            this.disabled = false;
                            showError((data.data && data.data.message) || i18n.competitionActionFailed);
                            return;
                        }

                        showCardNotice(this, i18n.winnerReset || 'Winner reset successfully', 'success');
                        reloadPage();
                    })
                    .catch(() => {
                        this.disabled = false;
                        showError(i18n.competitionActionFailed);
                    });
            });
        });

        // Handle competition winner selection for custom competitions
        document.querySelectorAll('.vep-competition-winner-select').forEach((select) => {            select.addEventListener('change', function() {
                const competitionId = this.dataset.competitionId;
                const winnerId = this.value;
                const nonce = this.dataset.nonce;

                const formData = new FormData();
                formData.append('action', 'vep_set_competition_winner');
                formData.append('competition_id', competitionId);
                formData.append('winner_id', winnerId);
                formData.append('nonce', nonce);

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (!data.success) {
                            showError((data.data && data.data.message) || i18n.competitionActionFailed);
                            reloadPage();
                            return;
                        }

                        const successMessage = (data.data && data.data.message) || i18n.winnerSaved || 'Saved.';
                        showCardNotice(this, successMessage, 'success');

                        // Update no-winner marker
                        const card = this.closest('.vep-competition-card');
                        if (card) {
                            card.classList.toggle('vep-competition-no-winner', winnerId === '');
                        }

                        // Re-initialize Choices if available
                        if (typeof Choices !== 'undefined' && this.choicesInstance) {
                            this.choicesInstance.destroy();
                            this.choicesInstance = new Choices(this, {
                                searchEnabled: true,
                                searchFields: ['label'],
                                searchPlaceholderValue: (typeof vepAdmin !== 'undefined' && vepAdmin.choicesI18n) ? vepAdmin.choicesI18n.searchPlaceholderValue : 'Search...',
                                itemSelectText: (typeof vepAdmin !== 'undefined' && vepAdmin.choicesI18n) ? vepAdmin.choicesI18n.itemSelectText : 'Press to select',
                                noResultsText: (typeof vepAdmin !== 'undefined' && vepAdmin.choicesI18n) ? vepAdmin.choicesI18n.noResultsText : 'No results found',
                                noChoicesText: (typeof vepAdmin !== 'undefined' && vepAdmin.choicesI18n) ? vepAdmin.choicesI18n.noChoicesText : 'No options available',
                                removeItemButton: false,
                                shouldSort: false
                            });
                        }
                    })
                    .catch(() => {
                        showError(i18n.competitionActionFailed);
                        reloadPage();
                    });
            });
        });

        renumberCards();

        // Handle free-text winner save for text-type custom competitions
        document.querySelectorAll('.vep-competition-winner-text-save').forEach((button) => {            button.addEventListener('click', function() {
                const competitionId = this.dataset.competitionId;
                const wrap = this.closest('.vep-competition-winner-text-wrap');
                const input = wrap ? wrap.querySelector('.vep-competition-winner-text') : null;

                if (!input) {
                    return;
                }

                const winnerText = input.value;
                const formData = new FormData();
                formData.append('action', 'vep_set_competition_winner_text');
                formData.append('competition_id', competitionId);
                formData.append('winner_text', winnerText);
                formData.append('nonce', input.dataset.nonce);

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                    .then((response) => response.json())
                    .then((data) => {
                        const msg = (data.data && data.data.message) || (data.success ? 'Saved.' : 'Error');
                        showCardNotice(this, msg, data.success ? 'success' : 'error');

                        if (data.success) {
                            // Update no-winner marker
                            const card = this.closest('.vep-competition-card');
                            if (card) {
                                card.classList.toggle('vep-competition-no-winner', winnerText.trim() === '');
                            }
                        }
                    })
                    .catch(() => {
                        showCardNotice(this, 'Error', 'error');
                    });
            });
        });

        // Handle reset all winners button
        const resetAllBtn = document.querySelector('.vep-reset-all-winners');
        if (resetAllBtn) {
            resetAllBtn.addEventListener('click', function() {
                const confirmMsg = i18n.resetAllWinnersConfirm || 'Are you sure you want to reset all winners? This cannot be undone.';
                if (!window.confirm(confirmMsg)) {
                    return;
                }

                const formData = new FormData();
                formData.append('action', 'vep_reset_all_winners');
                formData.append('nonce', this.dataset.nonce);

                fetch(ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                    .then((response) => response.json())
                    .then((data) => {
                        if (data.success) {
                            reloadPage();
                        } else {
                            showError((data.data && data.data.message) || i18n.competitionActionFailed);
                        }
                    })
                    .catch(() => {
                        showError(i18n.competitionActionFailed);
                    });
            });
        }
    }

    function initAdminChoices() {
        if (typeof Choices === 'undefined') {
            return;
        }

        const i18n = (typeof vepAdmin !== 'undefined' && vepAdmin.choicesI18n) ? vepAdmin.choicesI18n : {};
        const selectElements = document.querySelectorAll('.vep-choices');

        selectElements.forEach((element) => {
            if (element.choicesInstance) {
                return;
            }

            element.choicesInstance = new Choices(element, {
                searchEnabled: true,
                searchFields: ['label'],
                searchPlaceholderValue: i18n.searchPlaceholderValue || 'Search...',
                itemSelectText: i18n.itemSelectText || 'Press to select',
                noResultsText: i18n.noResultsText || 'No results found',
                noChoicesText: i18n.noChoicesText || 'No options available',
                removeItemButton: false,
                shouldSort: false
            });
        });
    }

    /**
     * Collapsible Display Settings card.
     */
    function initDisplaySettingsToggle() {
        const toggle = document.getElementById('vep-display-settings-toggle');
        const body   = document.getElementById('vep-display-settings-body');
        const caret  = document.getElementById('vep-display-settings-caret');
        if (!toggle || !body) return;

        toggle.addEventListener('click', function() {
            const collapsed = body.style.display === 'none';
            body.style.display = collapsed ? '' : 'none';
            if (caret) caret.style.transform = collapsed ? '' : 'rotate(-90deg)';
            toggle.setAttribute('aria-expanded', String(collapsed));
        });
    }

    /** Collapse or expand the settings card (called by nav box polling). */
    function setSettingsCollapsed(collapsed) {
        const card = document.getElementById('vep-display-settings-card');
        if (card) card.style.display = collapsed ? 'none' : '';
    }

    /**
     * Visningsnavigation — admin-side polling and button handlers.
     * Polls the (read-only) presentation state every 1s and shows/hides the nav box
     * depending on whether any display screen has a recent heartbeat.
     */
    function initDisplayNavBox() {
        const navBox = document.getElementById('vep-nav-box');
        const startBtn = document.getElementById('vep-start-event-display');
        if (!navBox || !startBtn || typeof vepAdmin === 'undefined') return;

        const eventId = startBtn.dataset.eventId;
        if (!eventId) return;

        let navPollInterval = null;
        let lastState = null;

        // All action-group divs keyed by view name
        const actionGroups = {
            countdown:    document.getElementById('vep-nav-countdown-actions'),
            statistics:   document.getElementById('vep-nav-statistics-actions'),
            competitions: document.getElementById('vep-nav-competitions-actions'),
            winner:       document.getElementById('vep-nav-winner-actions'),
            closing:      document.getElementById('vep-nav-closing-actions'),
        };
        const statusEl = document.getElementById('vep-nav-status');

        function hideAllGroups() {
            Object.values(actionGroups).forEach(el => { if (el) el.style.display = 'none'; });
        }

        function updateNavBox(state) {
            hideAllGroups();

            const formatWinnerNavLabel = function(prefix, title) {
                return title ? `${prefix} - ${title}` : prefix;
            };

            const group = actionGroups[state.view];
            if (group) group.style.display = '';

            // Statistics: always show back-to-countdown when in statistics view
            const backCountdownBtn = navBox.querySelector('.vep-nav-btn-back-countdown');
            if (backCountdownBtn) {
                backCountdownBtn.style.display = state.view === 'statistics' ? '' : 'none';
            }

            // Competition buttons: hide in nav box when showCompetitions setting is off
            const compsEnabled = vepAdmin ? !!vepAdmin.showCompetitions : true;
            const hasCompetitionWinners = Array.isArray(state.competitions) && state.competitions.length > 0;
            navBox.querySelectorAll('.vep-nav-btn-competitions').forEach(btn => {
                const shouldShow = compsEnabled && (
                    (state.view !== 'statistics' && state.view !== 'countdown') || hasCompetitionWinners
                );
                btn.style.display = shouldShow ? '' : 'none';
            });
            const navClosingFromCountdownBtn = navBox.querySelector('.vep-nav-btn-closing-from-countdown');
            if (navClosingFromCountdownBtn) {
                navClosingFromCountdownBtn.style.display = (state.view === 'countdown' && (!compsEnabled || !hasCompetitionWinners)) ? '' : 'none';
            }
            const navShowFirstWinnerBtn = document.getElementById('vep-nav-show-first-winner');
            if (navShowFirstWinnerBtn) {
                navShowFirstWinnerBtn.style.display = (state.view === 'competitions' && compsEnabled && hasCompetitionWinners) ? '' : 'none';
                const firstCompetitionTitle = hasCompetitionWinners && state.competitions[0] ? state.competitions[0].title : '';
                navShowFirstWinnerBtn.textContent = formatWinnerNavLabel(
                    (vepAdmin.i18n.winnerFirst || 'Første vinder'),
                    firstCompetitionTitle || ''
                );
            }
            const navClosingFromCompetitionsBtn = navBox.querySelector('.vep-nav-btn-closing-from-competitions');
            if (navClosingFromCompetitionsBtn) {
                navClosingFromCompetitionsBtn.style.display = (state.view === 'competitions' && !hasCompetitionWinners) ? '' : 'none';
            }
            // Afslut button in statistics nav: show when competitions are hidden
            const navClosingBtn = navBox.querySelector('.vep-nav-btn-closing');
            if (navClosingBtn) {
                navClosingBtn.style.display = (state.view === 'statistics' && (!compsEnabled || !hasCompetitionWinners)) ? '' : 'none';
            }

            // Closing view: toggle back-to-competitions vs back-to-statistics
            const navClosingToComps = navBox.querySelector('.vep-nav-btn-closing-to-competitions');
            const navClosingToStats = navBox.querySelector('.vep-nav-btn-closing-to-statistics');
            if (navClosingToComps) navClosingToComps.style.display = compsEnabled ? '' : 'none';
            if (navClosingToStats) navClosingToStats.style.display = !compsEnabled ? '' : 'none';

            // Competitions: render clickable list of competition names
            const compListEl = document.getElementById('vep-nav-competitions-list');
            if (compListEl) {
                if (state.view === 'competitions' && Array.isArray(state.competitions) && state.competitions.length > 0) {
                    compListEl.innerHTML = state.competitions.map(c =>
                        `<button type="button" class="button vep-nav-btn vep-nav-competition-item" data-nav-action="showWinner" data-winner-index="${parseInt(c.index, 10) || 0}">${escapeHtml(c.title)}</button>`
                    ).join('');
                    compListEl.style.display = '';
                } else {
                    compListEl.innerHTML = '';
                    compListEl.style.display = 'none';
                }
            }

            // Winner: update prev/next/finish and their winner_index data attrs
            if (state.view === 'winner') {
                const idx = parseInt(state.winner_index, 10) || 0;
                const total = parseInt(state.total_winners, 10) || 1;
                const prevBtn   = document.getElementById('vep-nav-winner-prev');
                const nextBtn   = document.getElementById('vep-nav-winner-next');
                const finishBtn = document.getElementById('vep-nav-winner-finish');
                const competitions = Array.isArray(state.competitions) ? state.competitions : [];
                const nextCompetition = idx < total - 1 ? competitions[idx + 1] : null;

                if (prevBtn) {
                    prevBtn.style.display = idx > 0 ? '' : 'none';
                    prevBtn.dataset.winnerIndex = idx - 1;
                }
                if (nextBtn) {
                    nextBtn.style.display = idx < total - 1 ? '' : 'none';
                    nextBtn.dataset.winnerIndex = idx + 1;
                    nextBtn.textContent = formatWinnerNavLabel(
                        (vepAdmin.i18n.winnerNext || 'Næste vinder'),
                        nextCompetition && nextCompetition.title ? nextCompetition.title : ''
                    );
                }
                if (finishBtn) {
                    finishBtn.style.display = idx === total - 1 ? '' : 'none';
                }
            }

            // Status paragraph
            if (statusEl) {
                const labels = { countdown: 'Nedtælling', statistics: 'Statistik', competitions: 'Konkurrencer', winner: 'Vinder', closing: 'Afslutning' };
                statusEl.style.whiteSpace = 'pre-line';

                if (state.view === 'winner') {
                    const competitions = Array.isArray(state.competitions) ? state.competitions : [];
                    const idx = parseInt(state.winner_index, 10) || 0;
                    const currentCompetition = competitions[idx] || null;
                    const statusLines = [
                        'Aktiv visning: ' + formatWinnerNavLabel(
                            (labels[state.view] || state.view),
                            currentCompetition && currentCompetition.title ? currentCompetition.title : ''
                        )
                    ];

                    if (currentCompetition && currentCompetition.winner_name) {
                        statusLines.push(currentCompetition.winner_name);
                    }

                    statusEl.textContent = statusLines.join('\n');
                } else {
                    statusEl.textContent = 'Aktiv visning: ' + (labels[state.view] || state.view);
                }
            }
        }

        // Visible notice when the nonce/session expired (instead of silently freezing).
        let authNoticeEl = null;
        document.addEventListener('vep:auth-expired', function() {
            if (authNoticeEl) return;
            const i18n = window.vepDisplayI18n || {};
            authNoticeEl = document.createElement('div');
            authNoticeEl.className = 'notice notice-error inline';
            const text = document.createElement('p');
            text.textContent = i18n.sessionExpired || 'Your session has expired. Please reload the page to continue.';
            const reloadBtn = document.createElement('button');
            reloadBtn.type = 'button';
            reloadBtn.className = 'button';
            reloadBtn.style.marginLeft = '10px';
            reloadBtn.textContent = i18n.reload || 'Reload';
            reloadBtn.addEventListener('click', function() { window.location.reload(); });
            text.appendChild(reloadBtn);
            authNoticeEl.appendChild(text);
            navBox.parentNode.insertBefore(authNoticeEl, navBox);
        });

        function pollState() {
            vepAjax('vep_display_get_state', { event_id: eventId })
                .then(resp => {
                    if (!resp.success) return;
                    const state = resp.data;
                    if (!state || state.active === false) {
                        navBox.style.display = 'none';
                        hideAllGroups();
                        if (statusEl) statusEl.textContent = '';
                        if (lastState !== null) setSettingsCollapsed(false); // re-expand when display ends
                        lastState = null;
                        return;
                    }
                    if (lastState === null) setSettingsCollapsed(true); // collapse when display starts
                    navBox.style.display = '';
                    lastState = state;
                    updateNavBox(state);
                })
                .catch(() => {});
        }

        // Wire up button clicks — send command to display
        navBox.addEventListener('click', function(e) {
            const btn = e.target.closest('.vep-nav-btn');
            if (!btn) return;
            const navAction = btn.dataset.navAction;
            if (!navAction) return;

            const fields = { event_id: eventId, nav_action: navAction };
            if (btn.dataset.winnerIndex !== undefined) {
                fields.winner_index = btn.dataset.winnerIndex;
            }
            // The server increments the shared state's seq; refresh right away so
            // the box reflects the new view without waiting for the next poll.
            vepAjax('vep_display_send_command', fields).then(pollState).catch(() => {});
        });

        navPollInterval = setInterval(pollState, 1000);
        pollState(); // immediate first check

        // Reset button — clears display state transients so polling can recover
        const resetBtn = document.getElementById('vep-nav-reset');
        if (resetBtn) {
            resetBtn.addEventListener('click', function() {
                const confirmMsg = resetBtn.dataset.confirm || 'Are you sure?';
                if (!window.confirm(confirmMsg)) { return; }
                vepAjax('vep_display_clear_state', { event_id: eventId })
                    .then(() => {
                        navBox.style.display = 'none';
                        hideAllGroups();
                        if (statusEl) statusEl.textContent = '';
                        lastState = null;
                        setSettingsCollapsed(false);
                    })
                    .catch(() => {});
            });
        }
    }

    /**
     * Participant print modal.
     *
     * Opens a configuration modal when a .vep-print-btn row action is clicked.
     * The modal lets the user choose fields (sortable), layout, orientation,
     * paper size and whether to include a printable checkbox.
     * Clicking "Print" opens the generated print page in a new tab.
     */
    function initPrintModal() {
        const modal     = document.getElementById('vep-print-modal');
        const closeBtn  = document.getElementById('vep-print-modal-close');
        const cancelBtn = document.getElementById('vep-print-cancel');
        const submitBtn = document.getElementById('vep-print-submit');
        const openBtn   = document.getElementById('vep-print-selected-participants');
        const layoutSel = document.getElementById('vep-print-layout');
        const gridWrap  = document.getElementById('vep-print-grid-cols-wrap');
        const gridCols  = document.getElementById('vep-print-grid-cols');
        const fieldList = document.getElementById('vep-print-fields-list');

        if (!modal || typeof vepAdmin === 'undefined') return;

        // Activate jQuery UI Sortable on the field list.
        if (typeof jQuery !== 'undefined' && jQuery.fn.sortable) {
            jQuery(fieldList).sortable({
                handle: '.vep-drag-handle',
                axis: 'y',
                tolerance: 'pointer',
                placeholder: 'vep-sortable-placeholder'
            });
        }

        function getSelectedParticipantIds() {
            const checkboxes = document.querySelectorAll('input[name="participant[]"]:checked');
            const ids = [];

            checkboxes.forEach(function (checkbox) {
                const id = parseInt(checkbox.value, 10);
                if (id) {
                    ids.push(id);
                }
            });

            return ids;
        }

        // Open modal from the top-level print button.
        // Use event delegation so the listener survives AJAX DOM replacements.
        document.addEventListener('click', function (e) {
            if (!e.target.closest('#vep-print-selected-participants')) return;
            const selectedIds = getSelectedParticipantIds();
            if (!selectedIds.length) {
                window.alert(vepAdmin.i18n && vepAdmin.i18n.printNoSelection
                    ? vepAdmin.i18n.printNoSelection
                    : 'Select at least one participant in the table.');
                return;
            }

            if (layoutSel && gridWrap) {
                gridWrap.style.display = layoutSel.value === 'grid' ? 'flex' : 'none';
            }
            modal.style.display = 'flex';
            modal.querySelector('.vep-modal-container').focus();
        });

        // Show/hide grid columns input based on layout selection.
        if (layoutSel && gridWrap) {
            layoutSel.addEventListener('change', function () {
                gridWrap.style.display = this.value === 'grid' ? 'flex' : 'none';
            });
        }

        // Close helpers.
        function closeModal() {
            modal.style.display = 'none';
        }

        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);

        // Close on overlay click.
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });

        // Close on Escape.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && modal.style.display !== 'none') closeModal();
        });

        // Close if page is restored from bfcache (back/forward navigation).
        // Without this, the modal overlay stays visible and blocks all clicks.
        window.addEventListener('pageshow', function (e) {
            if (e.persisted) closeModal();
        });

        // Build print URL and open in new tab.
        if (submitBtn) {
            submitBtn.addEventListener('click', function () {
                const selectedIds = getSelectedParticipantIds();

                if (!selectedIds.length) {
                    window.alert(vepAdmin.i18n && vepAdmin.i18n.printNoSelection
                        ? vepAdmin.i18n.printNoSelection
                        : 'Select at least one participant in the table.');
                    return;
                }

                // Collect checked fields in DOM order (respects sortable).
                const checkedItems = fieldList.querySelectorAll('.vep-sortable-item');
                const fields = [];
                checkedItems.forEach(function (item) {
                    const cb = item.querySelector('.vep-print-field-cb');
                    if (cb && cb.checked) {
                        fields.push(item.dataset.field);
                    }
                });

                if (fields.length === 0) {
                    window.alert(vepAdmin.i18n && vepAdmin.i18n.printNoFields
                        ? vepAdmin.i18n.printNoFields
                        : 'Vælg mindst ét felt.');
                    return;
                }

                const layout      = document.getElementById('vep-print-layout').value;
                const orientation = document.getElementById('vep-print-orientation').value;
                const paper       = document.getElementById('vep-print-paper').value;
                const cols        = (gridCols && layout === 'grid') ? (parseInt(gridCols.value, 10) || 3) : 3;
                const showCb      = document.getElementById('vep-print-show-checkbox').checked ? '1' : '0';
                const allowCut    = document.getElementById('vep-print-allow-cut').checked ? '1' : '0';

                const params = new URLSearchParams({
                    action:      'vep_print_participant',
                    ids:         selectedIds.join(','),
                    nonce:       vepAdmin.printNonce,
                    fields:      fields.join(','),
                    layout:      layout,
                    orientation: orientation,
                    paper:       paper,
                    grid_cols:   cols,
                    show_checkbox: showCb,
                    allow_cut:   allowCut
                });

                window.open(vepAdmin.adminPostUrl + '?' + params.toString(), '_blank');
                closeModal();
            });
        }
    }

    function initPerPageControls() {
        const controls = document.querySelectorAll('.vep-per-page-select');

        if (!controls.length) return;

        controls.forEach(function (control) {
            control.addEventListener('change', function () {
                const form = control.closest('form');
                if (!form) {
                    return;
                }

                const hidden = form.querySelector('input[data-per-page-hidden="1"]');
                if (!hidden) {
                    return;
                }

                hidden.value = this.value;

                form.querySelectorAll('.vep-per-page-select').forEach(function (otherControl) {
                    if (otherControl !== control) {
                        otherControl.value = control.value;
                    }
                });

                form.submit();
            });
        });
    }

})();

// ─── Collapsible Sections ────────────────────────────────────────────────────
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.vep-collapsible-section').forEach(function (details) {
            var summary = details.querySelector('summary');
            if (!summary) { return; }

            summary.addEventListener('click', function (e) {
                e.preventDefault();
                if (e.target.classList.contains('vep-collapsible-heading')) {
                    details.open = !details.open;
                }
            });
        });
    });
}());

// ─── Agreement View Modal ────────────────────────────────────────────────────
(function () {
    'use strict';

    if (typeof vepAdmin === 'undefined') { return; }

    var ajaxUrl = vepAdmin.ajaxUrl;
    var nonce   = vepAdmin.nonce;
    var i18n    = vepAdmin.i18n || {};
    var overlay = document.getElementById('vep-agreement-view-modal-overlay');

    if (!overlay) { return; }

    function openModal() {
        overlay.style.display = '';
    }

    function closeModal() {
        overlay.style.display = 'none';
    }

    function showLoading() {
        var loading = document.getElementById('vep-agreement-view-modal-loading');
        var content = document.getElementById('vep-agreement-view-modal-content');
        if (loading) loading.style.display = 'flex';
        if (content) content.style.display = 'none';
    }

    function hideLoading() {
        var loading = document.getElementById('vep-agreement-view-modal-loading');
        var content = document.getElementById('vep-agreement-view-modal-content');
        if (loading) loading.style.display = 'none';
        if (content) content.style.display = '';
    }

    function setText(id, value) {
        var el = document.getElementById(id);
        if (el) { el.textContent = value || ''; }
    }

    window.vepOpenAgreementViewModal = function (agreementId) {
        agreementId = parseInt(agreementId, 10) || 0;
        if (agreementId <= 0) { return; }

        var titleEl = document.getElementById('vep-agreement-view-modal-title');
        if (titleEl) { titleEl.textContent = (i18n.agreement || 'Agreement') + ' #' + agreementId; }

        var editBtn = document.getElementById('vep-agreement-view-modal-edit-btn');
        if (editBtn) { editBtn.setAttribute('data-agreement-id', String(agreementId)); }

        showLoading();
        openModal();

        fetch(ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'vep_admin_get_agreement_data', nonce: nonce, id: String(agreementId) })
        })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) {
                    closeModal();
                    if (typeof window.vepToast === 'function') { window.vepToast(res.data && res.data.message ? res.data.message : 'Error', 'error'); }
                    return;
                }
                var d = res.data;
                setText('vep-agreement-view-p1-name',        d.participant1_name);
                setText('vep-agreement-view-p2-name',        d.participant2_name);
                setText('vep-agreement-view-initiator-name', d.initiator_name);
                setText('vep-agreement-view-description',    d.description);
                setText('vep-agreement-view-status',         d.status_label || d.status);
                setText('vep-agreement-view-created-at',     d.created_at_formatted || d.created_at);
                hideLoading();
            })
            .catch(function () {
                closeModal();
                if (typeof window.vepToast === 'function') { window.vepToast('Network error', 'error'); }
            });
    };

    document.addEventListener('DOMContentLoaded', function () {
        var closeBtn       = document.getElementById('vep-agreement-view-modal-close');
        var closeBtnFooter = document.getElementById('vep-agreement-view-modal-close-btn');
        var editBtn        = document.getElementById('vep-agreement-view-modal-edit-btn');

        if (closeBtn)       { closeBtn.addEventListener('click', closeModal); }
        if (closeBtnFooter) { closeBtnFooter.addEventListener('click', closeModal); }

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) { closeModal(); }
        });

        if (editBtn) {
            editBtn.addEventListener('click', function () {
                var id = parseInt(editBtn.getAttribute('data-agreement-id'), 10) || 0;
                closeModal();
                if (id > 0 && typeof window.vepOpenAgreementModal === 'function') {
                    window.vepOpenAgreementModal(id);
                }
            });
        }

        document.addEventListener('keydown', function (e) {
            if ('Escape' === e.key && overlay.style.display !== 'none') {
                closeModal();
            }
        });

        document.addEventListener('click', function (e) {
            var target = e.target.closest('[data-agreement-view-modal]');
            if (!target) { return; }
            e.preventDefault();
            var id = parseInt(target.getAttribute('data-agreement-view-modal'), 10) || 0;
            if (id > 0) {
                window.vepOpenAgreementViewModal(id);
            }
        });
    });

})();

// ─── Participant Agreements Table (AJAX) ─────────────────────────────────────
(function () {
    'use strict';

    var participantId = null;
    var eventId       = null;

    var state = {
        participant_agreement_status: '',
        orderby:     'id',
        order:       'desc',
        agreement_s: '',
    };

    function showSpinner() {
        var section = document.getElementById('vep-participant-agreements-section');
        if (!section) { return; }
        var overlay = section.querySelector('.vep-ajax-spinner-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'vep-ajax-spinner-overlay';
            overlay.innerHTML = '<div class="vep-ajax-spinner"></div>';
            section.style.position = 'relative';
            section.appendChild(overlay);
        }
        overlay.style.display = 'flex';
    }

    function hideSpinner() {
        var overlay = document.querySelector('#vep-participant-agreements-section .vep-ajax-spinner-overlay');
        if (overlay) { overlay.style.display = 'none'; }
    }

    function reload(overrides) {
        if (!participantId || typeof vepAdmin === 'undefined') { return; }

        var actionKeys = ['agreement_action', 'agreement_id', '_wpnonce'];
        var extra = {};

        if (overrides) {
            Object.keys(overrides).forEach(function (k) {
                if (actionKeys.indexOf(k) !== -1) {
                    extra[k] = overrides[k];
                } else {
                    state[k] = overrides[k];
                }
            });
        }

        showSpinner();

        var data = new URLSearchParams();
        data.append('action',         'vep_reload_participant_agreements_table');
        data.append('participant_id', participantId);
        data.append('event_id',       eventId || '0');
        Object.keys(state).forEach(function (k) { data.append(k, state[k]); });
        Object.keys(extra).forEach(function (k) { data.append(k, extra[k]); });

        fetch(vepAdmin.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (result) {
                hideSpinner();
                if (!result || !result.success) {
                    if (typeof window.vepToast === 'function') {
                        window.vepToast(result && result.data ? result.data : 'An error occurred.', 'error');
                    }
                    return;
                }
                if (!result.data || !result.data.html) { return; }
                var section = document.getElementById('vep-participant-agreements-section');
                if (!section) { return; }
                var temp = document.createElement('div');
                temp.innerHTML = result.data.html;
                var newSection = temp.querySelector('#vep-participant-agreements-section');
                if (newSection) {
                    section.parentNode.replaceChild(newSection, section);
                }
                if (result.data.message && typeof window.vepToast === 'function') {
                    window.vepToast(result.data.message);
                }
                init();
            })
            .catch(function () { hideSpinner(); });
    }

    function init() {
        var section = document.getElementById('vep-participant-agreements-section');
        if (!section) { return; }

        // Block form navigation; search triggers reload.
        var form = section.querySelector('#vep-participant-agreements-form');
        if (form) {
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var searchInput = section.querySelector('#vep-participant-agreements-search');
                reload({ agreement_s: searchInput ? searchInput.value : '' });
            });
        }

        // Status filter links.
        section.querySelectorAll('.subsubsub a').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var url = new URL(this.href);
                reload({ participant_agreement_status: url.searchParams.get('participant_agreement_status') || '' });
            });
        });

        // Column sort links.
        section.querySelectorAll('th.sortable a, th.sorted a').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var url = new URL(this.href);
                reload({
                    orderby: url.searchParams.get('orderby') || 'id',
                    order:   url.searchParams.get('order')   || 'desc',
                });
            });
        });

        // Delete row-action links.
        section.querySelectorAll('a[data-agreement-action="delete"]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var msg = link.dataset.confirmMsg || 'Are you sure you want to delete this agreement?';
                if (!window.confirm(msg)) { return; }
                var url = new URL(this.href);
                reload({
                    agreement_action: 'delete',
                    agreement_id:     url.searchParams.get('id'),
                    _wpnonce:         url.searchParams.get('_wpnonce'),
                });
            });
        });

        // Edit agreement via modal.
        section.querySelectorAll('a[data-agreement-edit-modal]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                var id = parseInt(this.dataset.agreementEditModal, 10);
                if (typeof window.vepOpenAgreementModal === 'function') {
                    window.vepOpenAgreementModal(id);
                }
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var q = new URLSearchParams(window.location.search);
        if (q.get('page') !== 'volunteer-exchange-events' || q.get('action') !== 'view-participant' || !q.get('id')) { return; }
        participantId = q.get('id');
        eventId       = q.get('return_id') || '0';
        if (q.get('participant_agreement_status')) { state.participant_agreement_status = q.get('participant_agreement_status'); }
        if (q.get('orderby'))                      { state.orderby     = q.get('orderby'); }
        if (q.get('order'))                        { state.order       = q.get('order'); }
        if (q.get('agreement_s'))                  { state.agreement_s = q.get('agreement_s'); }
        init();
        document.addEventListener('vep:reload-agreements', function () { reload(); });
    });

}());

/**
 * Event create/edit modal IIFE.
 * Exposes window.vepOpenEventModal(eventId).
 * eventId = 0 → Add New mode; eventId > 0 → Edit mode.
 */
(function () {
    'use strict';

    var i18n    = (typeof vepAdmin !== 'undefined' && vepAdmin.i18n) ? vepAdmin.i18n : {};
    var ajaxUrl = (typeof vepAdmin !== 'undefined') ? vepAdmin.ajaxUrl : ajaxurl;
    var nonce   = (typeof vepAdmin !== 'undefined') ? vepAdmin.nonce  : '';

    function openModal() {
        var overlay = document.getElementById('vep-event-modal-overlay');
        if (overlay) overlay.style.display = 'flex';
    }

    function closeModal() {
        var overlay = document.getElementById('vep-event-modal-overlay');
        if (overlay) overlay.style.display = 'none';
    }

    function setTitle(text) {
        var el = document.getElementById('vep-event-modal-title');
        if (el) el.textContent = text;
    }

    function resetForm() {
        var form = document.getElementById('vep-event-modal-form');
        if (form) form.reset();
        document.getElementById('vep-event-modal-event-id').value = '0';
    }

    function showLoading() {
        var loading = document.getElementById('vep-event-modal-loading');
        var form    = document.getElementById('vep-event-modal-form');
        var saveBtn = document.getElementById('vep-event-modal-save');
        if (loading) loading.style.display = 'flex';
        if (form)    form.style.display = 'none';
        if (saveBtn) saveBtn.disabled = true;
    }

    function hideLoading() {
        var loading = document.getElementById('vep-event-modal-loading');
        var form    = document.getElementById('vep-event-modal-form');
        var saveBtn = document.getElementById('vep-event-modal-save');
        if (loading) loading.style.display = 'none';
        if (form)    form.style.display = '';
        if (saveBtn) saveBtn.disabled = false;
    }

    window.vepOpenEventModal = function (eventId) {
        eventId = parseInt(eventId, 10) || 0;

        if (eventId > 0) {
            setTitle(i18n.editEvent || 'Edit Event');
            document.getElementById('vep-event-modal-event-id').value = String(eventId);
            showLoading();
            openModal();

            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'vep_admin_get_event_data', nonce: nonce, id: String(eventId) })
            })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (!res.success) {
                        closeModal();
                        if (typeof window.vepToast === 'function') { window.vepToast(res.data && res.data.message ? res.data.message : 'Error', 'error'); }
                        return;
                    }
                    var d = res.data;
                    var nameEl  = document.getElementById('vep-event-modal-name');
                    var descEl  = document.getElementById('vep-event-modal-description');
                    var startEl = document.getElementById('vep-event-modal-start-date');
                    var endEl   = document.getElementById('vep-event-modal-end-date');
                    if (nameEl)  nameEl.value  = d.name        || '';
                    if (descEl)  descEl.value  = d.description || '';
                    if (startEl) startEl.value = d.start_date  || '';
                    if (endEl)   endEl.value   = d.end_date    || '';
                    hideLoading();
                })
                .catch(function () {
                    closeModal();
                    if (typeof window.vepToast === 'function') { window.vepToast('Network error', 'error'); }
                });
        } else {
            resetForm();
            setTitle(i18n.addNewEvent || 'Add New Event');
            openModal();
        }
    };

    function saveEvent() {
        var form = document.getElementById('vep-event-modal-form');
        if (!form) return;

        var saveBtn  = document.getElementById('vep-event-modal-save');
        var origText = saveBtn ? saveBtn.textContent : '';
        if (saveBtn) { saveBtn.textContent = i18n.saving || 'Saving\u2026'; saveBtn.disabled = true; }

        var fd = new FormData(form);
        fd.append('action', 'vep_admin_save_event');

        fetch(ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res.success) {
                    if (typeof window.vepToast === 'function') {
                        window.vepToast(res.data && res.data.message ? res.data.message : 'Saved.', 'success');
                    }
                    closeModal();
                    // Reload the page to reflect the updated event list.
                    window.location.reload();
                } else {
                    var msg = res.data && res.data.message ? res.data.message : 'Error';
                    if (typeof window.vepToast === 'function') { window.vepToast(msg, 'error'); }
                    if (saveBtn) { saveBtn.textContent = origText; saveBtn.disabled = false; }
                }
            })
            .catch(function () {
                if (typeof window.vepToast === 'function') { window.vepToast('Network error', 'error'); }
                if (saveBtn) { saveBtn.textContent = origText; saveBtn.disabled = false; }
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var closeBtn  = document.getElementById('vep-event-modal-close');
        var cancelBtn = document.getElementById('vep-event-modal-cancel');
        var saveBtn   = document.getElementById('vep-event-modal-save');
        var overlay   = document.getElementById('vep-event-modal-overlay');
        var addNewBtn = document.getElementById('vep-open-add-event-modal');

        if (closeBtn)  closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        if (overlay) {
            overlay.addEventListener('click', function (e) {
                if (e.target === overlay) closeModal();
            });
        }
        if (saveBtn) saveBtn.addEventListener('click', saveEvent);
        if (addNewBtn) {
            addNewBtn.addEventListener('click', function () {
                window.vepOpenEventModal(0);
            });
        }
        document.addEventListener('keydown', function (e) {
            if ('Escape' === e.key && overlay && overlay.style.display !== 'none') {
                closeModal();
            }
        });

        // Edit links in the events list table.
        document.addEventListener('click', function (e) {
            var target = e.target.closest('[data-event-edit-modal]');
            if (!target) return;
            e.preventDefault();
            var id = parseInt(target.getAttribute('data-event-edit-modal'), 10) || 0;
            if (id > 0) window.vepOpenEventModal(id);
        });
    });

}());

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize category page functionality
        initializeCategoryPage();
    });

    function initializeCategoryPage() {
        var $form = $('.vep-category-form, .vep-tag-form');
        if ($form.length === 0) {
            return;
        }

        var $iconInput = $('#category_icon, #type_icon, #tag_icon');
        var $iconPreview = $('#icon-preview');
        var $openPickerBtn = $('#open-icon-picker');
        var $clearPickerBtn = $('#clear-icon-picker');

        if ($iconInput.length === 0 || $openPickerBtn.length === 0) {
            return;
        }

        var initialData = $form.serialize();

        // All Font Awesome 7.1.0 SOLID + REGULAR + BRAND icons
        // Verified directly from: https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@7.1.0/css/all.min.css
        var allIcons = [
            // SOLID ICONS (970)
            's:fa-0', 's:fa-1', 's:fa-2', 's:fa-3', 's:fa-4',
            's:fa-5', 's:fa-6', 's:fa-7', 's:fa-8', 's:fa-9',
            's:fa-a', 's:fa-address-book', 's:fa-address-card', 's:fa-adjust', 's:fa-air-freshener',
            's:fa-align-center', 's:fa-align-justify', 's:fa-align-left', 's:fa-align-right', 's:fa-allergies',
            's:fa-ambulance', 's:fa-anchor', 's:fa-angle-double-down', 's:fa-angle-double-left', 's:fa-angle-double-right',
            's:fa-angle-double-up', 's:fa-angle-down', 's:fa-angle-left', 's:fa-angle-right', 's:fa-angle-up',
            's:fa-angry', 's:fa-ankh', 's:fa-apple-alt', 's:fa-archive', 's:fa-archway',
            's:fa-arrow-alt-circle-down', 's:fa-arrow-alt-circle-left', 's:fa-arrow-alt-circle-right', 's:fa-arrow-alt-circle-up', 's:fa-arrow-circle-down',
            's:fa-arrow-circle-left', 's:fa-arrow-circle-right', 's:fa-arrow-circle-up', 's:fa-arrow-down', 's:fa-arrow-left',
            's:fa-arrow-right', 's:fa-arrow-up', 's:fa-arrows-alt', 's:fa-arrows-alt-h', 's:fa-arrows-alt-v',
            's:fa-assistive-listening-systems', 's:fa-asterisk', 's:fa-at', 's:fa-atlas', 's:fa-atom',
            's:fa-audio-description', 's:fa-award', 's:fa-b', 's:fa-baby', 's:fa-baby-carriage',
            's:fa-backspace', 's:fa-backward', 's:fa-bacon', 's:fa-balance-scale', 's:fa-balance-scale-left',
            's:fa-balance-scale-right', 's:fa-ban', 's:fa-band-aid', 's:fa-barcode', 's:fa-bars',
            's:fa-baseball-ball', 's:fa-basketball-ball', 's:fa-bath', 's:fa-battery-empty', 's:fa-battery-full',
            's:fa-battery-half', 's:fa-battery-quarter', 's:fa-battery-three-quarters', 's:fa-bed', 's:fa-beer',
            's:fa-bell', 's:fa-bell-slash', 's:fa-bezier-curve', 's:fa-bible', 's:fa-bicycle',
            's:fa-biking', 's:fa-binoculars', 's:fa-biohazard', 's:fa-birthday-cake', 's:fa-blender',
            's:fa-blender-phone', 's:fa-blind', 's:fa-blog', 's:fa-bold', 's:fa-bolt',
            's:fa-bomb', 's:fa-bone', 's:fa-bong', 's:fa-book', 's:fa-book-dead',
            's:fa-book-medical', 's:fa-book-open', 's:fa-book-reader', 's:fa-bookmark', 's:fa-border-all',
            's:fa-border-none', 's:fa-border-style', 's:fa-bowling-ball', 's:fa-box', 's:fa-box-open',
            's:fa-boxes', 's:fa-braille', 's:fa-brain', 's:fa-bread-slice', 's:fa-briefcase',
            's:fa-briefcase-medical', 's:fa-broadcast-tower', 's:fa-broom', 's:fa-brush', 's:fa-bug',
            's:fa-building', 's:fa-bullhorn', 's:fa-bullseye', 's:fa-burn', 's:fa-bus',
            's:fa-bus-alt', 's:fa-business-time', 's:fa-calculator', 's:fa-calendar', 's:fa-calendar-alt',
            's:fa-calendar-check', 's:fa-calendar-day', 's:fa-calendar-minus', 's:fa-calendar-plus', 's:fa-calendar-times',
            's:fa-calendar-week', 's:fa-camera', 's:fa-camera-retro', 's:fa-campground', 's:fa-candy-cane',
            's:fa-cannabis', 's:fa-capsules', 's:fa-car', 's:fa-car-alt', 's:fa-car-battery',
            's:fa-car-crash', 's:fa-car-side', 's:fa-caret-down', 's:fa-caret-left', 's:fa-caret-right',
            's:fa-caret-square-down', 's:fa-caret-square-left', 's:fa-caret-square-right', 's:fa-caret-square-up', 's:fa-caret-up',
            's:fa-carrot', 's:fa-cart-arrow-down', 's:fa-cart-plus', 's:fa-cash-register', 's:fa-cat',
            's:fa-certificate', 's:fa-chair', 's:fa-chalkboard', 's:fa-chalkboard-teacher', 's:fa-charging-station',
            's:fa-chart-area', 's:fa-chart-bar', 's:fa-chart-line', 's:fa-chart-pie', 's:fa-check',
            's:fa-check-circle', 's:fa-check-double', 's:fa-check-square', 's:fa-cheese', 's:fa-chess',
            's:fa-chess-bishop', 's:fa-chess-board', 's:fa-chess-king', 's:fa-chess-knight', 's:fa-chess-pawn',
            's:fa-chess-queen', 's:fa-chess-rook', 's:fa-chevron-circle-down', 's:fa-chevron-circle-left', 's:fa-chevron-circle-right',
            's:fa-chevron-circle-up', 's:fa-chevron-down', 's:fa-chevron-left', 's:fa-chevron-right', 's:fa-chevron-up',
            's:fa-child', 's:fa-church', 's:fa-circle', 's:fa-circle-notch', 's:fa-city',
            's:fa-clinic-medical', 's:fa-clipboard', 's:fa-clipboard-check', 's:fa-clipboard-list', 's:fa-clock',
            's:fa-clone', 's:fa-closed-captioning', 's:fa-cloud', 's:fa-cloud-download-alt', 's:fa-cloud-meatball',
            's:fa-cloud-moon', 's:fa-cloud-moon-rain', 's:fa-cloud-rain', 's:fa-cloud-showers-heavy', 's:fa-cloud-sun',
            's:fa-cloud-sun-rain', 's:fa-cloud-upload-alt', 's:fa-cocktail', 's:fa-code', 's:fa-code-branch',
            's:fa-coffee', 's:fa-cog', 's:fa-cogs', 's:fa-coins', 's:fa-columns',
            's:fa-comment', 's:fa-comment-alt', 's:fa-comment-dollar', 's:fa-comment-dots', 's:fa-comment-medical',
            's:fa-comment-slash', 's:fa-comments', 's:fa-comments-dollar', 's:fa-compact-disc', 's:fa-compass',
            's:fa-compress', 's:fa-compress-arrows-alt', 's:fa-concierge-bell', 's:fa-cookie', 's:fa-cookie-bite',
            's:fa-copy', 's:fa-copyright', 's:fa-couch', 's:fa-credit-card', 's:fa-crop',
            's:fa-crop-alt', 's:fa-cross', 's:fa-crosshairs', 's:fa-crow', 's:fa-crown',
            's:fa-crutch', 's:fa-cube', 's:fa-cubes', 's:fa-cut', 's:fa-database',
            's:fa-deaf', 's:fa-democrat', 's:fa-desktop', 's:fa-dharmachakra', 's:fa-diagnoses',
            's:fa-dice', 's:fa-dice-d20', 's:fa-dice-d6', 's:fa-dice-five', 's:fa-dice-four',
            's:fa-dice-one', 's:fa-dice-six', 's:fa-dice-three', 's:fa-dice-two', 's:fa-digital-tachograph',
            's:fa-directions', 's:fa-divide', 's:fa-dizzy', 's:fa-dna', 's:fa-dog',
            's:fa-dollar-sign', 's:fa-dolly', 's:fa-dolly-flatbed', 's:fa-donate', 's:fa-door-closed',
            's:fa-door-open', 's:fa-dot-circle', 's:fa-dove', 's:fa-download', 's:fa-drafting-compass',
            's:fa-dragon', 's:fa-draw-polygon', 's:fa-drum', 's:fa-drum-steelpan', 's:fa-drumstick-bite',
            's:fa-dumbbell', 's:fa-dumpster', 's:fa-dumpster-fire', 's:fa-dungeon', 's:fa-edit',
            's:fa-egg', 's:fa-eject', 's:fa-ellipsis-h', 's:fa-ellipsis-v', 's:fa-envelope',
            's:fa-envelope-open', 's:fa-envelope-open-text', 's:fa-envelope-square', 's:fa-equals', 's:fa-eraser',
            's:fa-ethernet', 's:fa-euro-sign', 's:fa-exchange-alt', 's:fa-exclamation', 's:fa-exclamation-circle',
            's:fa-exclamation-triangle', 's:fa-expand', 's:fa-expand-arrows-alt', 's:fa-external-link-alt', 's:fa-external-link-square-alt',
            's:fa-eye', 's:fa-eye-dropper', 's:fa-eye-slash', 's:fa-fan', 's:fa-fast-backward',
            's:fa-fast-forward', 's:fa-fax', 's:fa-feather', 's:fa-feather-alt', 's:fa-female',
            's:fa-fighter-jet', 's:fa-file', 's:fa-file-alt', 's:fa-file-archive', 's:fa-file-audio',
            's:fa-file-code', 's:fa-file-contract', 's:fa-file-csv', 's:fa-file-download', 's:fa-file-excel',
            's:fa-file-export', 's:fa-file-image', 's:fa-file-import', 's:fa-file-invoice', 's:fa-file-invoice-dollar',
            's:fa-file-medical', 's:fa-file-medical-alt', 's:fa-file-pdf', 's:fa-file-powerpoint', 's:fa-file-prescription',
            's:fa-file-signature', 's:fa-file-upload', 's:fa-file-video', 's:fa-file-word', 's:fa-fill',
            's:fa-fill-drip', 's:fa-film', 's:fa-filter', 's:fa-fingerprint', 's:fa-fire',
            's:fa-fire-alt', 's:fa-fire-extinguisher', 's:fa-first-aid', 's:fa-fish', 's:fa-fist-raised',
            's:fa-flag', 's:fa-flag-checkered', 's:fa-flag-usa', 's:fa-flask', 's:fa-flushed',
            's:fa-folder', 's:fa-folder-minus', 's:fa-folder-open', 's:fa-folder-plus', 's:fa-font',
            's:fa-football-ball', 's:fa-forward', 's:fa-frog', 's:fa-frown', 's:fa-frown-open',
            's:fa-funnel-dollar', 's:fa-futbol', 's:fa-gamepad', 's:fa-gas-pump', 's:fa-gavel',
            's:fa-gem', 's:fa-genderless', 's:fa-ghost', 's:fa-gift', 's:fa-gifts',
            's:fa-glass-cheers', 's:fa-glass-martini', 's:fa-glass-martini-alt', 's:fa-glass-whiskey', 's:fa-glasses',
            's:fa-globe', 's:fa-globe-africa', 's:fa-globe-americas', 's:fa-globe-asia', 's:fa-globe-europe',
            's:fa-golf-ball', 's:fa-gopuram', 's:fa-graduation-cap', 's:fa-greater-than', 's:fa-greater-than-equal',
            's:fa-grimace', 's:fa-grin', 's:fa-grin-alt', 's:fa-grin-beam', 's:fa-grin-beam-sweat',
            's:fa-grin-hearts', 's:fa-grin-squint', 's:fa-grin-squint-tears', 's:fa-grin-stars', 's:fa-grin-tears',
            's:fa-grin-tongue', 's:fa-grin-tongue-squint', 's:fa-grin-tongue-wink', 's:fa-grin-wink', 's:fa-grip-horizontal',
            's:fa-grip-lines', 's:fa-grip-lines-vertical', 's:fa-grip-vertical', 's:fa-guitar', 's:fa-h-square',
            's:fa-hammer', 's:fa-hamsa', 's:fa-hand-holding', 's:fa-hand-holding-heart', 's:fa-hand-holding-usd',
            's:fa-hand-holding-water', 's:fa-hand-lizard', 's:fa-hand-middle-finger', 's:fa-hand-paper', 's:fa-hand-peace',
            's:fa-hand-point-down', 's:fa-hand-point-left', 's:fa-hand-point-right', 's:fa-hand-point-up', 's:fa-hand-pointer',
            's:fa-hand-rock', 's:fa-hand-scissors', 's:fa-hand-spock', 's:fa-hands', 's:fa-hands-helping',
            's:fa-handshake', 's:fa-handshake-slash', 's:fa-hanukiah', 's:fa-hard-hat', 's:fa-hashtag',
            's:fa-hat-cowboy', 's:fa-hat-cowboy-side', 's:fa-hat-wizard', 's:fa-haykal', 's:fa-hdd',
            's:fa-heading', 's:fa-headphones', 's:fa-headphones-alt', 's:fa-headset', 's:fa-heart',
            's:fa-heart-broken', 's:fa-heartbeat', 's:fa-helicopter', 's:fa-highlighter', 's:fa-hiking',
            's:fa-hippo', 's:fa-history', 's:fa-hockey-puck', 's:fa-holly-berry', 's:fa-home',
            's:fa-horse', 's:fa-horse-head', 's:fa-hospital', 's:fa-hospital-alt', 's:fa-hospital-symbol',
            's:fa-hot-tub', 's:fa-hotdog', 's:fa-hotel', 's:fa-hourglass', 's:fa-hourglass-end',
            's:fa-hourglass-half', 's:fa-hourglass-start', 's:fa-house-damage', 's:fa-hryvnia', 's:fa-i-cursor',
            's:fa-ice-cream', 's:fa-icicles', 's:fa-icons', 's:fa-id-badge', 's:fa-id-card',
            's:fa-id-card-alt', 's:fa-igloo', 's:fa-image', 's:fa-images', 's:fa-inbox',
            's:fa-indent', 's:fa-industry', 's:fa-infinity', 's:fa-info', 's:fa-info-circle',
            's:fa-italic', 's:fa-jedi', 's:fa-joint', 's:fa-journal-whills', 's:fa-kaaba',
            's:fa-key', 's:fa-keyboard', 's:fa-khanda', 's:fa-kiss', 's:fa-kiss-beam',
            's:fa-kiss-wink-heart', 's:fa-kiwi-bird', 's:fa-landmark', 's:fa-language', 's:fa-laptop',
            's:fa-laptop-code', 's:fa-laptop-medical', 's:fa-laugh', 's:fa-laugh-beam', 's:fa-laugh-squint',
            's:fa-laugh-wink', 's:fa-layer-group', 's:fa-leaf', 's:fa-lemon', 's:fa-less-than',
            's:fa-less-than-equal', 's:fa-level-down-alt', 's:fa-level-up-alt', 's:fa-life-ring', 's:fa-lightbulb',
            's:fa-link', 's:fa-lira-sign', 's:fa-list', 's:fa-list-alt', 's:fa-list-ol',
            's:fa-list-ul', 's:fa-location-arrow', 's:fa-lock', 's:fa-lock-open', 's:fa-long-arrow-alt-down',
            's:fa-long-arrow-alt-left', 's:fa-long-arrow-alt-right', 's:fa-long-arrow-alt-up', 's:fa-low-vision', 's:fa-luggage-cart',
            's:fa-magic', 's:fa-magnet', 's:fa-mail-bulk', 's:fa-male', 's:fa-map',
            's:fa-map-marked', 's:fa-map-marked-alt', 's:fa-map-marker', 's:fa-map-marker-alt', 's:fa-map-pin',
            's:fa-map-signs', 's:fa-marker', 's:fa-mars', 's:fa-mars-double', 's:fa-mars-stroke',
            's:fa-mars-stroke-h', 's:fa-mars-stroke-v', 's:fa-mask', 's:fa-medal', 's:fa-medkit',
            's:fa-meh', 's:fa-meh-blank', 's:fa-meh-rolling-eyes', 's:fa-memory', 's:fa-menorah',
            's:fa-mercury', 's:fa-meteor', 's:fa-microchip', 's:fa-microphone', 's:fa-microphone-alt',
            's:fa-microphone-alt-slash', 's:fa-microphone-slash', 's:fa-microscope', 's:fa-minus', 's:fa-minus-circle',
            's:fa-minus-square', 's:fa-mitten', 's:fa-mobile', 's:fa-mobile-alt', 's:fa-money-bill',
            's:fa-money-bill-alt', 's:fa-money-bill-wave', 's:fa-money-bill-wave-alt', 's:fa-money-check', 's:fa-money-check-alt',
            's:fa-monument', 's:fa-moon', 's:fa-mortar-pestle', 's:fa-mosque', 's:fa-motorcycle',
            's:fa-mountain', 's:fa-mouse-pointer', 's:fa-mug-hot', 's:fa-music', 's:fa-network-wired',
            's:fa-neuter', 's:fa-newspaper', 's:fa-not-equal', 's:fa-notes-medical', 's:fa-object-group',
            's:fa-object-ungroup', 's:fa-oil-can', 's:fa-om', 's:fa-otter', 's:fa-outdent',
            's:fa-pager', 's:fa-paint-brush', 's:fa-paint-roller', 's:fa-palette', 's:fa-pallet',
            's:fa-paper-plane', 's:fa-paperclip', 's:fa-parachute-box', 's:fa-paragraph', 's:fa-parking',
            's:fa-passport', 's:fa-pastafarianism', 's:fa-paste', 's:fa-pause', 's:fa-pause-circle',
            's:fa-paw', 's:fa-peace', 's:fa-pen', 's:fa-pen-alt', 's:fa-pen-fancy',
            's:fa-pen-nib', 's:fa-pen-square', 's:fa-pencil-alt', 's:fa-pencil-ruler', 's:fa-people-carry',
            's:fa-pepper-hot', 's:fa-percent', 's:fa-percentage', 's:fa-person-booth', 's:fa-phone',
            's:fa-phone-alt', 's:fa-phone-slash', 's:fa-phone-square', 's:fa-phone-square-alt', 's:fa-phone-volume',
            's:fa-photo-video', 's:fa-piggy-bank', 's:fa-pills', 's:fa-pizza-slice', 's:fa-place-of-worship',
            's:fa-plane', 's:fa-plane-arrival', 's:fa-plane-departure', 's:fa-play', 's:fa-play-circle',
            's:fa-plug', 's:fa-plus', 's:fa-plus-circle', 's:fa-plus-square', 's:fa-podcast',
            's:fa-poll', 's:fa-poll-h', 's:fa-poo', 's:fa-poo-storm', 's:fa-poop',
            's:fa-portrait', 's:fa-pound-sign', 's:fa-power-off', 's:fa-pray', 's:fa-praying-hands',
            's:fa-prescription', 's:fa-prescription-bottle', 's:fa-prescription-bottle-alt', 's:fa-print', 's:fa-procedures',
            's:fa-project-diagram', 's:fa-puzzle-piece', 's:fa-qrcode', 's:fa-question', 's:fa-question-circle',
            's:fa-quidditch', 's:fa-quote-left', 's:fa-quote-right', 's:fa-quran', 's:fa-radiation',
            's:fa-radiation-alt', 's:fa-rainbow', 's:fa-random', 's:fa-receipt', 's:fa-record-vinyl',
            's:fa-recycle', 's:fa-redo', 's:fa-redo-alt', 's:fa-registered', 's:fa-remove-format',
            's:fa-reply', 's:fa-reply-all', 's:fa-republican', 's:fa-restroom', 's:fa-retweet',
            's:fa-ribbon', 's:fa-ring', 's:fa-road', 's:fa-robot', 's:fa-rocket',
            's:fa-route', 's:fa-rss', 's:fa-rss-square', 's:fa-ruble-sign', 's:fa-ruler',
            's:fa-ruler-combined', 's:fa-ruler-horizontal', 's:fa-ruler-vertical', 's:fa-running', 's:fa-rupee-sign',
            's:fa-sad-cry', 's:fa-sad-tear', 's:fa-satellite', 's:fa-satellite-dish', 's:fa-save',
            's:fa-school', 's:fa-screwdriver', 's:fa-scroll', 's:fa-sd-card', 's:fa-search',
            's:fa-search-dollar', 's:fa-search-location', 's:fa-search-minus', 's:fa-search-plus', 's:fa-seedling',
            's:fa-server', 's:fa-shapes', 's:fa-share', 's:fa-share-alt', 's:fa-share-alt-square',
            's:fa-share-square', 's:fa-shekel-sign', 's:fa-shield-alt', 's:fa-ship', 's:fa-shipping-fast',
            's:fa-shoe-prints', 's:fa-shopping-bag', 's:fa-shopping-basket', 's:fa-shopping-cart', 's:fa-shower',
            's:fa-shuttle-van', 's:fa-sign', 's:fa-sign-in-alt', 's:fa-sign-language', 's:fa-sign-out-alt',
            's:fa-signal', 's:fa-signature', 's:fa-sim-card', 's:fa-sitemap', 's:fa-skating',
            's:fa-skiing', 's:fa-skiing-nordic', 's:fa-skull', 's:fa-skull-crossbones', 's:fa-slash',
            's:fa-sleigh', 's:fa-sliders-h', 's:fa-smile', 's:fa-smile-beam', 's:fa-smile-wink',
            's:fa-smog', 's:fa-smoking', 's:fa-smoking-ban', 's:fa-sms', 's:fa-snowboarding',
            's:fa-snowflake', 's:fa-snowman', 's:fa-snowplow', 's:fa-socks', 's:fa-solar-panel',
            's:fa-sort', 's:fa-sort-alpha-down', 's:fa-sort-alpha-down-alt', 's:fa-sort-alpha-up', 's:fa-sort-alpha-up-alt',
            's:fa-sort-amount-down', 's:fa-sort-amount-down-alt', 's:fa-sort-amount-up', 's:fa-sort-amount-up-alt', 's:fa-sort-down',
            's:fa-sort-numeric-down', 's:fa-sort-numeric-down-alt', 's:fa-sort-numeric-up', 's:fa-sort-numeric-up-alt', 's:fa-sort-up',
            's:fa-spa', 's:fa-space-shuttle', 's:fa-spell-check', 's:fa-spider', 's:fa-spinner',
            's:fa-splotch', 's:fa-spray-can', 's:fa-square', 's:fa-square-full', 's:fa-square-root-alt',
            's:fa-stamp', 's:fa-star', 's:fa-star-and-crescent', 's:fa-star-half', 's:fa-star-half-alt',
            's:fa-star-of-david', 's:fa-star-of-life', 's:fa-step-backward', 's:fa-step-forward', 's:fa-stethoscope',
            's:fa-sticky-note', 's:fa-stop', 's:fa-stop-circle', 's:fa-stopwatch', 's:fa-store',
            's:fa-store-alt', 's:fa-stream', 's:fa-street-view', 's:fa-strikethrough', 's:fa-stroopwafel',
            's:fa-subscript', 's:fa-subway', 's:fa-suitcase', 's:fa-suitcase-rolling', 's:fa-sun',
            's:fa-superscript', 's:fa-surprise', 's:fa-swatchbook', 's:fa-swimmer', 's:fa-swimming-pool',
            's:fa-synagogue', 's:fa-sync', 's:fa-sync-alt', 's:fa-syringe', 's:fa-table',
            's:fa-table-tennis', 's:fa-tablet', 's:fa-tablet-alt', 's:fa-tablets', 's:fa-tachometer-alt',
            's:fa-tag', 's:fa-tags', 's:fa-tape', 's:fa-tasks', 's:fa-taxi',
            's:fa-teeth', 's:fa-teeth-open', 's:fa-temperature-high', 's:fa-temperature-low', 's:fa-tenge',
            's:fa-terminal', 's:fa-text-height', 's:fa-text-width', 's:fa-th', 's:fa-th-large',
            's:fa-th-list', 's:fa-theater-masks', 's:fa-thermometer', 's:fa-thermometer-empty', 's:fa-thermometer-full',
            's:fa-thermometer-half', 's:fa-thermometer-quarter', 's:fa-thermometer-three-quarters', 's:fa-thumbs-down', 's:fa-thumbs-up',
            's:fa-thumbtack', 's:fa-ticket-alt', 's:fa-times', 's:fa-times-circle', 's:fa-tint',
            's:fa-tint-slash', 's:fa-tired', 's:fa-toggle-off', 's:fa-toggle-on', 's:fa-toilet',
            's:fa-toilet-paper', 's:fa-toolbox', 's:fa-tools', 's:fa-tooth', 's:fa-torah',
            's:fa-torii-gate', 's:fa-tractor', 's:fa-trademark', 's:fa-traffic-light', 's:fa-train',
            's:fa-tram', 's:fa-transgender', 's:fa-transgender-alt', 's:fa-trash', 's:fa-trash-alt',
            's:fa-trash-restore', 's:fa-trash-restore-alt', 's:fa-tree', 's:fa-trophy', 's:fa-truck',
            's:fa-truck-loading', 's:fa-truck-monster', 's:fa-truck-moving', 's:fa-truck-pickup', 's:fa-tshirt',
            's:fa-tty', 's:fa-tv', 's:fa-umbrella', 's:fa-umbrella-beach', 's:fa-underline',
            's:fa-undo', 's:fa-undo-alt', 's:fa-universal-access', 's:fa-university', 's:fa-unlink',
            's:fa-unlock', 's:fa-unlock-alt', 's:fa-upload', 's:fa-user', 's:fa-user-alt',
            's:fa-user-alt-slash', 's:fa-user-astronaut', 's:fa-user-check', 's:fa-user-circle', 's:fa-user-clock',
            's:fa-user-cog', 's:fa-user-edit', 's:fa-user-friends', 's:fa-user-graduate', 's:fa-user-injured',
            's:fa-user-lock', 's:fa-user-md', 's:fa-user-minus', 's:fa-user-ninja', 's:fa-user-nurse',
            's:fa-user-plus', 's:fa-user-secret', 's:fa-user-shield', 's:fa-user-slash', 's:fa-user-tag',
            's:fa-user-tie', 's:fa-user-times', 's:fa-users', 's:fa-users-cog', 's:fa-utensil-spoon',
            's:fa-utensils', 's:fa-vector-square', 's:fa-venus', 's:fa-venus-double', 's:fa-venus-mars',
            's:fa-vial', 's:fa-vials', 's:fa-video', 's:fa-video-slash', 's:fa-vihara',
            's:fa-voicemail', 's:fa-volleyball-ball', 's:fa-volume-down', 's:fa-volume-mute', 's:fa-volume-off',
            's:fa-volume-up', 's:fa-vote-yea', 's:fa-vr-cardboard', 's:fa-walking', 's:fa-wallet',
            's:fa-warehouse', 's:fa-water', 's:fa-wave-square', 's:fa-weight', 's:fa-weight-hanging',
            's:fa-wheelchair', 's:fa-wifi', 's:fa-wind', 's:fa-window-close', 's:fa-window-maximize',
            's:fa-window-minimize', 's:fa-window-restore', 's:fa-wine-bottle', 's:fa-wine-glass', 's:fa-wine-glass-alt',
            's:fa-won-sign', 's:fa-wrench', 's:fa-x-ray', 's:fa-yen-sign', 's:fa-yin-yang',

            // REGULAR ICONS (152)
            'r:fa-address-book', 'r:fa-address-card', 'r:fa-angry', 'r:fa-arrow-alt-circle-down', 'r:fa-arrow-alt-circle-left',
            'r:fa-arrow-alt-circle-right', 'r:fa-arrow-alt-circle-up', 'r:fa-bell', 'r:fa-bell-slash', 'r:fa-bookmark',
            'r:fa-building', 'r:fa-calendar', 'r:fa-calendar-alt', 'r:fa-calendar-check', 'r:fa-calendar-minus',
            'r:fa-calendar-plus', 'r:fa-calendar-times', 'r:fa-caret-square-down', 'r:fa-caret-square-left', 'r:fa-caret-square-right',
            'r:fa-caret-square-up', 'r:fa-chart-bar', 'r:fa-check-circle', 'r:fa-check-square', 'r:fa-circle',
            'r:fa-clipboard', 'r:fa-clock', 'r:fa-clone', 'r:fa-closed-captioning', 'r:fa-comment',
            'r:fa-comment-alt', 'r:fa-comment-dots', 'r:fa-comments', 'r:fa-compass', 'r:fa-copy',
            'r:fa-copyright', 'r:fa-credit-card', 'r:fa-dizzy', 'r:fa-dot-circle', 'r:fa-edit',
            'r:fa-envelope', 'r:fa-envelope-open', 'r:fa-eye', 'r:fa-eye-slash', 'r:fa-file',
            'r:fa-file-alt', 'r:fa-file-archive', 'r:fa-file-audio', 'r:fa-file-code', 'r:fa-file-excel',
            'r:fa-file-image', 'r:fa-file-pdf', 'r:fa-file-powerpoint', 'r:fa-file-video', 'r:fa-file-word',
            'r:fa-flag', 'r:fa-flushed', 'r:fa-folder', 'r:fa-folder-open', 'r:fa-font-awesome-logo-full',
            'r:fa-frown', 'r:fa-frown-open', 'r:fa-futbol', 'r:fa-gem', 'r:fa-grimace',
            'r:fa-grin', 'r:fa-grin-alt', 'r:fa-grin-beam', 'r:fa-grin-beam-sweat', 'r:fa-grin-hearts',
            'r:fa-grin-squint', 'r:fa-grin-squint-tears', 'r:fa-grin-stars', 'r:fa-grin-tears', 'r:fa-grin-tongue',
            'r:fa-grin-tongue-squint', 'r:fa-grin-tongue-wink', 'r:fa-grin-wink', 'r:fa-hand-lizard', 'r:fa-hand-paper',
            'r:fa-hand-peace', 'r:fa-hand-point-down', 'r:fa-hand-point-left', 'r:fa-hand-point-right', 'r:fa-hand-point-up',
            'r:fa-hand-pointer', 'r:fa-hand-rock', 'r:fa-hand-scissors', 'r:fa-hand-spock', 'r:fa-handshake',
            'r:fa-hdd', 'r:fa-heart', 'r:fa-hospital', 'r:fa-hourglass', 'r:fa-id-badge',
            'r:fa-id-card', 'r:fa-image', 'r:fa-images', 'r:fa-keyboard', 'r:fa-kiss',
            'r:fa-kiss-beam', 'r:fa-kiss-wink-heart', 'r:fa-laugh', 'r:fa-laugh-beam', 'r:fa-laugh-squint',
            'r:fa-laugh-wink', 'r:fa-lemon', 'r:fa-life-ring', 'r:fa-lightbulb', 'r:fa-list-alt',
            'r:fa-map', 'r:fa-meh', 'r:fa-meh-blank', 'r:fa-meh-rolling-eyes', 'r:fa-minus-square',
            'r:fa-money-bill-alt', 'r:fa-moon', 'r:fa-newspaper', 'r:fa-object-group', 'r:fa-object-ungroup',
            'r:fa-paper-plane', 'r:fa-pause-circle', 'r:fa-play-circle', 'r:fa-plus-square', 'r:fa-question-circle',
            'r:fa-registered', 'r:fa-sad-cry', 'r:fa-sad-tear', 'r:fa-save', 'r:fa-share-square',
            'r:fa-smile', 'r:fa-smile-beam', 'r:fa-smile-wink', 'r:fa-snowflake', 'r:fa-square',
            'r:fa-star', 'r:fa-star-half', 'r:fa-sticky-note', 'r:fa-stop-circle', 'r:fa-sun',
            'r:fa-surprise', 'r:fa-thumbs-down', 'r:fa-thumbs-up', 'r:fa-times-circle', 'r:fa-tired',
            'r:fa-trash-alt', 'r:fa-user', 'r:fa-user-circle', 'r:fa-window-close', 'r:fa-window-maximize',
            'r:fa-window-minimize', 'r:fa-window-restore',

            // BRAND ICONS (446)
            'b:fa-500px', 'b:fa-accessible-icon', 'b:fa-accusoft', 'b:fa-adn', 'b:fa-adobe',
            'b:fa-adversal', 'b:fa-affiliatetheme', 'b:fa-airbnb', 'b:fa-algolia', 'b:fa-alipay',
            'b:fa-amazon', 'b:fa-amazon-pay', 'b:fa-amilia', 'b:fa-android', 'b:fa-angellist',
            'b:fa-angrycreative', 'b:fa-angular', 'b:fa-app-store', 'b:fa-app-store-ios', 'b:fa-apper',
            'b:fa-apple', 'b:fa-apple-pay', 'b:fa-artstation', 'b:fa-asymmetrik', 'b:fa-atlassian',
            'b:fa-audible', 'b:fa-autoprefixer', 'b:fa-avianex', 'b:fa-aviato', 'b:fa-aws',
            'b:fa-bandcamp', 'b:fa-battle-net', 'b:fa-behance', 'b:fa-behance-square', 'b:fa-bimobject',
            'b:fa-bitbucket', 'b:fa-bitcoin', 'b:fa-bity', 'b:fa-black-tie', 'b:fa-blackberry',
            'b:fa-blogger', 'b:fa-blogger-b', 'b:fa-bluetooth', 'b:fa-bluetooth-b', 'b:fa-bootstrap',
            'b:fa-btc', 'b:fa-buffer', 'b:fa-buromobelexperte', 'b:fa-buy-n-large', 'b:fa-buysellads',
            'b:fa-canadian-maple-leaf', 'b:fa-cc-amazon-pay', 'b:fa-cc-amex', 'b:fa-cc-apple-pay', 'b:fa-cc-diners-club',
            'b:fa-cc-discover', 'b:fa-cc-jcb', 'b:fa-cc-mastercard', 'b:fa-cc-paypal', 'b:fa-cc-stripe',
            'b:fa-cc-visa', 'b:fa-centercode', 'b:fa-centos', 'b:fa-chrome', 'b:fa-chromecast',
            'b:fa-cloudscale', 'b:fa-cloudsmith', 'b:fa-cloudversify', 'b:fa-codepen', 'b:fa-codiepie',
            'b:fa-confluence', 'b:fa-connectdevelop', 'b:fa-contao', 'b:fa-cotton-bureau', 'b:fa-cpanel',
            'b:fa-creative-commons', 'b:fa-creative-commons-by', 'b:fa-creative-commons-nc', 'b:fa-creative-commons-nc-eu', 'b:fa-creative-commons-nc-jp',
            'b:fa-creative-commons-nd', 'b:fa-creative-commons-pd', 'b:fa-creative-commons-pd-alt', 'b:fa-creative-commons-remix', 'b:fa-creative-commons-sa',
            'b:fa-creative-commons-sampling', 'b:fa-creative-commons-sampling-plus', 'b:fa-creative-commons-share', 'b:fa-creative-commons-zero', 'b:fa-critical-role',
            'b:fa-css3', 'b:fa-css3-alt', 'b:fa-cuttlefish', 'b:fa-d-and-d', 'b:fa-d-and-d-beyond',
            'b:fa-dailymotion', 'b:fa-dashcube', 'b:fa-deezer', 'b:fa-delicious', 'b:fa-deploydog',
            'b:fa-deskpro', 'b:fa-dev', 'b:fa-deviantart', 'b:fa-dhl', 'b:fa-diaspora',
            'b:fa-digg', 'b:fa-digital-ocean', 'b:fa-discord', 'b:fa-discourse', 'b:fa-dochub',
            'b:fa-docker', 'b:fa-draft2digital', 'b:fa-dribbble', 'b:fa-dribbble-square', 'b:fa-dropbox',
            'b:fa-drupal', 'b:fa-dyalog', 'b:fa-earlybirds', 'b:fa-ebay', 'b:fa-edge',
            'b:fa-elementor', 'b:fa-ello', 'b:fa-ember', 'b:fa-empire', 'b:fa-envira',
            'b:fa-erlang', 'b:fa-ethereum', 'b:fa-etsy', 'b:fa-evernote', 'b:fa-expeditedssl',
            'b:fa-facebook', 'b:fa-facebook-f', 'b:fa-facebook-messenger', 'b:fa-facebook-square', 'b:fa-fantasy-flight-games',
            'b:fa-fedex', 'b:fa-fedora', 'b:fa-figma', 'b:fa-firefox', 'b:fa-firefox-browser',
            'b:fa-first-order', 'b:fa-first-order-alt', 'b:fa-firstdraft', 'b:fa-flickr', 'b:fa-flipboard',
            'b:fa-fly', 'b:fa-font-awesome', 'b:fa-font-awesome-alt', 'b:fa-font-awesome-flag', 'b:fa-fonticons',
            'b:fa-fonticons-fi', 'b:fa-fort-awesome', 'b:fa-fort-awesome-alt', 'b:fa-forumbee', 'b:fa-foursquare',
            'b:fa-free-code-camp', 'b:fa-freebsd', 'b:fa-fulcrum', 'b:fa-galactic-republic', 'b:fa-galactic-senate',
            'b:fa-get-pocket', 'b:fa-gg', 'b:fa-gg-circle', 'b:fa-git', 'b:fa-git-alt',
            'b:fa-git-square', 'b:fa-github', 'b:fa-github-alt', 'b:fa-github-square', 'b:fa-gitkraken',
            'b:fa-gitlab', 'b:fa-gitter', 'b:fa-glide', 'b:fa-glide-g', 'b:fa-gofore',
            'b:fa-goodreads', 'b:fa-goodreads-g', 'b:fa-google', 'b:fa-google-drive', 'b:fa-google-pay',
            'b:fa-google-play', 'b:fa-google-plus', 'b:fa-google-plus-g', 'b:fa-google-plus-square', 'b:fa-google-wallet',
            'b:fa-gratipay', 'b:fa-grav', 'b:fa-gripfire', 'b:fa-grunt', 'b:fa-gulp',
            'b:fa-hacker-news', 'b:fa-hacker-news-square', 'b:fa-hackerrank', 'b:fa-hips', 'b:fa-hire-a-helper',
            'b:fa-hooli', 'b:fa-hornbill', 'b:fa-hotjar', 'b:fa-houzz', 'b:fa-html5',
            'b:fa-hubspot', 'b:fa-ideal', 'b:fa-imdb', 'b:fa-instagram', 'b:fa-instagram-square',
            'b:fa-intercom', 'b:fa-internet-explorer', 'b:fa-invision', 'b:fa-ioxhost', 'b:fa-itch-io',
            'b:fa-itunes', 'b:fa-itunes-note', 'b:fa-java', 'b:fa-jedi-order', 'b:fa-jenkins',
            'b:fa-jira', 'b:fa-joget', 'b:fa-joomla', 'b:fa-js', 'b:fa-js-square',
            'b:fa-jsfiddle', 'b:fa-kaggle', 'b:fa-keybase', 'b:fa-keycdn', 'b:fa-kickstarter',
            'b:fa-kickstarter-k', 'b:fa-korvue', 'b:fa-laravel', 'b:fa-lastfm', 'b:fa-lastfm-square',
            'b:fa-leanpub', 'b:fa-less', 'b:fa-line', 'b:fa-linkedin', 'b:fa-linkedin-in',
            'b:fa-linode', 'b:fa-linux', 'b:fa-lyft', 'b:fa-magento', 'b:fa-mailchimp',
            'b:fa-mandalorian', 'b:fa-markdown', 'b:fa-mastodon', 'b:fa-maxcdn', 'b:fa-mdb',
            'b:fa-medapps', 'b:fa-medium', 'b:fa-medium-m', 'b:fa-medrt', 'b:fa-meetup',
            'b:fa-megaport', 'b:fa-mendeley', 'b:fa-microblog', 'b:fa-microsoft', 'b:fa-mix',
            'b:fa-mixer', 'b:fa-mixcloud', 'b:fa-mizuni', 'b:fa-modx', 'b:fa-monero',
            'b:fa-napster', 'b:fa-neos', 'b:fa-nimblr', 'b:fa-node', 'b:fa-node-js',
            'b:fa-npm', 'b:fa-ns8', 'b:fa-nutritionix', 'b:fa-odnoklassniki', 'b:fa-odnoklassniki-square',
            'b:fa-old-republic', 'b:fa-opencart', 'b:fa-openid', 'b:fa-opera', 'b:fa-optin-monster',
            'b:fa-orcid', 'b:fa-osi', 'b:fa-page4', 'b:fa-pagelines', 'b:fa-palfed',
            'b:fa-patreon', 'b:fa-paypal', 'b:fa-penny-arcade', 'b:fa-periscope', 'b:fa-phabricator',
            'b:fa-phoenix-framework', 'b:fa-phoenix-squadron', 'b:fa-php', 'b:fa-pied-piper', 'b:fa-pied-piper-alt',
            'b:fa-pied-piper-hat', 'b:fa-pied-piper-pp', 'b:fa-pied-piper-square', 'b:fa-pinterest', 'b:fa-pinterest-p',
            'b:fa-pinterest-square', 'b:fa-playstation', 'b:fa-product-hunt', 'b:fa-pushed', 'b:fa-python',
            'b:fa-qq', 'b:fa-quinscape', 'b:fa-quora', 'b:fa-r-project', 'b:fa-raspberry-pi',
            'b:fa-ravelry', 'b:fa-react', 'b:fa-reacteurope', 'b:fa-readme', 'b:fa-rebel',
            'b:fa-red-river', 'b:fa-reddit', 'b:fa-reddit-alien', 'b:fa-reddit-square', 'b:fa-redhat',
            'b:fa-renren', 'b:fa-replyd', 'b:fa-researchgate', 'b:fa-resolving', 'b:fa-rev',
            'b:fa-rocketchat', 'b:fa-rockrms', 'b:fa-rust', 'b:fa-safari', 'b:fa-salesforce',
            'b:fa-sass', 'b:fa-schlix', 'b:fa-scribd', 'b:fa-searchengin', 'b:fa-sellcast',
            'b:fa-sellsy', 'b:fa-servicestack', 'b:fa-shirtsinbulk', 'b:fa-shopify', 'b:fa-shopware',
            'b:fa-simplybuilt', 'b:fa-sistrix', 'b:fa-sith', 'b:fa-sketch', 'b:fa-skyatlas',
            'b:fa-skype', 'b:fa-slack', 'b:fa-slack-hash', 'b:fa-slideshare', 'b:fa-snapchat',
            'b:fa-snapchat-ghost', 'b:fa-snapchat-square', 'b:fa-soundcloud', 'b:fa-sourcetree', 'b:fa-speakap',
            'b:fa-speaker-deck', 'b:fa-spotify', 'b:fa-squarespace', 'b:fa-stack-exchange', 'b:fa-stack-overflow',
            'b:fa-stackpath', 'b:fa-staylinked', 'b:fa-steam', 'b:fa-steam-square', 'b:fa-steam-symbol',
            'b:fa-sticker-mule', 'b:fa-strava', 'b:fa-stripe', 'b:fa-stripe-s', 'b:fa-studiovinari',
            'b:fa-stumbleupon', 'b:fa-stumbleupon-circle', 'b:fa-superpowers', 'b:fa-supple', 'b:fa-suse',
            'b:fa-swift', 'b:fa-symfony', 'b:fa-teamspeak', 'b:fa-telegram', 'b:fa-telegram-plane',
            'b:fa-tencent-weibo', 'b:fa-the-red-yeti', 'b:fa-themeco', 'b:fa-themeisle', 'b:fa-think-peaks',
            'b:fa-tiktok', 'b:fa-trade-federation', 'b:fa-trello', 'b:fa-tripadvisor', 'b:fa-tumblr',
            'b:fa-tumblr-square', 'b:fa-twitch', 'b:fa-twitter', 'b:fa-twitter-square', 'b:fa-typo3',
            'b:fa-uber', 'b:fa-ubuntu', 'b:fa-uikit', 'b:fa-umbraco', 'b:fa-uniregistry',
            'b:fa-unity', 'b:fa-unsplash', 'b:fa-untappd', 'b:fa-ups', 'b:fa-usb',
            'b:fa-usps', 'b:fa-ussunnah', 'b:fa-vaadin', 'b:fa-viacoin', 'b:fa-viadeo',
            'b:fa-viadeo-square', 'b:fa-viber', 'b:fa-vimeo', 'b:fa-vimeo-square', 'b:fa-vimeo-v',
            'b:fa-vine', 'b:fa-vk', 'b:fa-vnv', 'b:fa-vuejs', 'b:fa-waze',
            'b:fa-weebly', 'b:fa-weibo', 'b:fa-weixin', 'b:fa-whatsapp', 'b:fa-whatsapp-square',
            'b:fa-whmcs', 'b:fa-wikipedia-w', 'b:fa-windows', 'b:fa-wix', 'b:fa-wizards-of-the-coast',
            'b:fa-wolf-pack-battalion', 'b:fa-wordpress', 'b:fa-wordpress-simple', 'b:fa-wpbeginner', 'b:fa-wpexplorer',
            'b:fa-wpforms', 'b:fa-wpressr', 'b:fa-xbox', 'b:fa-xing', 'b:fa-xing-square',
            'b:fa-y-combinator', 'b:fa-yahoo', 'b:fa-yammer', 'b:fa-yandex', 'b:fa-yandex-international',
            'b:fa-yarn', 'b:fa-yelp', 'b:fa-yoast', 'b:fa-youtube', 'b:fa-youtube-square',
            'b:fa-zhihu'
        ];

        function parseIconPrefix(iconValue) {
            if (!iconValue) {
                return { class: 'fa-solid', name: '' };
            }

            if (iconValue.indexOf('s:') === 0) {
                return { class: 'fa-solid', name: iconValue.substring(2) };
            } else if (iconValue.indexOf('r:') === 0) {
                return { class: 'fa-regular', name: iconValue.substring(2) };
            } else if (iconValue.indexOf('b:') === 0) {
                return { class: 'fa-brands', name: iconValue.substring(2) };
            }

            return { class: 'fa-solid', name: iconValue };
        }

        var iconsPerPage = 200;
        var currentPage = 0;
        var filteredIcons = allIcons.slice();

        $openPickerBtn.on('click', function() {
            currentPage = 0;
            filteredIcons = allIcons.slice();

            var modal = $('<div class="vep-icon-modal-backdrop"><div class="vep-icon-modal"><div class="vep-icon-modal-header"><h2>Select an Icon (' + allIcons.length + ' available)</h2><button class="vep-icon-modal-close">&times;</button></div><div><input type="text" id="icon-search" placeholder="Search icons..."><div class="vep-icon-grid"></div></div><div class="vep-icon-loader" style="display: none; text-align: center; padding: 20px;"><p>Loading more icons...</p></div></div></div>');
            var modalContent = modal.find('.vep-icon-modal');
            var iconGrid = modalContent.find('.vep-icon-grid');
            var loader = modalContent.find('.vep-icon-loader');

            function loadMoreIcons() {
                var start = currentPage * iconsPerPage;
                var end = start + iconsPerPage;
                var iconsToLoad = filteredIcons.slice(start, end);

                $.each(iconsToLoad, function(index, icon) {
                    var parsed = parseIconPrefix(icon);
                    var iconLabel = parsed.name.replace(/-/g, ' ').substring(3);
                    var iconItem = $('<div class="vep-icon-item" data-icon="' + icon + '" title="' + iconLabel + '"><i class="' + parsed.class + ' ' + parsed.name + '"></i><span>' + iconLabel + '</span></div>');
                    iconGrid.append(iconItem);
                });

                currentPage++;

                if (start + iconsPerPage >= filteredIcons.length) {
                    loader.hide();
                } else {
                    loader.show();
                }
            }

            function closeModal() {
                modal.remove();
            }

            $('body').append(modal);
            loadMoreIcons();

            iconGrid.on('scroll', function() {
                if ($(this).scrollTop() + $(this).innerHeight() >= $(this)[0].scrollHeight - 50) {
                    if (currentPage * iconsPerPage < filteredIcons.length) {
                        loadMoreIcons();
                    }
                }
            });

            $('.vep-icon-modal-close').on('click', closeModal);
            modal.on('click', function(e) {
                if ($(e.target).is('.vep-icon-modal-backdrop')) {
                    closeModal();
                }
            });

            $(document).on('click', '.vep-icon-item', function() {
                var icon = $(this).data('icon');
                $iconInput.val(icon).trigger('change');
                var parsed = parseIconPrefix(icon);
                $iconPreview.html('<i class="' + parsed.class + ' ' + parsed.name + '"></i>');
                closeModal();
            });

            $('#icon-search').on('input', function() {
                var searchTerm = $(this).val().toLowerCase();

                if (searchTerm === '') {
                    filteredIcons = allIcons.slice();
                } else {
                    filteredIcons = allIcons.filter(function(icon) {
                        return icon.toLowerCase().indexOf(searchTerm) > -1;
                    });
                }

                currentPage = 0;
                iconGrid.empty();
                loader.hide();
                loadMoreIcons();
            });

            $('#icon-search').focus();
        });

        $clearPickerBtn.on('click', function(e) {
            e.preventDefault();
            $iconInput.val('').trigger('input');
        });

        $iconInput.on('input', function() {
            var iconValue = $(this).val();
            if (iconValue) {
                var parsed = parseIconPrefix(iconValue);
                $iconPreview.html('<i class="' + parsed.class + ' ' + parsed.name + '"></i>');
            } else {
                var placeholderText = (typeof vepAdmin !== 'undefined' && vepAdmin.placeholderText) ? vepAdmin.placeholderText : 'Please select icon';
                $iconPreview.html('<span class="vep-icon-placeholder">' + placeholderText + '</span>');
            }
        });

        $form.find('input, textarea').on('change', function() {
            if ($form.serialize() !== initialData) {
                $(window).on('beforeunload', function() {
                    return 'You have unsaved changes.';
                });
            }
        });

        $form.on('submit', function() {
            $(window).off('beforeunload');
        });

        $(document).on('click', 'a', function() {
            $(window).off('beforeunload');
        });
    }
})(jQuery);

// ─── Send Agreement Overview (bulk) ──────────────────────────────────────────
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('vep-send-agreement-overview');
        if (!btn) { return; }

        btn.addEventListener('click', function () {
            var eventId = btn.getAttribute('data-event-id');
            if (!eventId || btn.disabled) { return; }

            if (!window.confirm(
                (typeof vepAdmin !== 'undefined' && vepAdmin.i18n && vepAdmin.i18n.confirmSendOverview)
                    ? vepAdmin.i18n.confirmSendOverview
                    : 'Send agreement overview to all eligible participants?'
            )) { return; }

            btn.disabled = true;
            var originalText = btn.textContent;
            btn.textContent = (typeof vepAdmin !== 'undefined' && vepAdmin.i18n && vepAdmin.i18n.sending)
                ? vepAdmin.i18n.sending
                : 'Sending…';

            var data = new FormData();
            data.append('action', 'vep_send_agreement_overview');
            data.append('nonce', (typeof vepAdmin !== 'undefined') ? vepAdmin.nonce : '');
            data.append('event_id', eventId);

            fetch((typeof vepAdmin !== 'undefined') ? vepAdmin.ajaxUrl : ajaxurl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
            })
            .then(function (res) { return res.json(); })
            .then(function (response) {
                if (response.success) {
                    if (window.vepToast) { window.vepToast(response.data.message || 'Sent.', 'success'); }
                    btn.textContent = (typeof vepAdmin !== 'undefined' && vepAdmin.i18n && vepAdmin.i18n.agreementOverviewSent)
                        ? vepAdmin.i18n.agreementOverviewSent
                        : 'Agreement Overview Sent';
                    btn.disabled = true;
                } else {
                    var msg = (response.data && response.data.message) ? response.data.message : 'Error';
                    if (window.vepToast) { window.vepToast(msg, 'error'); }
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
            })
            .catch(function () {
                if (window.vepToast) { window.vepToast('Request failed.', 'error'); }
                btn.textContent = originalText;
                btn.disabled = false;
            });
        });
    });
}());

// ─── Send Single Agreement Overview ──────────────────────────────────────────
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var btn = document.getElementById('vep-send-single-agreement-overview');
        if (!btn) { return; }

        btn.addEventListener('click', function () {
            var participantId = btn.getAttribute('data-participant-id');
            var eventId       = btn.getAttribute('data-event-id');
            if (!participantId || !eventId || btn.disabled) { return; }

            btn.disabled = true;

            var data = new FormData();
            data.append('action', 'vep_send_single_agreement_overview');
            data.append('nonce', (typeof vepAdmin !== 'undefined') ? vepAdmin.nonce : '');
            data.append('participant_id', participantId);
            data.append('event_id', eventId);

            fetch((typeof vepAdmin !== 'undefined') ? vepAdmin.ajaxUrl : ajaxurl, {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
            })
            .then(function (res) { return res.json(); })
            .then(function (response) {
                var msg = (response.data && response.data.message) ? response.data.message : (response.success ? 'Sent.' : 'Error');
                if (window.vepToast) {
                    window.vepToast(msg, response.success ? 'success' : 'error');
                }
                btn.disabled = false;
            })
            .catch(function () {
                if (window.vepToast) { window.vepToast('Request failed.', 'error'); }
                btn.disabled = false;
            });
        });
    });
}());
