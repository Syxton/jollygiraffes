(function () {
    "use strict";

    var app = document.getElementById('status_app');
    var code = app.getAttribute('data-code') || '';

    var state = {
        role: null,
        children: [],
        moods: {},
        tallies: {},
        tags: [],
        chid: null,
        todayDaykey: null,
        daykey: null,
        pin: ''
    };

    // ------------------------------------------------------------------
    // Networking
    // ------------------------------------------------------------------
    function post(action, params) {
        var body = new URLSearchParams(params || {});
        body.set('action', action);
        return fetch('ajax/status_ajax.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); });
    }

    // ------------------------------------------------------------------
    // Screens
    // ------------------------------------------------------------------
    function showScreen(id) {
        ['screen_login', 'screen_parent', 'screen_admin', 'screen_links'].forEach(function (s) {
            document.getElementById(s).style.display = (s === id) ? '' : 'none';
        });
    }

    // ------------------------------------------------------------------
    // Login
    // ------------------------------------------------------------------
    function initLogin() {
        var title = document.getElementById('login_title');
        var subtitle = document.getElementById('login_subtitle');
        if (code) {
            subtitle.textContent = 'Enter your family PIN to see today\u2019s status.';
        } else {
            title.textContent = app.getAttribute('data-sitename') + ' \u2013 Staff';
            subtitle.textContent = 'Enter the staff PIN to update statuses.';
        }

        var numpad = document.getElementById('numpad');
        numpad.innerHTML = '';
        var keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'C', '0', '\u232B'];
        keys.forEach(function (k) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = k;
            if (k === 'C') { btn.className = 'numpad-clear'; }
            if (k === '\u232B') { btn.className = 'numpad-back'; }
            btn.addEventListener('click', function () { handleKey(k); });
            numpad.appendChild(btn);
        });

        state.pin = '';
        renderPinDots();
        document.getElementById('login_error').textContent = '';
        showScreen('screen_login');
    }

    function renderPinDots() {
        var wrap = document.getElementById('pin_display');
        wrap.innerHTML = '';
        for (var i = 0; i < 4; i++) {
            var dot = document.createElement('div');
            dot.className = 'dot' + (i < state.pin.length ? ' filled' : '');
            wrap.appendChild(dot);
        }
    }

    function handleKey(k) {
        if (k === 'C') {
            state.pin = '';
            renderPinDots();
            return;
        }
        if (k === '\u232B') {
            state.pin = state.pin.slice(0, -1);
            renderPinDots();
            return;
        }
        if (state.pin.length >= 4) { return; }
        state.pin += k;
        renderPinDots();
        if (state.pin.length === 4) {
            submitPin();
        }
    }

    function submitPin() {
        var pin = state.pin;
        var req = code ? post('login_parent', { code: code, pin: pin }) : post('login_admin', { pin: pin });
        req.then(function (res) {
            if (!res.success) {
                document.getElementById('login_error').textContent = res.message || 'Incorrect PIN.';
                state.pin = '';
                renderPinDots();
                return;
            }
            state.role = code ? 'parent' : 'admin';
            state.children = res.children || [];
            if (state.role === 'admin') {
                state.moods = res.moods || {};
                state.tallies = res.tallies || {};
                state.tags = res.tags || [];
                startAdmin();
            } else {
                startParent();
            }
        }).catch(function () {
            document.getElementById('login_error').textContent = 'Something went wrong. Please try again.';
            state.pin = '';
            renderPinDots();
        });
    }

    function checkSession() {
        post('session_check', {}).then(function (res) {
            if (res.success && res.loggedIn) {
                state.role = res.role;
                state.children = res.children || [];
                if (res.role === 'admin') {
                    state.moods = res.moods || {};
                    state.tallies = res.tallies || {};
                    state.tags = res.tags || [];
                    startAdmin();
                } else {
                    state.moods = res.moods || {};
                    state.tallies = res.tallies || {};
                    startParent();
                }
            } else {
                initLogin();
            }
        }).catch(initLogin);
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------
    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    function logout(reload) {
        post('logout', {}).then(function () {
            state.role = null;
            state.pin = '';
            initLogin();
        });
    }

    // ==================================================================
    // PARENT VIEW
    // ==================================================================
    function startParent() {
        if (!state.children.length) {
            showScreen('screen_parent');
            document.getElementById('parent_child_tabs').innerHTML = '<p class="muted" style="padding:12px 16px;">No children found on this account.</p>';
            return;
        }
        state.chid = state.children[0].chid;
        renderChildTabs();
        showScreen('screen_parent');
        fetchDayParent(); // establishes state.todayDaykey / state.daykey
        bindParentNav();
    }

    function renderChildTabs() {
        var wrap = document.getElementById('parent_child_tabs');
        wrap.innerHTML = '';
        if (state.children.length < 2) { return; }
        state.children.forEach(function (c) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'child-tab' + (c.chid === state.chid ? ' active' : '');
            btn.textContent = c.name;
            btn.addEventListener('click', function () {
                state.chid = c.chid;
                renderChildTabs();
                fetchDayParent(state.daykey);
            });
            wrap.appendChild(btn);
        });
    }

    var navBound = false;
    function bindParentNav() {
        if (navBound) { return; }
        navBound = true;
        document.getElementById('day_prev').addEventListener('click', function () { shiftDay(-1); });
        document.getElementById('day_next').addEventListener('click', function () { shiftDay(1); });
        document.getElementById('parent_logout').addEventListener('click', logout);

        var swipeArea = document.getElementById('swipe_area');
        var touchStartX = null;
        swipeArea.addEventListener('touchstart', function (e) {
            touchStartX = e.changedTouches[0].clientX;
        }, { passive: true });
        swipeArea.addEventListener('touchend', function (e) {
            if (touchStartX === null) { return; }
            var dx = e.changedTouches[0].clientX - touchStartX;
            if (Math.abs(dx) > 50) {
                shiftDay(dx > 0 ? -1 : 1); // swipe right -> previous day
            }
            touchStartX = null;
        }, { passive: true });
    }

    var MIN_DAYS_BACK = 90;
    function shiftDay(direction) {
        if (state.daykey === null || state.todayDaykey === null) { return; }
        var next = state.daykey + direction * 86400;
        if (next > state.todayDaykey) { return; }
        if (next < state.todayDaykey - MIN_DAYS_BACK * 86400) { return; }
        fetchDayParent(next);
    }

    function fetchDayParent(daykey) {
        var params = { chid: state.chid };
        if (daykey) { params.daykey = daykey; }
        post('get_day', params).then(function (res) {
            if (!res.success) { return; }
            var day = res.day;
            state.daykey = day.daykey;
            if (state.todayDaykey === null) {
                // First call has no daykey param, so the server returned
                // "today" - use it as the fixed anchor for swipe limits.
                state.todayDaykey = day.daykey;
            }
            renderParentDay(day);
        });
    }

    function renderParentDay(day) {
        document.getElementById('day_label').textContent = day.is_today ? 'Today \u2013 ' + day.date_label : day.date_label;
        document.getElementById('day_next').disabled = (state.daykey >= state.todayDaykey);
        document.getElementById('day_prev').disabled = (state.daykey <= state.todayDaykey - MIN_DAYS_BACK * 86400);

        // Name
        var name = document.querySelector('#screen_parent #name');
        name.innerHTML = day.name;

        // Avatar
        var avatar = document.querySelector('#screen_parent #avatar');
        avatar.innerHTML = day.avatar;

        // Mood
        var moodWrap = document.getElementById('mood_timeline');
        moodWrap.innerHTML = '';
        document.getElementById('mood_empty').style.display = day.moods.length ? 'none' : '';
        day.moods.forEach(function (m) {
            var chip = document.createElement('div');
            chip.className = 'mood-chip';
            chip.style.background = m.color;
            chip.innerHTML = '<span class="emoji">' + m.emoji + '</span><span>' + escapeHtml(m.label) + ' \u00b7 ' + escapeHtml(m.time) + '</span>';
            moodWrap.appendChild(chip);
        });

        // Tallies
        var tallyWrap = document.getElementById('tally_grid');
        tallyWrap.innerHTML = '';

        Object.keys(day.counts).forEach(function (key) {
            var info = state.tallies[key] || { label: key, emoji: '', color: '#999' };
            var item = document.createElement('div');
            item.className = 'tally-item';
            item.innerHTML = '<div class="emoji">' + (info.emoji || '') + '</div>' +
                '<div class="count">' + day.counts[key] + '</div>' +
                '<div class="label">' + escapeHtml(info.label || key) + '</div>';
            tallyWrap.appendChild(item);
        });

        // Menu
        document.getElementById('menu_text').textContent = day.menu || '';
        document.getElementById('menu_empty').style.display = day.menu ? 'none' : '';

        // Notes
        var notesWrap = document.getElementById('notes_list');
        notesWrap.innerHTML = '';
        document.getElementById('notes_empty').style.display = day.notes.length ? 'none' : '';
        day.notes.forEach(function (n) {
            var item = document.createElement('div');
            item.className = 'note-item';
            item.style.background = n.color;
            item.style.color = n.textcolor;
            item.innerHTML = '<div class="note-meta"><span>' + escapeHtml(n.tag_title) + '</span><span>' + escapeHtml(n.time) + '</span></div>' +
                '<div class="note-text">' + escapeHtml(n.note) + '</div>';
            notesWrap.appendChild(item);
        });
    }

    // ==================================================================
    // ADMIN VIEW
    // ==================================================================
    var adminBound = false;

    function startAdmin() {
        showScreen('screen_admin');
        renderAdminChildSelect();
        if (state.children.length) {
            state.chid = state.children[0].chid;
        }
        renderMoodButtons();
        renderAdminTallyButtons(null);
        bindAdminEvents();
        fetchDayAdmin();
    }

    function renderAdminChildSelect() {
        var select = document.getElementById('admin_child_select');
        select.innerHTML = '';
        if (!state.children.length) {
            var opt = document.createElement('option');
            opt.textContent = 'No children found';
            select.appendChild(opt);
            return;
        }
        var groups = {};
        state.children.forEach(function (c) {
            var fam = c.family_name || 'Family';
            if (!groups[fam]) { groups[fam] = document.createElement('optgroup'); groups[fam].label = fam; select.appendChild(groups[fam]); }
            var opt = document.createElement('option');
            opt.value = c.chid;
            opt.textContent = c.name;
            groups[fam].appendChild(opt);
        });
    }

    function renderMoodButtons() {
        var wrap = document.getElementById('mood_buttons');
        wrap.innerHTML = '';
        Object.keys(state.moods).forEach(function (key) {
            var info = state.moods[key];
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.style.background = info.color;
            btn.innerHTML = '<span class="emoji">' + info.emoji + '</span><span>' + escapeHtml(info.label) + '</span>';
            btn.addEventListener('click', function () {
                post('add_mood', { chid: state.chid, mood: key }).then(function (res) {
                    if (res.success) { renderAdminDay(res.day); }
                });
            });
            wrap.appendChild(btn);
        });
    }

    function renderAdminTallyButtons(counts) {
        var wrap = document.getElementById('admin_tally_grid');
        wrap.innerHTML = '';
        Object.keys(state.tallies).forEach(function (key) {
            var info = state.tallies[key];
            var item = document.createElement('div');
            item.className = 'tally-item';
            var count = counts ? counts[key] : 0;
            item.innerHTML = '<div class="emoji">' + info.emoji + '</div>' +
                '<div class="count" data-tag="' + key + '">' + count + '</div>' +
                '<div class="label">' + escapeHtml(info.label) + '</div>' +
                '<div class="tally-buttons">' +
                '<button type="button" class="undo-btn" data-tag="' + key + '" data-act="undo">\u2212</button>' +
                '<button type="button" class="tap-btn" data-tag="' + key + '" data-act="tap">+</button>' +
                '</div>';
            wrap.appendChild(item);
        });
        wrap.querySelectorAll('button').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tag = btn.getAttribute('data-tag');
                var act = btn.getAttribute('data-act') === 'tap' ? 'add_tally' : 'undo_tally';
                post(act, { chid: state.chid, tag: tag }).then(function (res) {
                    if (res.success) { renderAdminDay(res.day); }
                });
            });
        });
    }

    function renderNoteTagSelect() {
        var select = document.getElementById('note_tag_select');
        select.innerHTML = '';
        state.tags.forEach(function (t) {
            var opt = document.createElement('option');
            opt.value = t.tag;
            opt.textContent = t.title;
            select.appendChild(opt);
        });
    }

    function bindAdminEvents() {
        if (adminBound) { return; }
        adminBound = true;

        document.getElementById('admin_child_select').addEventListener('change', function (e) {
            state.chid = parseInt(e.target.value, 10);
            fetchDayAdmin();
        });
        document.getElementById('admin_logout').addEventListener('click', logout);
        document.getElementById('admin_links_btn').addEventListener('click', openLinksPanel);
        document.getElementById('links_back_btn').addEventListener('click', function () {
            showScreen('screen_admin');
        });

        document.getElementById('save_menu_btn').addEventListener('click', function () {
            var menu = document.getElementById('admin_menu_input').value;
            post('save_menu', { chid: state.chid, menu: menu }).then(function (res) {
                if (res.success) {
                    var status = document.getElementById('menu_save_status');
                    status.textContent = 'Saved';
                    setTimeout(function () { status.textContent = ''; }, 2000);
                }
            });
        });

        renderNoteTagSelect();
        document.getElementById('add_note_btn').addEventListener('click', function () {
            var tag = document.getElementById('note_tag_select').value;
            var text = document.getElementById('note_text_input').value.trim();
            var notify = document.getElementById('note_notify_checkbox').checked;
            if (!text) { return; }
            post('add_note', { chid: state.chid, tag: tag, note: text, notify: notify ? '1' : '' }).then(function (res) {
                if (res.success) {
                    document.getElementById('note_text_input').value = '';
                    document.getElementById('note_notify_checkbox').checked = false;
                    renderAdminDay(res.day);
                }
            });
        });

        document.getElementById('copy_menu_btn').addEventListener('click', function () {
            var panel = document.getElementById('menu_copy_panel');
            var opening = panel.style.display === 'none';
            panel.style.display = opening ? '' : 'none';
            if (opening) { renderMenuCopyList(); }
        });
        document.getElementById('menu_copy_cancel').addEventListener('click', function () {
            document.getElementById('menu_copy_panel').style.display = 'none';
        });
        document.getElementById('menu_copy_confirm').addEventListener('click', function () {
            var menu = document.getElementById('admin_menu_input').value;
            var checked = document.querySelectorAll('#menu_copy_list input[type="checkbox"]:checked');
            var chids = Array.prototype.map.call(checked, function (cb) { return cb.value; });
            var status = document.getElementById('menu_copy_status');
            if (!chids.length) {
                status.textContent = 'Select at least one child.';
                return;
            }
            chids.push(state.chid); // keeps the current child's saved menu in sync too
            post('copy_menu_to_children', { menu: menu, chids: chids.join(',') }).then(function (res) {
                if (res.success) {
                    var count = res.written.length;
                    status.textContent = 'Copied to ' + count + ' kid' + (count === 1 ? '' : 's') + '.';
                    document.getElementById('menu_copy_panel').style.display = 'none';
                    fetchMenuSuggestions();
                } else {
                    status.textContent = res.message || 'Could not copy.';
                }
                setTimeout(function () { status.textContent = ''; }, 2500);
            });
        });
    }

    function renderMenuCopyList() {
        var wrap = document.getElementById('menu_copy_list');
        wrap.innerHTML = '';
        var others = state.children.filter(function (c) { return c.chid !== state.chid; });
        if (!others.length) {
            wrap.innerHTML = '<p class="muted">No other kids to copy to.</p>';
            return;
        }
        var groups = {};
        others.forEach(function (c) {
            var fam = c.family_name || 'Family';
            if (!groups[fam]) {
                var group = document.createElement('div');
                group.className = 'menu-copy-group';
                var title = document.createElement('div');
                title.className = 'menu-copy-group-title';
                title.textContent = fam;
                group.appendChild(title);
                groups[fam] = group;
                wrap.appendChild(group);
            }
            var label = document.createElement('label');
            label.className = 'menu-copy-item';
            label.innerHTML = '<input type="checkbox" value="' + c.chid + '"> ' + escapeHtml(c.name);
            groups[fam].appendChild(label);
        });
    }

    function fetchMenuSuggestions() {
        if (!state.chid) { return; }
        post('get_menu_suggestions', { chid: state.chid }).then(function (res) {
            if (res.success) { renderMenuSuggestions(res.suggestions); }
        });
    }

    function renderMenuSuggestions(suggestions) {
        var wrap = document.getElementById('menu_suggestions');
        wrap.innerHTML = '';
        if (!suggestions || !suggestions.length) { return; }

        var label = document.createElement('div');
        label.className = 'menu-suggestions-label';
        label.textContent = 'Quick fill from today:';
        wrap.appendChild(label);

        var chipRow = document.createElement('div');
        chipRow.className = 'menu-suggestions-row';
        suggestions.forEach(function (s) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'menu-suggestion-chip';
            if (s.kids) { chip.title = 'Same as: ' + s.kids; }
            var preview = s.menu.length > 44 ? s.menu.slice(0, 44) + '\u2026' : s.menu;
            chip.textContent = preview + (s.count > 1 ? ' (' + s.count + ')' : '');
            chip.addEventListener('click', function () {
                document.getElementById('admin_menu_input').value = s.menu;
            });
            chipRow.appendChild(chip);
        });
        wrap.appendChild(chipRow);
    }

    function fetchDayAdmin() {
        if (!state.chid) { return; }
        post('get_day', { chid: state.chid }).then(function (res) {
            if (res.success) { renderAdminDay(res.day); }
        });
    }

    function renderAdminDay(day) {
        document.getElementById('admin_day_label').textContent = day.date_label + ' (today)';

        // Avatar
        var avatar = document.querySelector('#screen_admin #avatar');
        avatar.innerHTML = day.avatar;

        // Mood timeline (recent taps today)
        var moodWrap = document.getElementById('admin_mood_timeline');
        moodWrap.innerHTML = '';
        day.moods.forEach(function (m) {
            var chip = document.createElement('div');
            chip.className = 'mood-chip';
            chip.style.background = m.color;
            chip.innerHTML = '<span class="emoji">' + m.emoji + '</span><span>' + escapeHtml(m.label) + ' \u00b7 ' + escapeHtml(m.time) + '</span>';
            moodWrap.appendChild(chip);
        });

        renderAdminTallyButtons(day.counts);

        document.getElementById('admin_menu_input').value = day.menu || '';
        document.getElementById('menu_copy_panel').style.display = 'none';
        fetchMenuSuggestions();

        var notesWrap = document.getElementById('admin_notes_list');
        notesWrap.innerHTML = '';
        day.notes.forEach(function (n) {
            var item = document.createElement('div');
            item.className = 'note-item';
            item.style.background = n.color;
            item.style.color = n.textcolor;
            item.innerHTML = '<div class="note-meta"><span>' + escapeHtml(n.tag_title) + (n.notify ? ' \ud83d\udd14' : '') + '</span><span>' + escapeHtml(n.time) + '</span></div>' +
                '<div class="note-text">' + escapeHtml(n.note) + '</div>' +
                '<button type="button" class="note-delete" data-nid="' + n.nid + '">Delete</button>';
            notesWrap.appendChild(item);
        });
        notesWrap.querySelectorAll('.note-delete').forEach(function (btn) {
            btn.addEventListener('click', function () {
                post('delete_note', { chid: state.chid, nid: btn.getAttribute('data-nid') }).then(function (res) {
                    if (res.success) { renderAdminDay(res.day); }
                });
            });
        });
    }

    // ------------------------------------------------------------------
    // Family links panel
    // ------------------------------------------------------------------
    function openLinksPanel() {
        showScreen('screen_links');
        post('get_links', {}).then(function (res) {
            if (!res.success) { return; }
            renderLinksList(res.families);
        });
    }

    function renderLinksList(families) {
        var wrap = document.getElementById('links_list');
        wrap.innerHTML = '';
        var base = location.origin + location.pathname;
        families.forEach(function (f) {
            var item = document.createElement('div');
            item.className = 'link-item';
            item.innerHTML = '<div class="family-name">' + escapeHtml(f.name) + '</div>' +
                '<div class="link-row">' +
                '<input type="text" value="' + escapeHtml(f.link_code) + '" data-aid="' + f.aid + '">' +
                '<button type="button" class="save-link" data-aid="' + f.aid + '">Save</button>' +
                '<button type="button" class="copy-link" data-aid="' + f.aid + '">Copy Link</button>' +
                '</div>' +
                '<div class="link-url">' + base + '?c=' + escapeHtml(f.link_code) + '</div>';
            wrap.appendChild(item);
        });
        wrap.querySelectorAll('.save-link').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var aid = btn.getAttribute('data-aid');
                var input = wrap.querySelector('input[data-aid="' + aid + '"]');
                post('set_link_code', { aid: aid, code: input.value }).then(function (res) {
                    if (res.success) {
                        post('get_links', {}).then(function (r) { renderLinksList(r.families); });
                    } else {
                        alert(res.message || 'Could not save that link.');
                    }
                });
            });
        });
        wrap.querySelectorAll('.copy-link').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var aid = btn.getAttribute('data-aid');
                var input = wrap.querySelector('input[data-aid="' + aid + '"]');
                var url = base + '?c=' + input.value;

                copyText(url);
                btn.textContent = 'Copied!';
                setTimeout(function () { btn.textContent = 'Copy Link'; }, 1500);
            });
        });
    }

    function fallbackCopyTextToClipboard(text) {
        const textarea = document.createElement("textarea");
        textarea.value = text;

        // Move textarea out of the viewport to prevent scrolling
        textarea.style.position = "fixed";
        textarea.style.top = "0";
        textarea.style.left = "0";
        textarea.style.opacity = "0";

        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        try {
            const successful = document.execCommand('copy');
            if (!successful) {
            console.error('Fallback: Copying text command was unsuccessful');
            }
        } catch (err) {
            console.error('Fallback: Oops, unable to copy', err);
        }

        document.body.removeChild(textarea);
        }

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).catch(err => {
            console.warn("Clipboard API failed, using fallback", err);
            fallbackCopyTextToClipboard(text);
            });
        } else {
            fallbackCopyTextToClipboard(text);
        }
    }

    // ------------------------------------------------------------------
    checkSession();
})();