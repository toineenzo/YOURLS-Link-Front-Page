/* Link Front Page — admin script */
(() => {
    'use strict';

    const bootstrap = JSON.parse(document.getElementById('lfp-bootstrap').textContent);
    const allLinks = Array.isArray(bootstrap.allLinks) ? bootstrap.allLinks : [];
    const linkMap = Object.create(null);
    for (const link of allLinks) {
        linkMap[link.keyword] = link;
    }
    const platforms = bootstrap.platforms || {};

    let items = Array.isArray(bootstrap.items) ? structuredClone(bootstrap.items) : [];
    let socials = Array.isArray(bootstrap.socials) ? structuredClone(bootstrap.socials) : [];

    const form = document.getElementById('lfp-form');
    const tree = document.getElementById('lfp-tree');
    const itemsJsonInput = document.getElementById('lfp-items-json');
    const socialsJsonInput = document.getElementById('lfp-socials-json');
    const socialsList = document.getElementById('lfp-socials');
    const tplLink = document.getElementById('lfp-tpl-link');
    const tplCategory = document.getElementById('lfp-tpl-category');
    const tplSocial = document.getElementById('lfp-tpl-social');
    const picker = document.getElementById('lfp-picker');
    const pickerInput = document.getElementById('lfp-picker-q');
    const pickerList = document.getElementById('lfp-picker-list');

    const uid = (prefix) => prefix + '_' + Math.random().toString(36).slice(2, 10) + Date.now().toString(36).slice(-4);

    /* -------------------------------------------------- Confirm dialog */

    const confirmDialog  = document.getElementById('lfp-confirm');
    const confirmTitle   = document.getElementById('lfp-confirm-title');
    const confirmMessage = document.getElementById('lfp-confirm-message');
    const confirmCancel  = document.getElementById('lfp-confirm-cancel');
    const confirmOk      = document.getElementById('lfp-confirm-ok');

    function lfpConfirm({ title = 'Confirm', message = 'Are you sure?', confirmLabel = 'Confirm', cancelLabel = 'Cancel', danger = false } = {}) {
        return new Promise((resolve) => {
            if (!confirmDialog) {
                resolve(window.confirm(message));
                return;
            }
            confirmTitle.textContent = title;
            confirmMessage.textContent = message;
            confirmOk.textContent = confirmLabel;
            confirmCancel.textContent = cancelLabel;
            confirmOk.classList.toggle('lfp-btn-danger',  !!danger);
            confirmOk.classList.toggle('lfp-btn-primary', !danger);

            const cleanup = (result) => {
                confirmOk.removeEventListener('click', okHandler);
                confirmCancel.removeEventListener('click', cancelHandler);
                confirmDialog.removeEventListener('cancel', cancelHandler);
                if (confirmDialog.open) confirmDialog.close();
                resolve(result);
            };
            const okHandler     = (e) => { e.preventDefault(); cleanup(true);  };
            const cancelHandler = (e) => { if (e) e.preventDefault(); cleanup(false); };

            confirmOk.addEventListener('click', okHandler);
            confirmCancel.addEventListener('click', cancelHandler);
            confirmDialog.addEventListener('cancel', cancelHandler);

            if (typeof confirmDialog.showModal === 'function') {
                confirmDialog.showModal();
            } else {
                confirmDialog.setAttribute('open', '');
            }
            // Auto-focus the cancel button so a stray Enter doesn't destroy data.
            setTimeout(() => confirmCancel.focus(), 30);
        });
    }

    /* -------------------------------------------------- File input wrapper */

    const enhanceFileInputs = (root) => {
        const scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('input[type="file"]').forEach((input) => {
            if (input.dataset.lfpEnhanced === '1') return;
            input.dataset.lfpEnhanced = '1';
            input.classList.add('lfp-fileinput-native');

            const wrap = document.createElement('div');
            wrap.className = 'lfp-fileinput';

            const trigger = document.createElement('button');
            trigger.type = 'button';
            trigger.className = 'lfp-btn lfp-btn-tight lfp-fileinput-trigger';
            trigger.innerHTML = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg><span>Choose file</span>';

            const label = document.createElement('span');
            label.className = 'lfp-fileinput-name';
            label.textContent = 'No file selected';

            const clear = document.createElement('button');
            clear.type = 'button';
            clear.className = 'lfp-fileinput-clear';
            clear.textContent = 'Clear';
            clear.hidden = true;

            const updateLabel = () => {
                if (input.files && input.files.length > 0) {
                    label.textContent = input.files.length === 1
                        ? input.files[0].name
                        : `${input.files.length} files selected`;
                    label.classList.add('is-selected');
                    clear.hidden = false;
                } else {
                    label.textContent = 'No file selected';
                    label.classList.remove('is-selected');
                    clear.hidden = true;
                }
            };

            trigger.addEventListener('click', () => input.click());
            input.addEventListener('change', updateLabel);
            clear.addEventListener('click', () => {
                input.value = '';
                input.dispatchEvent(new Event('change'));
            });

            input.parentNode.insertBefore(wrap, input);
            wrap.appendChild(trigger);
            wrap.appendChild(label);
            wrap.appendChild(clear);
            wrap.appendChild(input);

            updateLabel();
        });
    };

    // Catch any file inputs that get inserted later (from cloned templates).
    if (typeof MutationObserver === 'function') {
        const obs = new MutationObserver((mutations) => {
            for (const m of mutations) {
                m.addedNodes.forEach((n) => { if (n.nodeType === 1) enhanceFileInputs(n); });
            }
        });
        obs.observe(document.body, { childList: true, subtree: true });
    }

    const ensureIds = (list) => {
        for (const item of list) {
            if (!item.id) item.id = uid(item.type === 'category' ? 'cat' : 'link');
            if (item.type === 'category') {
                if (!Array.isArray(item.children)) item.children = [];
                ensureIds(item.children);
            }
        }
    };
    ensureIds(items);

    /* -------------------------------------------------- Render */

    const cssUrl = (url) => 'url(' + JSON.stringify(url) + ')';

    const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));

    const updateLinkFallback = (node, item) => {
        const fallback = node.querySelector('[data-lfp-fallback]');
        if (!fallback) return;
        const data = linkMap[item.keyword];
        const t = (item.title || '').trim();
        if (t !== '') {
            fallback.textContent = t;
            fallback.style.opacity = '0.7';
        } else if (data) {
            fallback.textContent = data.title || data.url || '(unknown link)';
            fallback.style.opacity = '0.7';
        } else {
            fallback.textContent = '⚠ shortlink missing in YOURLS';
            fallback.style.color = 'var(--lfp-a-danger)';
        }
    };

    const updateCategoryCount = (node, item) => {
        const el = node.querySelector('[data-lfp-count]');
        if (!el) return;
        const n = (item.children || []).length;
        el.textContent = n + ' link' + (n === 1 ? '' : 's');
    };

    const renderItem = (item) => {
        const isCategory = item.type === 'category';
        const tpl = isCategory ? tplCategory : tplLink;
        const node = tpl.content.firstElementChild.cloneNode(true);
        node.dataset.id = item.id;

        const titleInput = node.querySelector('[data-lfp-title]');
        const descInput  = node.querySelector('[data-lfp-description]');
        const urlInput   = node.querySelector('[data-lfp-image-url]');
        const fileInput  = node.querySelector('[data-lfp-image-file]');
        const thumb      = node.querySelector('[data-lfp-thumb]');
        const toggleBtn  = node.querySelector('[data-lfp-toggle]');
        const removeBtn  = node.querySelector('[data-lfp-remove]');

        titleInput.value = item.title || '';
        descInput.value = item.description || '';
        urlInput.value = item.image || '';
        if (item.image) thumb.style.backgroundImage = cssUrl(item.image);

        // Per-item file input — server maps it back via name="item_<id>"
        fileInput.name = 'item_' + item.id;

        titleInput.addEventListener('input', (e) => {
            item.title = e.target.value;
            if (isCategory) {
                const display = node.querySelector('[data-lfp-display-title]');
                if (display) display.textContent = e.target.value || 'Category';
            } else {
                updateLinkFallback(node, item);
            }
        });

        descInput.addEventListener('input', (e) => {
            item.description = e.target.value;
        });

        urlInput.addEventListener('input', (e) => {
            item.image = e.target.value;
            thumb.style.backgroundImage = item.image ? cssUrl(item.image) : '';
        });

        fileInput.addEventListener('change', (e) => {
            const f = e.target.files && e.target.files[0];
            if (!f) return;
            const reader = new FileReader();
            reader.addEventListener('load', (ev) => {
                const dataUrl = String(ev.target.result);
                item.image = dataUrl;
                urlInput.value = dataUrl;
                thumb.style.backgroundImage = cssUrl(dataUrl);
            });
            reader.readAsDataURL(f);
        });

        const clearBtn = node.querySelector('[data-lfp-image-clear]');
        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                item.image = '';
                urlInput.value = '';
                fileInput.value = '';
                thumb.style.backgroundImage = '';
                fileInput.dispatchEvent(new Event('change'));
            });
        }

        toggleBtn.addEventListener('click', () => {
            const body = node.querySelector('.lfp-item-body');
            body.hidden = !body.hidden;
        });

        removeBtn.addEventListener('click', async () => {
            const isCategory = item.type === 'category';
            const label = (item.title || '').trim()
                || (item.keyword ? '/' + item.keyword : '')
                || (isCategory ? 'this category' : 'this link');
            const ok = await lfpConfirm({
                title: isCategory ? 'Remove category' : 'Remove link',
                message: isCategory
                    ? `Remove "${label}" and any links nested inside it from the homepage list?`
                    : `Remove "${label}" from the homepage list?`,
                confirmLabel: 'Remove',
                danger: true,
            });
            if (!ok) return;
            removeItemById(item.id);
            renderTree();
        });

        // Drag from handle only
        const handle = node.querySelector('.lfp-handle');
        node.addEventListener('mousedown', (e) => {
            node.draggable = !!e.target.closest('.lfp-handle');
        });
        node.addEventListener('mouseleave', () => { node.draggable = false; });
        handle.addEventListener('dragstart', (e) => {
            // Trigger via the article's draggable
        });

        node.addEventListener('dragstart', (e) => {
            if (!node.draggable) {
                e.preventDefault();
                return;
            }
            dragState.id = item.id;
            dragState.type = item.type;
            node.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', item.id);
            e.stopPropagation();
        });

        node.addEventListener('dragend', () => {
            node.classList.remove('is-dragging');
            node.draggable = false;
            clearDropMarkers();
            dragState.id = null;
            dragState.type = null;
        });

        node.addEventListener('dragover', (e) => onItemDragOver(e, node, item));
        node.addEventListener('dragleave', (e) => onItemDragLeave(e, node));
        node.addEventListener('drop', (e) => onItemDrop(e, node, item));

        if (isCategory) {
            const childrenContainer = node.querySelector('[data-lfp-children]');
            if (!Array.isArray(item.children)) item.children = [];
            renderItems(childrenContainer, item.children);
            attachContainerDnd(childrenContainer, item);
            updateCategoryCount(node, item);
            const display = node.querySelector('[data-lfp-display-title]');
            display.textContent = item.title || 'Category';
        } else {
            const keywordEl = node.querySelector('[data-lfp-keyword]');
            keywordEl.textContent = item.keyword;
            updateLinkFallback(node, item);
        }

        return node;
    };

    const renderItems = (container, list) => {
        container.replaceChildren();
        for (const item of list) {
            container.appendChild(renderItem(item));
        }
    };

    const renderTree = () => {
        renderItems(tree, items);
        attachContainerDnd(tree, null);
    };

    /* -------------------------------------------------- Item lookup */

    const findItem = (id, list = items, parent = items) => {
        for (let i = 0; i < list.length; i++) {
            if (list[i].id === id) return { item: list[i], parent: list, index: i };
            if (list[i].type === 'category' && Array.isArray(list[i].children)) {
                const found = findItem(id, list[i].children, list[i].children);
                if (found) return found;
            }
        }
        return null;
    };

    const removeItemById = (id) => {
        const found = findItem(id);
        if (!found) return null;
        return found.parent.splice(found.index, 1)[0];
    };

    /* -------------------------------------------------- Drag & drop */

    const dragState = { id: null, type: null };

    const clearDropMarkers = () => {
        document.querySelectorAll('.is-drop-above, .is-drop-below').forEach((el) => {
            el.classList.remove('is-drop-above', 'is-drop-below');
        });
        document.querySelectorAll('.is-drop-target').forEach((el) => {
            el.classList.remove('is-drop-target');
        });
    };

    const isDescendantCategory = (ancestorId, descendantId) => {
        const found = findItem(ancestorId);
        if (!found || found.item.type !== 'category') return false;
        const stack = [...(found.item.children || [])];
        while (stack.length) {
            const cur = stack.pop();
            if (cur.id === descendantId) return true;
            if (cur.type === 'category' && Array.isArray(cur.children)) stack.push(...cur.children);
        }
        return false;
    };

    function onItemDragOver(e, node, item) {
        if (!dragState.id || dragState.id === item.id) return;
        // Categories can only be reordered at the top level (no nested categories)
        const targetIsTopLevel = node.parentElement === tree;
        if (dragState.type === 'category' && !targetIsTopLevel) return;

        e.preventDefault();
        e.stopPropagation();
        e.dataTransfer.dropEffect = 'move';

        const rect = node.getBoundingClientRect();
        const above = (e.clientY - rect.top) < rect.height / 2;
        node.classList.toggle('is-drop-above', above);
        node.classList.toggle('is-drop-below', !above);
    }

    function onItemDragLeave(e, node) {
        // Only clear if leaving the node itself, not entering a child
        const related = e.relatedTarget;
        if (related && node.contains(related)) return;
        node.classList.remove('is-drop-above', 'is-drop-below');
    }

    function onItemDrop(e, node, item) {
        if (!dragState.id || dragState.id === item.id) return;
        e.preventDefault();
        e.stopPropagation();

        const rect = node.getBoundingClientRect();
        const above = (e.clientY - rect.top) < rect.height / 2;

        moveBesideItem(dragState.id, item.id, above ? 'before' : 'after');
        renderTree();
    }

    function attachContainerDnd(container, ownerCategory) {
        container.addEventListener('dragover', (e) => {
            if (!dragState.id) return;
            // Reject dropping a category into a category's children list
            if (ownerCategory && dragState.type === 'category') return;
            // Don't drop a category into itself or its descendants
            if (ownerCategory && (ownerCategory.id === dragState.id || isDescendantCategory(dragState.id, ownerCategory.id))) return;

            // Only handle when the cursor is in container "padding" (not over child item)
            const directlyOnContainer = e.target === container;
            if (!directlyOnContainer && !container.contains(e.target)) return;

            // If we're hovering over a child item, that handler manages it
            const hoveringChild = [...container.children].some((ch) => ch.contains(e.target));
            if (hoveringChild) return;

            e.preventDefault();
            e.stopPropagation();
            e.dataTransfer.dropEffect = 'move';
            container.classList.add('is-drop-target');
        });

        container.addEventListener('dragleave', (e) => {
            const related = e.relatedTarget;
            if (related && container.contains(related)) return;
            container.classList.remove('is-drop-target');
        });

        container.addEventListener('drop', (e) => {
            if (!dragState.id) return;
            if (ownerCategory && dragState.type === 'category') return;
            if (ownerCategory && (ownerCategory.id === dragState.id || isDescendantCategory(dragState.id, ownerCategory.id))) return;
            const hoveringChild = [...container.children].some((ch) => ch.contains(e.target) && ch !== e.target);
            if (hoveringChild) return;

            e.preventDefault();
            e.stopPropagation();
            container.classList.remove('is-drop-target');

            const targetList = ownerCategory ? ownerCategory.children : items;
            moveToEnd(dragState.id, targetList);
            renderTree();
        });
    }

    function moveBesideItem(srcId, targetId, where) {
        if (srcId === targetId) return;
        const src = findItem(srcId);
        const tgt = findItem(targetId);
        if (!src || !tgt) return;

        // Don't drop a category into another category's children
        const targetIsTopLevel = tgt.parent === items;
        if (src.item.type === 'category' && !targetIsTopLevel) return;

        // Splice out source
        src.parent.splice(src.index, 1);

        // Re-find target index (may have shifted)
        const reTgt = findItem(targetId);
        if (!reTgt) {
            // Target somehow gone — append to top level
            items.push(src.item);
            return;
        }
        const insertIndex = where === 'before' ? reTgt.index : reTgt.index + 1;
        reTgt.parent.splice(insertIndex, 0, src.item);
    }

    function moveToEnd(srcId, targetList) {
        const src = findItem(srcId);
        if (!src) return;
        const item = src.item;
        src.parent.splice(src.index, 1);
        targetList.push(item);
    }

    /* -------------------------------------------------- Buttons */

    document.getElementById('lfp-add-category').addEventListener('click', () => {
        items.push({
            id: uid('cat'),
            type: 'category',
            title: 'New category',
            description: '',
            image: '',
            children: [],
        });
        renderTree();
    });

    /* Pull a default favicon from Google's s2 endpoint based on the link's
       destination URL. The user can replace it with their own image any time. */
    const faviconFor = (url) => {
        if (!url) return '';
        let host = '';
        try { host = new URL(url).hostname; } catch { /* URL might be schemeless */ }
        if (!host) {
            const m = String(url).match(/^(?:https?:\/\/)?([^\/?#]+)/i);
            if (m) host = m[1];
        }
        return host
            ? `https://www.google.com/s2/favicons?sz=128&domain=${encodeURIComponent(host)}`
            : '';
    };

    document.getElementById('lfp-add-link').addEventListener('click', () => {
        openPicker((keyword) => {
            const linkData = linkMap[keyword] || {};
            const newItem = {
                id: uid('link'),
                type: 'link',
                keyword,
                title: '',
                description: '',
                image: faviconFor(linkData.url || ''),
            };
            items.push(newItem);
            renderTree();
            // Expand the newly added item's editor right away — saves the
            // user a click and makes it obvious where to type the custom
            // title / description / image.
            const node = tree.querySelector(`[data-id="${newItem.id}"]`);
            if (node) {
                const body = node.querySelector('.lfp-item-body');
                if (body) body.hidden = false;
                node.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                node.querySelector('[data-lfp-title]')?.focus();
            }
        });
    });

    /* -------------------------------------------------- Picker */

    let pickerCallback = null;

    const renderPickerList = (query) => {
        const q = query.toLowerCase().trim();
        const matches = (q === ''
            ? allLinks
            : allLinks.filter((l) =>
                l.keyword.toLowerCase().includes(q) ||
                (l.title || '').toLowerCase().includes(q) ||
                (l.url || '').toLowerCase().includes(q),
            )
        ).slice(0, 200);

        if (matches.length === 0) {
            pickerList.innerHTML = '<li><em>No matches.</em></li>';
            return;
        }

        pickerList.innerHTML = matches.map((l) => `
            <li data-keyword="${escapeHtml(l.keyword)}">
                <span class="lfp-pick-keyword">${escapeHtml(l.keyword)}</span>
                <span class="lfp-pick-title">${escapeHtml(l.title || '(no title)')}</span>
                <span class="lfp-pick-url">${escapeHtml(l.url)}</span>
            </li>
        `).join('');
    };

    const openPicker = (cb) => {
        pickerCallback = cb;
        pickerInput.value = '';
        renderPickerList('');
        if (typeof picker.showModal === 'function') {
            picker.showModal();
        } else {
            picker.setAttribute('open', '');
        }
        setTimeout(() => pickerInput.focus(), 30);
    };

    pickerInput.addEventListener('input', (e) => renderPickerList(e.target.value));

    pickerList.addEventListener('click', (e) => {
        const li = e.target.closest('li[data-keyword]');
        if (!li) return;
        const keyword = li.dataset.keyword;
        if (pickerCallback) pickerCallback(keyword);
        picker.close();
    });

    pickerInput.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const first = pickerList.querySelector('li[data-keyword]');
        if (first) first.click();
    });

    /* -------------------------------------------------- Socials */

    const renderSocialIcon = (platform) => {
        const def = platforms[platform];
        if (!def) return '';
        return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">${def.svg}</svg>`;
    };

    const setSocialSourceUI = (row, source) => {
        const urlInput = row.querySelector('[data-lfp-social-url]');
        const kwWrap   = row.querySelector('[data-lfp-social-keyword-wrap]');
        if (source === 'keyword') {
            urlInput.hidden = true;
            kwWrap.hidden = false;
        } else {
            urlInput.hidden = false;
            kwWrap.hidden = true;
        }
    };

    const renderSocials = () => {
        socialsList.replaceChildren();
        for (const entry of socials) {
            socialsList.appendChild(renderSocialRow(entry));
        }
    };

    function renderSocialRow(entry) {
        const node = tplSocial.content.firstElementChild.cloneNode(true);
        node.dataset.id = entry.id;

        const iconSlot     = node.querySelector('[data-lfp-social-icon]');
        const platformSel  = node.querySelector('[data-lfp-social-platform]');
        const sourceSel    = node.querySelector('[data-lfp-social-source]');
        const urlInput     = node.querySelector('[data-lfp-social-url]');
        const keywordEl    = node.querySelector('[data-lfp-social-keyword]');
        const pickBtn      = node.querySelector('[data-lfp-social-pick]');
        const removeBtn    = node.querySelector('[data-lfp-social-remove]');

        platformSel.value = entry.platform;
        sourceSel.value   = entry.source || 'url';
        urlInput.value    = entry.url || '';
        keywordEl.textContent = entry.keyword ? entry.keyword : '—';
        iconSlot.style.color = (platforms[entry.platform]?.color) || 'var(--lfp-a-fg)';
        iconSlot.innerHTML = renderSocialIcon(entry.platform);
        setSocialSourceUI(node, sourceSel.value);

        platformSel.addEventListener('change', (e) => {
            entry.platform = e.target.value;
            iconSlot.style.color = (platforms[entry.platform]?.color) || 'var(--lfp-a-fg)';
            iconSlot.innerHTML = renderSocialIcon(entry.platform);
        });

        sourceSel.addEventListener('change', (e) => {
            entry.source = e.target.value === 'keyword' ? 'keyword' : 'url';
            setSocialSourceUI(node, entry.source);
        });

        urlInput.addEventListener('input', (e) => { entry.url = e.target.value; });

        pickBtn.addEventListener('click', () => {
            openPicker((kw) => {
                entry.keyword = kw;
                keywordEl.textContent = kw;
            });
        });

        removeBtn.addEventListener('click', () => {
            const idx = socials.findIndex((s) => s.id === entry.id);
            if (idx >= 0) socials.splice(idx, 1);
            renderSocials();
        });

        // Drag handle
        let mouseOnHandle = false;
        node.addEventListener('mousedown', (e) => {
            mouseOnHandle = !!e.target.closest('.lfp-handle');
            node.draggable = mouseOnHandle;
        });
        node.addEventListener('dragstart', (e) => {
            if (!mouseOnHandle) { e.preventDefault(); return; }
            socialDragId = entry.id;
            node.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', entry.id);
        });
        node.addEventListener('dragend', () => {
            node.classList.remove('is-dragging');
            node.draggable = false;
            socialDragId = null;
            socialsList.querySelectorAll('.is-drop-above, .is-drop-below').forEach((el) => {
                el.classList.remove('is-drop-above', 'is-drop-below');
            });
        });
        node.addEventListener('dragover', (e) => {
            if (!socialDragId || socialDragId === entry.id) return;
            e.preventDefault();
            const rect = node.getBoundingClientRect();
            const above = (e.clientY - rect.top) < rect.height / 2;
            node.classList.toggle('is-drop-above', above);
            node.classList.toggle('is-drop-below', !above);
        });
        node.addEventListener('dragleave', () => {
            node.classList.remove('is-drop-above', 'is-drop-below');
        });
        node.addEventListener('drop', (e) => {
            if (!socialDragId || socialDragId === entry.id) return;
            e.preventDefault();
            const rect = node.getBoundingClientRect();
            const above = (e.clientY - rect.top) < rect.height / 2;
            const srcIdx = socials.findIndex((s) => s.id === socialDragId);
            const tgtIdx = socials.findIndex((s) => s.id === entry.id);
            if (srcIdx < 0 || tgtIdx < 0) return;
            const [moving] = socials.splice(srcIdx, 1);
            const reTgt = socials.findIndex((s) => s.id === entry.id);
            const insertAt = above ? reTgt : reTgt + 1;
            socials.splice(insertAt, 0, moving);
            renderSocials();
        });

        return node;
    }

    let socialDragId = null;

    document.getElementById('lfp-add-social').addEventListener('click', () => {
        const firstPlatform = Object.keys(platforms)[0] || 'website';
        socials.push({
            id: uid('soc'),
            platform: firstPlatform,
            source: 'url',
            url: '',
            keyword: '',
            label: '',
        });
        renderSocials();
    });

    /* -------------------------------------------------- Tabs */

    document.querySelectorAll('.lfp-tab').forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.tab;
            document.querySelectorAll('.lfp-tab').forEach((t) => t.classList.toggle('is-active', t === btn));
            document.querySelectorAll('.lfp-pane').forEach((p) => p.classList.toggle('is-active', p.dataset.pane === target));
        });
    });

    /* Inner tab strip for the Personal / Business contact card. */
    document.querySelectorAll('.lfp-contact-tab').forEach((btn) => {
        btn.addEventListener('click', () => {
            const target = btn.dataset.contactTab;
            const scope = btn.closest('.lfp-contact');
            if (!scope) return;
            scope.querySelectorAll('.lfp-contact-tab').forEach((t) => t.classList.toggle('is-active', t === btn));
            scope.querySelectorAll('.lfp-contact-pane').forEach((p) => p.classList.toggle('is-active', p.dataset.contactPane === target));
        });
    });

    /* -------------------------------------------------- Reset */

    /* Per-tab reset buttons. Each button knows which scope it targets via
       data-lfp-reset, gets a scope-specific confirm message, and submits
       through the dedicated #lfp-reset-form (kept outside the main settings
       form so the user's other unsaved fields don't get persisted along
       with the reset). */
    const resetForm  = document.getElementById('lfp-reset-form');
    const resetCopy  = {
        links: {
            title: 'Remove all links?',
            message: 'This empties the homepage link list — every configured link and category is removed. Other settings (general, image grid, appearance) are kept.',
            confirmLabel: 'Remove all links',
        },
        general: {
            title: 'Reset general settings?',
            message: 'Site title, login path, footer toggles, About-me section and social-media buttons all revert to their defaults. Links, image grid and appearance are kept.',
            confirmLabel: 'Reset general settings',
        },
        image_grid: {
            title: 'Remove all image grid tiles?',
            message: 'Every tile in the image grid is removed. The grid stays enabled or disabled exactly as it is now. Other tabs are untouched.',
            confirmLabel: 'Remove all tiles',
        },
        appearance: {
            title: 'Reset appearance?',
            message: 'Colors, spacing, typography, background image and custom CSS all revert to defaults. Links, general settings and the image grid are kept.',
            confirmLabel: 'Reset appearance',
        },
    };

    document.querySelectorAll('[data-lfp-reset]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const scope = btn.dataset.lfpReset || 'all';
            const copy  = resetCopy[scope] || {
                title: 'Reset?',
                message: 'Are you sure?',
                confirmLabel: 'Reset',
            };
            const ok = await lfpConfirm({ ...copy, danger: true });
            if (!ok || !resetForm) return;
            resetForm.querySelector('input[name="reset_scope"]').value = scope;
            resetForm.submit();
        });
    });

    /* -------------------------------------------------- Font picker */

    const fontSource = document.getElementById('lfp-font-source');
    const fontSearch = document.getElementById('lfp-font-search');
    const fontGoogleSel = document.getElementById('lfp-font-google');

    const updateFontBlocks = () => {
        const value = fontSource.value;
        document.querySelectorAll('[data-lfp-fontblock]').forEach((el) => {
            el.style.display = el.dataset.lfpFontblock === value ? '' : 'none';
        });
    };
    if (fontSource) {
        fontSource.addEventListener('change', updateFontBlocks);
        updateFontBlocks();
    }

    if (fontSearch && fontGoogleSel) {
        fontSearch.addEventListener('input', (e) => {
            const q = e.target.value.toLowerCase().trim();
            for (const opt of fontGoogleSel.options) {
                const family = opt.value.toLowerCase();
                const cat = (opt.dataset.category || '').toLowerCase();
                opt.hidden = q !== '' && !family.includes(q) && !cat.includes(q);
            }
        });
    }

    /* -------- Live font preview (works for all sources) */

    const fontPreview = document.getElementById('lfp-font-preview');
    const fontFamilyInput = document.getElementById('lfp-font');
    const loadedGoogleFonts = new Set();
    let customFontStyleEl = null;

    const loadGoogleFont = (family) => {
        if (!family || loadedGoogleFonts.has(family)) return;
        loadedGoogleFonts.add(family);
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = `https://fonts.googleapis.com/css2?family=${encodeURIComponent(family).replace(/%20/g, '+')}:wght@400;600;700&display=swap`;
        document.head.appendChild(link);
    };

    const installCustomFontFace = () => {
        // Use the saved custom font URL (set via hidden input after upload)
        // OR a temporary blob URL from the file input the user just picked.
        const urlInput = document.querySelector('input[name="font_custom_url"]');
        const fileInput = document.querySelector('input[name="font_custom_file"]');
        let url = urlInput?.value || '';
        let format = document.querySelector('input[name="font_custom_format"]')?.value || 'woff2';

        if (fileInput?.files && fileInput.files[0]) {
            const f = fileInput.files[0];
            url = URL.createObjectURL(f);
            const ext = f.name.split('.').pop().toLowerCase();
            format = { woff2: 'woff2', woff: 'woff', ttf: 'truetype', otf: 'opentype' }[ext] || 'woff2';
        }

        if (!url) return false;
        if (!customFontStyleEl) {
            customFontStyleEl = document.createElement('style');
            document.head.appendChild(customFontStyleEl);
        }
        customFontStyleEl.textContent = `@font-face{font-family:"LFPCustomPreview";src:url("${url}") format("${format}");font-display:swap;}`;
        return true;
    };

    const refreshFontPreview = () => {
        if (!fontPreview) return;
        const source = fontSource ? fontSource.value : 'system';

        if (source === 'google') {
            const family = fontGoogleSel?.value || '';
            if (family) {
                loadGoogleFont(family);
                fontPreview.style.fontFamily = `"${family.replace(/"/g, '')}", sans-serif`;
            } else {
                fontPreview.style.fontFamily = fontFamilyInput?.value || '';
            }
        } else if (source === 'custom') {
            if (installCustomFontFace()) {
                fontPreview.style.fontFamily = '"LFPCustomPreview", sans-serif';
            } else {
                fontPreview.style.fontFamily = fontFamilyInput?.value || '';
            }
        } else {
            fontPreview.style.fontFamily = fontFamilyInput?.value || '';
        }
    };

    fontGoogleSel?.addEventListener('change', refreshFontPreview);
    fontSource?.addEventListener('change', refreshFontPreview);
    fontFamilyInput?.addEventListener('input', refreshFontPreview);
    document.querySelector('input[name="font_custom_file"]')?.addEventListener('change', refreshFontPreview);

    refreshFontPreview();

    /* -------------------------------------------------- Instagram grid */

    const imgGrid = document.getElementById('lfp-imgrid-grid');
    const imgJsonInput = document.getElementById('lfp-imgrid-json');
    const tplImgTile = document.getElementById('lfp-tpl-ig-tile');
    const tplImgAdd  = document.getElementById('lfp-tpl-ig-add');
    const imgDialog = document.getElementById('lfp-imgrid-dialog');

    let imgItems = Array.isArray(bootstrap.imageGrid) ? structuredClone(bootstrap.imageGrid) : [];
    let imgEditId = null; // null when adding, item.id when editing
    let imgDragId = null;

    const renderImg = () => {
        if (!imgGrid) return;
        imgGrid.replaceChildren();
        for (const it of imgItems) {
            imgGrid.appendChild(renderImgTile(it));
        }
        // Add a single placeholder for the next empty cell
        imgGrid.appendChild(renderImgAdd());
    };

    function renderImgTile(item) {
        const node = tplImgTile.content.firstElementChild.cloneNode(true);
        node.dataset.id = item.id;

        const imgSlot   = node.querySelector('[data-lfp-imgrid-img]');
        const overlay   = node.querySelector('[data-lfp-imgrid-overlay]');
        const titleEl   = node.querySelector('[data-lfp-imgrid-title]');
        const editBtn   = node.querySelector('[data-lfp-imgrid-edit]');
        const removeBtn = node.querySelector('[data-lfp-imgrid-remove]');

        if (item.image) imgSlot.style.backgroundImage = cssUrl(item.image);
        titleEl.textContent = item.title || '';
        overlay.dataset.show = item.show_mode || 'always';
        if (!item.title || (item.show_mode === 'never')) {
            overlay.classList.add('is-empty');
        }

        // Bulk-uploaded tiles arrive without a destination URL — flag them
        // so the user knows which ones still need a click.
        const needsDetails = !item.url && !item.keyword;
        if (needsDetails) node.classList.add('lfp-imgrid-needs-details');

        editBtn.addEventListener('click', () => openImgDialog(item.id));
        removeBtn.addEventListener('click', async () => {
            const ok = await lfpConfirm({
                title: 'Remove image tile',
                message: 'Remove this tile from the image grid?',
                confirmLabel: 'Remove',
                danger: true,
            });
            if (!ok) return;
            const idx = imgItems.findIndex((i) => i.id === item.id);
            if (idx >= 0) imgItems.splice(idx, 1);
            renderImg();
        });

        // Drag-drop
        let onHandle = false;
        node.addEventListener('mousedown', (e) => {
            onHandle = !!e.target.closest('.lfp-handle');
            node.draggable = onHandle;
        });
        node.addEventListener('dragstart', (e) => {
            if (!onHandle) { e.preventDefault(); return; }
            imgDragId = item.id;
            node.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        node.addEventListener('dragend', () => {
            node.classList.remove('is-dragging');
            node.draggable = false;
            imgDragId = null;
            imgGrid.querySelectorAll('.is-drop-target').forEach((el) => el.classList.remove('is-drop-target'));
        });
        node.addEventListener('dragover', (e) => {
            if (!imgDragId || imgDragId === item.id) return;
            e.preventDefault();
            node.classList.add('is-drop-target');
        });
        node.addEventListener('dragleave', () => {
            node.classList.remove('is-drop-target');
        });
        node.addEventListener('drop', (e) => {
            if (!imgDragId || imgDragId === item.id) return;
            e.preventDefault();
            const srcIdx = imgItems.findIndex((i) => i.id === imgDragId);
            const tgtIdx = imgItems.findIndex((i) => i.id === item.id);
            if (srcIdx < 0 || tgtIdx < 0) return;
            const [moved] = imgItems.splice(srcIdx, 1);
            const re = imgItems.findIndex((i) => i.id === item.id);
            imgItems.splice(re, 0, moved);
            renderImg();
        });

        return node;
    }

    function renderImgAdd() {
        const btn = tplImgAdd.content.firstElementChild.cloneNode(true);
        btn.addEventListener('click', () => openImgDialog(null));
        return btn;
    }

    /* -------- Bulk image upload */

    const imgBulkBtn = document.getElementById('lfp-imgrid-bulk');
    const imgBulkInput = document.getElementById('lfp-imgrid-bulk-input');

    const readFileAsDataUrl = (file) => new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.addEventListener('load', (ev) => resolve(String(ev.target.result)));
        reader.addEventListener('error', () => reject(reader.error));
        reader.readAsDataURL(file);
    });

    imgBulkBtn?.addEventListener('click', () => imgBulkInput?.click());

    imgBulkInput?.addEventListener('change', async (e) => {
        const files = Array.from(e.target.files || []);
        if (files.length === 0) return;
        imgBulkBtn.disabled = true;
        imgBulkBtn.textContent = `Reading ${files.length} image${files.length === 1 ? '' : 's'}…`;
        try {
            for (const f of files) {
                if (!f.type.startsWith('image/')) continue;
                if (f.size > 5 * 1024 * 1024) continue; // matches the server-side limit
                const image = await readFileAsDataUrl(f);
                imgItems.push({
                    id: uid('ig'),
                    source: 'url',
                    url: '',
                    keyword: '',
                    image,
                    title: '',
                    show_mode: 'always',
                });
            }
        } finally {
            imgBulkInput.value = ''; // allow re-selecting the same set
            imgBulkBtn.disabled = false;
            imgBulkBtn.innerHTML = '&#x2B73; Bulk upload images';
            renderImg();
        }
    });

    /* -------- Instagram dialog */

    const imgFields = {
        imageUrl:    document.getElementById('lfp-imgrid-image-url'),
        imageFile:   document.getElementById('lfp-imgrid-image-file'),
        source:      document.getElementById('lfp-imgrid-source'),
        url:         document.getElementById('lfp-imgrid-url'),
        keywordDisp: document.getElementById('lfp-imgrid-keyword-display'),
        title:       document.getElementById('lfp-imgrid-title-input'),
        showMode:    document.getElementById('lfp-imgrid-show-mode'),
    };
    let imgDialogState = {};

    const imgPreview = document.getElementById('lfp-imgrid-preview');

    const updateImgPreview = () => {
        if (!imgPreview) return;
        imgPreview.style.backgroundImage = imgDialogState.image ? cssUrl(imgDialogState.image) : '';
    };

    const isDataUrl = (v) => typeof v === 'string' && v.startsWith('data:');

    function openImgDialog(id) {
        imgEditId = id;
        imgDialogState = id
            ? structuredClone(imgItems.find((i) => i.id === id) || {})
            : { id: uid('ig'), source: 'url', url: '', keyword: '', image: '', title: '', show_mode: 'always' };

        // Show uploaded files as "(uploaded file)" in the URL field instead
        // of a multi-line base64 data: URL. The preview tile shows the actual
        // image, which is what the user cares about.
        if (isDataUrl(imgDialogState.image)) {
            imgFields.imageUrl.value = '';
            imgFields.imageUrl.placeholder = '(uploaded image shown above — paste a URL to replace)';
        } else {
            imgFields.imageUrl.value = imgDialogState.image || '';
            imgFields.imageUrl.placeholder = 'https://example.com/photo.jpg';
        }
        imgFields.imageFile.value = '';
        imgFields.source.value = imgDialogState.source || 'url';
        imgFields.url.value = imgDialogState.url || '';
        imgFields.keywordDisp.textContent = imgDialogState.keyword || '—';
        imgFields.title.value = imgDialogState.title || '';
        imgFields.showMode.value = imgDialogState.show_mode || 'always';
        updateImgSourceBlocks();
        updateImgPreview();
        document.getElementById('lfp-imgrid-dialog-title').textContent = id ? 'Edit image tile' : 'Add image tile';

        if (typeof imgDialog.showModal === 'function') imgDialog.showModal();
        else imgDialog.setAttribute('open', '');
    }

    function updateImgSourceBlocks() {
        const v = imgFields.source.value;
        document.querySelectorAll('[data-lfp-imgrid-block]').forEach((el) => {
            el.hidden = el.dataset.lfpImgridBlock !== v;
        });
    }

    if (imgFields.source) {
        imgFields.source.addEventListener('change', updateImgSourceBlocks);
    }

    imgFields.imageUrl?.addEventListener('input', (e) => {
        imgDialogState.image = e.target.value;
        updateImgPreview();
    });

    imgFields.imageFile?.addEventListener('change', async (e) => {
        const f = e.target.files && e.target.files[0];
        if (!f) return;
        imgDialogState.image = await readFileAsDataUrl(f);
        // Don't dump the base64 blob into the URL input; the preview shows
        // the picture instead.
        imgFields.imageUrl.value = '';
        imgFields.imageUrl.placeholder = '(uploaded image shown above — paste a URL to replace)';
        updateImgPreview();
    });

    imgFields.url?.addEventListener('input', (e) => {
        imgDialogState.url = e.target.value;
    });

    imgFields.title?.addEventListener('input', (e) => {
        imgDialogState.title = e.target.value;
    });

    imgFields.showMode?.addEventListener('change', (e) => {
        imgDialogState.show_mode = e.target.value;
    });

    document.getElementById('lfp-imgrid-pick')?.addEventListener('click', () => {
        openPicker((kw) => {
            imgDialogState.keyword = kw;
            imgFields.keywordDisp.textContent = kw;
        });
    });

    document.getElementById('lfp-imgrid-cancel')?.addEventListener('click', () => {
        imgDialog.close();
    });

    document.getElementById('lfp-imgrid-save')?.addEventListener('click', () => {
        const s = imgDialogState;
        s.source = imgFields.source.value === 'keyword' ? 'keyword' : 'url';
        if (!s.image) { alert('Please add an image for the tile.'); return; }
        if (s.source === 'keyword' && !s.keyword) { alert('Pick a YOURLS shortlink.'); return; }
        if (s.source === 'url' && !s.url) { alert('Enter a URL.'); return; }

        if (imgEditId) {
            const idx = imgItems.findIndex((i) => i.id === imgEditId);
            if (idx >= 0) imgItems[idx] = s;
        } else {
            imgItems.push(s);
        }
        imgDialog.close();
        renderImg();
    });

    /* -------------------------------------------------- Inline image inputs
       (logo / favicon / about photo / background image) — preview on upload,
       clear via the always-present .lfp-btn[data-lfp-image-clear] button. */

    document.querySelectorAll('.lfp-pane:not([data-pane="links"]) .lfp-image-input').forEach((wrap) => {
        const urlInput  = wrap.querySelector('[data-lfp-image-url]');
        const fileInput = wrap.querySelector('[data-lfp-image-file]');
        const clearBtn  = wrap.querySelector('[data-lfp-image-clear]');

        const ensureThumb = () => {
            let thumb = wrap.querySelector('.lfp-thumb');
            if (!thumb) {
                thumb = document.createElement('img');
                thumb.className = 'lfp-thumb';
                wrap.appendChild(thumb);
            }
            return thumb;
        };

        fileInput?.addEventListener('change', (e) => {
            const f = e.target.files && e.target.files[0];
            if (!f) return;
            const reader = new FileReader();
            reader.addEventListener('load', (ev) => {
                ensureThumb().src = String(ev.target.result);
            });
            reader.readAsDataURL(f);
        });

        urlInput?.addEventListener('input', (e) => {
            const v = e.target.value.trim();
            const thumb = wrap.querySelector('.lfp-thumb');
            if (v && thumb) thumb.src = v;
        });

        clearBtn?.addEventListener('click', () => {
            if (urlInput) urlInput.value = '';
            if (fileInput) {
                fileInput.value = '';
                fileInput.dispatchEvent(new Event('change'));
            }
            wrap.querySelector('.lfp-thumb')?.remove();
        });
    });

    /* -------------------------------------------------- Submit */

    form.addEventListener('submit', () => {
        // Strip transient fields and serialize
        const clean = (list) => list.map((item) => {
            const out = {
                id: item.id,
                type: item.type,
                title: item.title || '',
                description: item.description || '',
                image: item.image || '',
            };
            if (item.type === 'link') {
                out.keyword = item.keyword;
            } else {
                out.children = clean(item.children || []);
            }
            return out;
        });
        itemsJsonInput.value = JSON.stringify(clean(items));

        const cleanSocials = socials
            .filter((s) => s.platform && (
                (s.source === 'keyword' && s.keyword) ||
                (s.source !== 'keyword' && s.url)
            ))
            .map((s) => ({
                id: s.id,
                platform: s.platform,
                source: s.source === 'keyword' ? 'keyword' : 'url',
                url: s.url || '',
                keyword: s.keyword || '',
                label: s.label || '',
            }));
        socialsJsonInput.value = JSON.stringify(cleanSocials);

        // Drop transient data: URL inputs only carry data: previews. The
        // server-side handler keeps any data: URL as-is and the browser is
        // happy rendering them, but they bloat the option blob — only keep
        // them when the user uploaded a file (recognisable by data: prefix).
        if (imgJsonInput) {
            // Keep tiles that have an image, even if URL / keyword is still
            // empty (bulk-uploaded items the user hasn't filled in yet). The
            // public renderer filters incomplete tiles via lfp_resolve_image_grid.
            const cleanIg = imgItems
                .filter((i) => i.image)
                .map((i) => ({
                    id: i.id,
                    source: i.source === 'keyword' ? 'keyword' : 'url',
                    url: i.url || '',
                    keyword: i.keyword || '',
                    image: i.image || '',
                    title: i.title || '',
                    show_mode: ['always', 'hover', 'never'].includes(i.show_mode) ? i.show_mode : 'always',
                }));
            imgJsonInput.value = JSON.stringify(cleanIg);
        }
    });

    /* -------------------------------------------------- Init */
    renderTree();
    renderSocials();
    renderImg();
    enhanceFileInputs(document);
})();
