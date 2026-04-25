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
                thumb.style.backgroundImage = cssUrl(String(ev.target.result));
            });
            reader.readAsDataURL(f);
        });

        toggleBtn.addEventListener('click', () => {
            const body = node.querySelector('.lfp-item-body');
            body.hidden = !body.hidden;
        });

        removeBtn.addEventListener('click', () => {
            if (!confirm('Remove this item from the link list?')) return;
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

    document.getElementById('lfp-add-link').addEventListener('click', () => {
        openPicker((keyword) => {
            items.push({
                id: uid('link'),
                type: 'link',
                keyword,
                title: '',
                description: '',
                image: '',
            });
            renderTree();
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

    /* -------------------------------------------------- Reset */

    document.getElementById('lfp-reset').addEventListener('click', () => {
        if (!confirm('Reset all settings to defaults? This will remove every configured link and category.')) return;
        const actionInput = form.querySelector('input[name="lfp_action"]');
        actionInput.value = 'reset';
        form.submit();
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

    /* -------------------------------------------------- Instagram grid */

    const igGrid = document.getElementById('lfp-ig-grid');
    const igJsonInput = document.getElementById('lfp-ig-json');
    const tplIgTile = document.getElementById('lfp-tpl-ig-tile');
    const tplIgAdd  = document.getElementById('lfp-tpl-ig-add');
    const igDialog = document.getElementById('lfp-ig-dialog');

    let igItems = Array.isArray(bootstrap.instagram) ? structuredClone(bootstrap.instagram) : [];
    let igEditId = null; // null when adding, item.id when editing
    let igDragId = null;

    const renderIg = () => {
        if (!igGrid) return;
        igGrid.replaceChildren();
        for (const it of igItems) {
            igGrid.appendChild(renderIgTile(it));
        }
        // Add a single placeholder for the next empty cell
        igGrid.appendChild(renderIgAdd());
    };

    function renderIgTile(item) {
        const node = tplIgTile.content.firstElementChild.cloneNode(true);
        node.dataset.id = item.id;

        const imgSlot   = node.querySelector('[data-lfp-ig-img]');
        const overlay   = node.querySelector('[data-lfp-ig-overlay]');
        const titleEl   = node.querySelector('[data-lfp-ig-title]');
        const editBtn   = node.querySelector('[data-lfp-ig-edit]');
        const removeBtn = node.querySelector('[data-lfp-ig-remove]');

        if (item.image) imgSlot.style.backgroundImage = cssUrl(item.image);
        titleEl.textContent = item.title || '';
        overlay.dataset.show = item.show_mode || 'always';
        if (!item.title || (item.show_mode === 'never')) {
            overlay.classList.add('is-empty');
        }

        editBtn.addEventListener('click', () => openIgDialog(item.id));
        removeBtn.addEventListener('click', () => {
            if (!confirm('Remove this tile?')) return;
            const idx = igItems.findIndex((i) => i.id === item.id);
            if (idx >= 0) igItems.splice(idx, 1);
            renderIg();
        });

        // Drag-drop
        let onHandle = false;
        node.addEventListener('mousedown', (e) => {
            onHandle = !!e.target.closest('.lfp-handle');
            node.draggable = onHandle;
        });
        node.addEventListener('dragstart', (e) => {
            if (!onHandle) { e.preventDefault(); return; }
            igDragId = item.id;
            node.classList.add('is-dragging');
            e.dataTransfer.effectAllowed = 'move';
        });
        node.addEventListener('dragend', () => {
            node.classList.remove('is-dragging');
            node.draggable = false;
            igDragId = null;
            igGrid.querySelectorAll('.is-drop-target').forEach((el) => el.classList.remove('is-drop-target'));
        });
        node.addEventListener('dragover', (e) => {
            if (!igDragId || igDragId === item.id) return;
            e.preventDefault();
            node.classList.add('is-drop-target');
        });
        node.addEventListener('dragleave', () => {
            node.classList.remove('is-drop-target');
        });
        node.addEventListener('drop', (e) => {
            if (!igDragId || igDragId === item.id) return;
            e.preventDefault();
            const srcIdx = igItems.findIndex((i) => i.id === igDragId);
            const tgtIdx = igItems.findIndex((i) => i.id === item.id);
            if (srcIdx < 0 || tgtIdx < 0) return;
            const [moved] = igItems.splice(srcIdx, 1);
            const re = igItems.findIndex((i) => i.id === item.id);
            igItems.splice(re, 0, moved);
            renderIg();
        });

        return node;
    }

    function renderIgAdd() {
        const btn = tplIgAdd.content.firstElementChild.cloneNode(true);
        btn.addEventListener('click', () => openIgDialog(null));
        return btn;
    }

    /* -------- Instagram dialog */

    const igFields = {
        imageUrl:    document.getElementById('lfp-ig-image-url'),
        imageFile:   document.getElementById('lfp-ig-image-file'),
        source:      document.getElementById('lfp-ig-source'),
        url:         document.getElementById('lfp-ig-url'),
        keywordDisp: document.getElementById('lfp-ig-keyword-display'),
        title:       document.getElementById('lfp-ig-title-input'),
        showMode:    document.getElementById('lfp-ig-show-mode'),
    };
    let igDialogState = {};

    function openIgDialog(id) {
        igEditId = id;
        igDialogState = id
            ? structuredClone(igItems.find((i) => i.id === id) || {})
            : { id: uid('ig'), source: 'url', url: '', keyword: '', image: '', title: '', show_mode: 'always' };

        igFields.imageUrl.value = igDialogState.image || '';
        igFields.imageFile.value = '';
        igFields.source.value = igDialogState.source || 'url';
        igFields.url.value = igDialogState.url || '';
        igFields.keywordDisp.textContent = igDialogState.keyword || '—';
        igFields.title.value = igDialogState.title || '';
        igFields.showMode.value = igDialogState.show_mode || 'always';
        updateIgSourceBlocks();
        document.getElementById('lfp-ig-dialog-title').textContent = id ? 'Edit Instagram tile' : 'Add Instagram tile';

        if (typeof igDialog.showModal === 'function') igDialog.showModal();
        else igDialog.setAttribute('open', '');
    }

    function updateIgSourceBlocks() {
        const v = igFields.source.value;
        document.querySelectorAll('[data-lfp-ig-block]').forEach((el) => {
            el.hidden = el.dataset.lfpIgBlock !== v;
        });
    }

    if (igFields.source) {
        igFields.source.addEventListener('change', updateIgSourceBlocks);
    }

    igFields.imageUrl?.addEventListener('input', (e) => {
        igDialogState.image = e.target.value;
    });

    igFields.imageFile?.addEventListener('change', (e) => {
        const f = e.target.files && e.target.files[0];
        if (!f) return;
        // Read as data URL so the live grid preview works before form submit.
        const reader = new FileReader();
        reader.addEventListener('load', (ev) => {
            igDialogState.image = String(ev.target.result);
            igFields.imageUrl.value = igDialogState.image;
        });
        reader.readAsDataURL(f);
    });

    igFields.url?.addEventListener('input', (e) => {
        igDialogState.url = e.target.value;
    });

    igFields.title?.addEventListener('input', (e) => {
        igDialogState.title = e.target.value;
    });

    igFields.showMode?.addEventListener('change', (e) => {
        igDialogState.show_mode = e.target.value;
    });

    document.getElementById('lfp-ig-pick')?.addEventListener('click', () => {
        openPicker((kw) => {
            igDialogState.keyword = kw;
            igFields.keywordDisp.textContent = kw;
        });
    });

    document.getElementById('lfp-ig-cancel')?.addEventListener('click', () => {
        igDialog.close();
    });

    document.getElementById('lfp-ig-save')?.addEventListener('click', () => {
        const s = igDialogState;
        s.source = igFields.source.value === 'keyword' ? 'keyword' : 'url';
        if (!s.image) { alert('Please add an image for the tile.'); return; }
        if (s.source === 'keyword' && !s.keyword) { alert('Pick a YOURLS shortlink.'); return; }
        if (s.source === 'url' && !s.url) { alert('Enter a URL.'); return; }

        if (igEditId) {
            const idx = igItems.findIndex((i) => i.id === igEditId);
            if (idx >= 0) igItems[idx] = s;
        } else {
            igItems.push(s);
        }
        igDialog.close();
        renderIg();
    });

    /* -------------------------------------------------- Image preview for header/bg */

    document.querySelectorAll('.lfp-pane:not([data-pane="links"]) [data-lfp-image-file]').forEach((fileInput) => {
        fileInput.addEventListener('change', (e) => {
            const f = e.target.files && e.target.files[0];
            if (!f) return;
            const wrap = fileInput.closest('.lfp-image-input');
            if (!wrap) return;
            let preview = wrap.querySelector('.lfp-thumb');
            if (!preview) {
                preview = document.createElement('img');
                preview.className = 'lfp-thumb';
                wrap.appendChild(preview);
            }
            const reader = new FileReader();
            reader.addEventListener('load', (ev) => {
                preview.src = String(ev.target.result);
            });
            reader.readAsDataURL(f);
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
        if (igJsonInput) {
            const cleanIg = igItems
                .filter((i) => i.image && (
                    (i.source === 'keyword' && i.keyword) ||
                    (i.source !== 'keyword' && i.url)
                ))
                .map((i) => ({
                    id: i.id,
                    source: i.source === 'keyword' ? 'keyword' : 'url',
                    url: i.url || '',
                    keyword: i.keyword || '',
                    image: i.image || '',
                    title: i.title || '',
                    show_mode: ['always', 'hover', 'never'].includes(i.show_mode) ? i.show_mode : 'always',
                }));
            igJsonInput.value = JSON.stringify(cleanIg);
        }
    });

    /* -------------------------------------------------- Init */
    renderTree();
    renderSocials();
    renderIg();
})();
