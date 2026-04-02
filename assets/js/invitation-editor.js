/**
 * PartyParart - Invitation Editor
 * Handles layout showcase, sidebar controls, inline editing,
 * auto-save, and drag & drop section ordering.
 */
(function() {
    'use strict';

    // ─── State ──────────────────────────────────────────────────────────
    var config = {};
    var saveTimeout = null;
    var saveStatus = 'saved';
    var activeEditable = null;
    var eventId = null;
    var dragState = null;
    var globalListenersBound = false;

    // ─── Font Map ───────────────────────────────────────────────────────
    var fontMap = {
        elegant: {
            display: "'Cormorant Garamond', Georgia, serif",
            body: "'DM Sans', -apple-system, sans-serif"
        },
        modern: {
            display: "'Inter', -apple-system, sans-serif",
            body: "'Inter', -apple-system, sans-serif"
        },
        playful: {
            display: "'Quicksand', 'Comic Sans MS', sans-serif",
            body: "'Nunito', sans-serif"
        },
        traditional: {
            display: "'Playfair Display', Georgia, serif",
            body: "'Lora', Georgia, serif"
        },
        minimal: {
            display: "'DM Sans', -apple-system, sans-serif",
            body: "'DM Sans', -apple-system, sans-serif"
        }
    };

    // ─── CSS Variable Map ───────────────────────────────────────────────
    var cssVarMap = {
        color_primary: '--inv-primary',
        color_secondary: '--inv-secondary',
        color_accent: '--inv-accent',
        color_text: '--inv-text',
        color_background: '--inv-background'
    };

    // ─── Save Status Messages ───────────────────────────────────────────
    var statusMessages = {
        unsaved: 'Ændringer ikke gemt...',
        saving: 'Gemmer...',
        saved: 'Alle ændringer gemt',
        error: 'Kunne ikke gemme — prøver igen...'
    };

    // ═══════════════════════════════════════════════════════════════════
    //  AUTO-SAVE
    // ═══════════════════════════════════════════════════════════════════

    function scheduleAutoSave() {
        if (saveTimeout) {
            clearTimeout(saveTimeout);
        }
        updateSaveStatus('unsaved');
        saveTimeout = setTimeout(doAutoSave, 2000);
    }

    function doAutoSave() {
        updateSaveStatus('saving');

        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/invitation-autosave.php', true);
        xhr.setRequestHeader('Content-Type', 'application/json');

        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;

            if (xhr.status >= 200 && xhr.status < 300) {
                updateSaveStatus('saved');
            } else {
                updateSaveStatus('error');
                setTimeout(doAutoSave, 5000);
            }
        };

        xhr.onerror = function() {
            updateSaveStatus('error');
            setTimeout(doAutoSave, 5000);
        };

        var payload = {};
        for (var key in config) {
            if (config.hasOwnProperty(key)) {
                payload[key] = config[key];
            }
        }
        payload.event_id = eventId;
        xhr.send(JSON.stringify(payload));
    }

    function updateSaveStatus(status) {
        saveStatus = status;
        var el = document.getElementById('save-status');
        if (!el) return;

        el.textContent = statusMessages[status] || '';
        el.className = 'save-status ' + status;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  SHOWCASE
    // ═══════════════════════════════════════════════════════════════════

    function initShowcase() {
        var cards = document.querySelectorAll('.showcase-card');
        var continueBtn = document.querySelector('.showcase-continue');
        var hiddenInput = document.getElementById('selected-layout');

        for (var i = 0; i < cards.length; i++) {
            cards[i].addEventListener('click', function() {
                // Deselect all
                for (var j = 0; j < cards.length; j++) {
                    cards[j].classList.remove('selected');
                }
                // Select clicked
                this.classList.add('selected');

                // Set hidden input value
                var layout = this.getAttribute('data-layout');
                if (hiddenInput && layout) {
                    hiddenInput.value = layout;
                }

                // Show continue button
                if (continueBtn) {
                    continueBtn.classList.add('visible');
                }
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  SIDEBAR
    // ═══════════════════════════════════════════════════════════════════

    // ─── Step Completion Map ───────────────────────────────────
    var stepPanels = ['images', 'text', 'design', 'sections', 'publish'];

    function initSidebar() {
        initSidebarTabs();
        initSidebarCollapse();
        initStepNavigation();
        initFontOptions();
        initColorInputs();
        initColorPresets();
        initTextInputs();
        initSectionToggles();
        initLayoutChange();
        initSectionDragDrop();
    }

    function initSidebarTabs() {
        var tabs = document.querySelectorAll('.sidebar-tab');

        for (var i = 0; i < tabs.length; i++) {
            tabs[i].addEventListener('click', function() {
                var panelName = this.getAttribute('data-panel');
                if (panelName) activatePanel(panelName);
            });
        }
    }

    function initSidebarCollapse() {
        var btn = document.querySelector('.sidebar-collapse-btn');
        if (!btn) return;

        btn.addEventListener('click', function() {
            var workspace = document.getElementById('inv-workspace');
            if (workspace) {
                workspace.classList.toggle('sidebar-collapsed');
            }
        });
    }

    function initStepNavigation() {
        var nextBtns = document.querySelectorAll('.step-next-btn');
        for (var i = 0; i < nextBtns.length; i++) {
            nextBtns[i].addEventListener('click', function() {
                var nextPanel = this.getAttribute('data-next-panel');
                if (nextPanel) {
                    activatePanel(nextPanel);
                }
            });
        }
    }

    function activatePanel(panelName) {
        var tabs = document.querySelectorAll('.sidebar-tab');
        var panels = document.querySelectorAll('.sidebar-panel');

        for (var j = 0; j < tabs.length; j++) {
            tabs[j].classList.remove('active');
        }
        for (var j = 0; j < panels.length; j++) {
            panels[j].classList.remove('active');
        }

        var tab = document.querySelector('.sidebar-tab[data-panel="' + panelName + '"]');
        var panel = document.getElementById('panel-' + panelName);
        if (tab) tab.classList.add('active');
        if (panel) panel.classList.add('active');

        // Scroll panel to top
        var panelsContainer = document.querySelector('.sidebar-panels');
        if (panelsContainer) panelsContainer.scrollTop = 0;
    }

    function updateStepCompletion() {
        // Check which steps are complete based on current state
        var heroExists = document.querySelector('.hero-preview img');
        var messageField = document.getElementById('field-message');
        var hasMessage = messageField && messageField.value.trim().length > 0;

        // Publish state is server-rendered, preserve it
        var publishTab = document.querySelector('.sidebar-tab[data-panel="publish"]');
        var publishComplete = publishTab && publishTab.classList.contains('completed');

        var completion = {
            images: !!heroExists,
            text: !!hasMessage,
            design: true,
            sections: true,
            publish: publishComplete
        };

        var completedCount = 0;
        var total = stepPanels.length;

        for (var i = 0; i < stepPanels.length; i++) {
            var panel = stepPanels[i];
            var tab = document.querySelector('.sidebar-tab[data-panel="' + panel + '"]');
            if (!tab) continue;

            if (completion[panel]) {
                tab.classList.add('completed');
                completedCount++;
            } else {
                tab.classList.remove('completed');
            }
        }

        // Update progress bar
        var fill = document.getElementById('steps-progress-fill');
        var label = document.getElementById('steps-progress-label');
        if (fill) fill.style.width = Math.round((completedCount / total) * 100) + '%';
        if (label) label.textContent = completedCount + '/' + total + ' trin fuldført';
    }

    function initFontOptions() {
        var options = document.querySelectorAll('.font-option');

        for (var i = 0; i < options.length; i++) {
            options[i].addEventListener('click', function() {
                // Deselect all
                for (var j = 0; j < options.length; j++) {
                    options[j].classList.remove('selected');
                }
                this.classList.add('selected');

                // Read radio value
                var radio = this.querySelector('input[type="radio"]');
                if (radio) {
                    radio.checked = true;
                    config.font_style = radio.value;
                    applyFontToPreview(radio.value);
                    scheduleAutoSave();
                }
            });
        }
    }

    function initColorInputs() {
        var inputs = document.querySelectorAll('.color-picker-row input[type="color"]');

        for (var i = 0; i < inputs.length; i++) {
            inputs[i].addEventListener('input', function() {
                var field = this.getAttribute('data-field');
                if (field) {
                    config[field] = this.value;
                    applyCssVariable(field, this.value);
                    scheduleAutoSave();
                }
            });
        }
    }

    function initColorPresets() {
        var presets = document.querySelectorAll('.color-preset-btn');

        for (var i = 0; i < presets.length; i++) {
            presets[i].addEventListener('click', function() {
                var colorsJson = this.getAttribute('data-colors');
                if (!colorsJson) return;

                var colors;
                try {
                    colors = JSON.parse(colorsJson);
                } catch (e) {
                    return;
                }

                // Apply all colors
                for (var field in colors) {
                    if (colors.hasOwnProperty(field)) {
                        config[field] = colors[field];
                        applyCssVariable(field, colors[field]);

                        // Update corresponding color input
                        var input = document.querySelector('.color-picker-row input[data-field="' + field + '"]');
                        if (input) {
                            input.value = colors[field];
                        }
                    }
                }

                scheduleAutoSave();
            });
        }
    }

    function initTextInputs() {
        var inputs = document.querySelectorAll('.sidebar-panel input[data-field], .sidebar-panel textarea[data-field]');

        for (var i = 0; i < inputs.length; i++) {
            inputs[i].addEventListener('input', function() {
                var field = this.getAttribute('data-field');
                if (!field) return;

                config[field] = this.value;

                // Sync to preview
                var previewEl = document.querySelector('[data-editable="' + field + '"]');
                if (previewEl) {
                    if (field === 'invitation_message') {
                        previewEl.innerHTML = this.value.replace(/\n/g, '<br>');
                    } else {
                        previewEl.textContent = this.value;
                    }
                }

                scheduleAutoSave();
                updateStepCompletion();
            });
        }
    }

    function initSectionToggles() {
        var checkboxes = document.querySelectorAll('.section-item input[type="checkbox"]');

        for (var i = 0; i < checkboxes.length; i++) {
            checkboxes[i].addEventListener('change', function() {
                var field = this.getAttribute('data-field');
                var sectionItem = this.closest('.section-item');
                var section = sectionItem ? sectionItem.getAttribute('data-section') : null;
                if (!field) return;

                config[field] = this.checked;

                // Show/hide section in preview
                if (section) {
                    var sectionEls = document.querySelectorAll('.inv-preview [data-section="' + section + '"]');
                    for (var j = 0; j < sectionEls.length; j++) {
                        sectionEls[j].style.display = this.checked ? '' : 'none';
                    }
                }

                scheduleAutoSave();
            });
        }
    }

    function initLayoutChange() {
        var select = document.getElementById('layout-change');
        if (!select) return;

        // Hidden select change handler (for backwards compat)
        select.addEventListener('change', function() {
            config.layout_style = this.value;
            loadPreviewFromServer();
            scheduleAutoSave();
        });

        // Visual layout picker cards
        var cards = document.querySelectorAll('.layout-picker-card');
        for (var i = 0; i < cards.length; i++) {
            cards[i].addEventListener('click', function() {
                var value = this.getAttribute('data-layout-value');
                if (!value || value === config.layout_style) return;

                // Update visual state
                var allCards = document.querySelectorAll('.layout-picker-card');
                for (var j = 0; j < allCards.length; j++) {
                    allCards[j].classList.remove('selected');
                }
                this.classList.add('selected');

                // Sync hidden select and trigger change
                select.value = value;
                config.layout_style = value;
                loadPreviewFromServer();
                scheduleAutoSave();
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  CLIENT-SIDE PREVIEW UPDATES
    // ═══════════════════════════════════════════════════════════════════

    function applyCssVariable(field, value) {
        var varName = cssVarMap[field];
        if (!varName) return;

        var preview = document.querySelector('.inv-preview');
        if (preview) {
            preview.style.setProperty(varName, value);
        }
    }

    function applyFontToPreview(fontStyle) {
        var fonts = fontMap[fontStyle];
        if (!fonts) return;

        var preview = document.querySelector('.inv-preview');
        if (!preview) return;

        preview.style.setProperty('--font-display', fonts.display);
        preview.style.setProperty('--font-body', fonts.body);
    }

    // ═══════════════════════════════════════════════════════════════════
    //  SERVER-SIDE PREVIEW RELOAD
    // ═══════════════════════════════════════════════════════════════════

    function loadPreviewFromServer() {
        var preview = document.querySelector('.inv-preview');
        if (!preview) return;

        preview.style.opacity = '0.5';

        var xhr = new XMLHttpRequest();
        var url = '/api/invitation-preview.php?event_id=' + encodeURIComponent(eventId) + '&format=partial';
        xhr.open('POST', url, true);
        xhr.setRequestHeader('Content-Type', 'application/json');

        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) return;

            if (xhr.status >= 200 && xhr.status < 300) {
                preview.innerHTML = xhr.responseText;
            }

            preview.style.opacity = '1';
            initPreviewEditor();
        };

        xhr.onerror = function() {
            preview.style.opacity = '1';
        };

        xhr.send(JSON.stringify(config));
    }

    // ═══════════════════════════════════════════════════════════════════
    //  INLINE EDITING
    // ═══════════════════════════════════════════════════════════════════

    function initPreviewEditor() {
        var preview = document.querySelector('.inv-preview');
        if (!preview) return;

        var editables = preview.querySelectorAll('[data-editable]');

        for (var i = 0; i < editables.length; i++) {
            editables[i].addEventListener('click', function(e) {
                e.stopPropagation();
                startInlineEdit(this);
            });
        }

        // Click outside editable → stop editing (only bind once)
        if (!globalListenersBound) {
            document.addEventListener('click', function(e) {
                if (!activeEditable) return;

                var toolbar = document.getElementById('floating-toolbar');
                if (toolbar && toolbar.contains(e.target)) return;
                if (activeEditable.contains(e.target)) return;

                stopInlineEdit();
            });

            // Escape key → stop editing
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && activeEditable) {
                    stopInlineEdit();
                }
            });

            globalListenersBound = true;
        }
    }

    function startInlineEdit(el) {
        if (activeEditable && activeEditable !== el) {
            stopInlineEdit();
        }

        activeEditable = el;
        el.setAttribute('contenteditable', 'true');
        el.focus();

        showToolbar(el);

        // Highlight corresponding sidebar field
        var field = el.getAttribute('data-editable');
        var sidebarInput = document.querySelector('.sidebar-panel [data-field="' + field + '"]');
        if (sidebarInput) {
            sidebarInput.classList.add('editing-active');
        }

        // Listen for input changes
        el.addEventListener('input', onEditableInput);
    }

    function onEditableInput() {
        if (!activeEditable) return;
        syncEditableToConfig(activeEditable);
        scheduleAutoSave();
    }

    function stopInlineEdit() {
        if (!activeEditable) return;

        var el = activeEditable;
        el.removeAttribute('contenteditable');
        el.removeEventListener('input', onEditableInput);

        // Sync final value
        syncEditableToConfig(el);

        // Remove sidebar highlight
        var field = el.getAttribute('data-editable');
        var sidebarInput = document.querySelector('.sidebar-panel [data-field="' + field + '"]');
        if (sidebarInput) {
            sidebarInput.classList.remove('editing-active');
        }

        hideToolbar();
        activeEditable = null;
    }

    function syncEditableToConfig(el) {
        var field = el.getAttribute('data-editable');
        if (!field) return;

        var value;
        if (field === 'invitation_message') {
            // Convert <br> to newlines and strip other tags
            var html = el.innerHTML;
            html = html.replace(/<br\s*\/?>/gi, '\n');
            var tmp = document.createElement('div');
            tmp.innerHTML = html;
            value = tmp.textContent || tmp.innerText || '';
        } else {
            value = el.textContent || '';
        }

        config[field] = value;

        // Sync to sidebar input
        var sidebarInput = document.querySelector('.sidebar-panel [data-field="' + field + '"]');
        if (sidebarInput) {
            sidebarInput.value = value;
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  FLOATING TOOLBAR
    // ═══════════════════════════════════════════════════════════════════

    function showToolbar(el) {
        var toolbar = document.getElementById('floating-toolbar');
        if (!toolbar) return;

        var previewPanel = document.querySelector('.inv-preview-panel');
        if (!previewPanel) return;

        var elRect = el.getBoundingClientRect();
        var panelRect = previewPanel.getBoundingClientRect();
        var scrollTop = previewPanel.scrollTop;

        toolbar.style.left = (elRect.left - panelRect.left) + 'px';
        toolbar.style.top = (elRect.top - panelRect.top + scrollTop - toolbar.offsetHeight - 8) + 'px';

        toolbar.classList.add('visible');
    }

    function hideToolbar() {
        var toolbar = document.getElementById('floating-toolbar');
        if (toolbar) {
            toolbar.classList.remove('visible');
        }
    }

    function initToolbar() {
        var toolbar = document.getElementById('floating-toolbar');
        if (!toolbar) return;

        // Bold
        var boldBtn = toolbar.querySelector('[data-action="bold"]');
        if (boldBtn) {
            boldBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                document.execCommand('bold', false, null);
                this.classList.toggle('active');
            });
        }

        // Italic
        var italicBtn = toolbar.querySelector('[data-action="italic"]');
        if (italicBtn) {
            italicBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                document.execCommand('italic', false, null);
                this.classList.toggle('active');
            });
        }

        // Color
        var colorInput = toolbar.querySelector('.toolbar-color-input');
        if (colorInput) {
            colorInput.addEventListener('input', function(e) {
                e.stopPropagation();
                document.execCommand('foreColor', false, this.value);
            });
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    //  SECTION DRAG & DROP
    // ═══════════════════════════════════════════════════════════════════

    function initSectionDragDrop() {
        var container = document.getElementById('section-list');
        if (!container) return;

        var handles = container.querySelectorAll('.drag-handle');

        for (var i = 0; i < handles.length; i++) {
            handles[i].addEventListener('mousedown', onDragStart);
        }
    }

    function onDragStart(e) {
        e.preventDefault();

        var item = this.closest('.section-item');
        if (!item) return;

        var container = document.getElementById('section-list');
        if (!container) return;

        item.classList.add('dragging');

        dragState = {
            item: item,
            container: container,
            startY: e.clientY
        };

        document.addEventListener('mousemove', onDragMove);
        document.addEventListener('mouseup', onDragEnd);
    }

    function onDragMove(e) {
        if (!dragState) return;

        var container = dragState.container;
        var dragging = dragState.item;
        var items = container.querySelectorAll('.section-item:not(.dragging)');

        for (var i = 0; i < items.length; i++) {
            var rect = items[i].getBoundingClientRect();
            var midY = rect.top + rect.height / 2;

            if (e.clientY < midY) {
                container.insertBefore(dragging, items[i]);
                return;
            }
        }

        // If cursor is below all items, append at end
        container.appendChild(dragging);
    }

    function onDragEnd() {
        if (!dragState) return;

        dragState.item.classList.remove('dragging');

        document.removeEventListener('mousemove', onDragMove);
        document.removeEventListener('mouseup', onDragEnd);

        updateSectionsOrder();
        scheduleAutoSave();

        dragState = null;
    }

    function updateSectionsOrder() {
        var items = document.querySelectorAll('#section-list .section-item');
        var order = {};

        for (var i = 0; i < items.length; i++) {
            var checkbox = items[i].querySelector('input[type="checkbox"]');
            var key = items[i].getAttribute('data-section') ||
                       (checkbox ? checkbox.getAttribute('data-section') : null);

            if (key) {
                order[key] = {
                    enabled: checkbox ? checkbox.checked : true,
                    position: i
                };
            }
        }

        config.sections_order = order;
    }

    // ═══════════════════════════════════════════════════════════════════
    //  INIT
    // ═══════════════════════════════════════════════════════════════════

    function init() {
        // Showcase mode
        var showcase = document.getElementById('layout-showcase');
        if (showcase) {
            initShowcase();
        }

        // Workspace mode
        var workspace = document.getElementById('inv-workspace');
        if (workspace) {
            // Parse config from data attribute
            var configJson = workspace.getAttribute('data-config');
            if (configJson) {
                try {
                    config = JSON.parse(configJson);
                } catch (e) {
                    config = {};
                }
            }

            eventId = parseInt(workspace.getAttribute('data-event-id'), 10) || null;

            initSidebar();
            initPreviewEditor();
            initToolbar();
            updateStepCompletion();

            // Apply initial styles
            if (config.font_style) {
                applyFontToPreview(config.font_style);
            }
            for (var field in cssVarMap) {
                if (cssVarMap.hasOwnProperty(field) && config[field]) {
                    applyCssVariable(field, config[field]);
                }
            }
        }
    }

    // Run init when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    // Expose reinit for external callers (e.g. after XHR preview reload)
    window.reinitPreviewEditor = initPreviewEditor;

    // Expose force auto-save for external callers (e.g. before page reload)
    window.forceAutoSave = function(callback) {
        if (saveTimeout) clearTimeout(saveTimeout);
        var xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/invitation-autosave.php', true);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && callback) callback();
        };
        xhr.onerror = function() { if (callback) callback(); };
        var payload = {};
        for (var key in config) {
            if (config.hasOwnProperty(key)) payload[key] = config[key];
        }
        payload.event_id = eventId;
        xhr.send(JSON.stringify(payload));
    };

})();
