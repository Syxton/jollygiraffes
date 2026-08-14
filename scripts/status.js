(function () {
    "use strict";

    var app = document.getElementById('status_app');
    var code = app.getAttribute('data-code') || '';

    var state = {
        role: null,
        children: [],
        moods: {},
        pottyTypes: {},
        quickNotes: {},
        incidentTypes: {},
        napDurations: [30, 60, 90, 120],
        bottleOunces: [1, 2, 3, 4, 5, 6, 7, 8],
        meals: {},
        mealRatings: {},
        activities: {},
        napRatings: {},
        bottle: { label: 'Bottle', emoji: '\ud83c\udf7c', color: '#4DABF7' },
        tags: [],
        chid: null,
        todayDaykey: null,
        daykey: null,
        pin: '',
        lastAdminDay: null,
        cardCollapsed: {},
        previewMode: false,
        adminPreviewChid: null
    };

    var MEAL_ORDER = ['breakfast', 'lunch', 'dinner'];
    function orderedMealKeys() {
        var keys = Object.keys(state.meals);
        keys.sort(function (a, b) {
            var ia = MEAL_ORDER.indexOf(a), ib = MEAL_ORDER.indexOf(b);
            if (ia === -1) { ia = 99; }
            if (ib === -1) { ib = 99; }
            return ia - ib;
        });
        return keys;
    }

    var ACTIVITY_ORDER = ['books', 'outdoor_playground', 'indoor_playground', 'art', 'pretend_play', 'sensory', 'belly_time', 'videos'];
    function orderedActivityKeys() {
        var keys = Object.keys(state.activities);
        keys.sort(function (a, b) {
            var ia = ACTIVITY_ORDER.indexOf(a), ib = ACTIVITY_ORDER.indexOf(b);
            if (ia === -1) { ia = 99; }
            if (ib === -1) { ib = 99; }
            return ia - ib;
        });
        return keys;
    }

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
            state.moods = res.moods || {};
            state.pottyTypes = res.pottyTypes || {};
            state.meals = res.meals || {};
            state.mealRatings = res.mealRatings || {};
            state.activities = res.activities || {};
            state.napRatings = res.napRatings || {};
            state.bottle = res.bottle || state.bottle;
            if (state.role === 'admin') {
                state.quickNotes = res.quickNotes || {};
                state.incidentTypes = res.incidentTypes || {};
                state.napDurations = res.napDurations || state.napDurations;
                state.bottleOunces = res.bottleOunces || state.bottleOunces;
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
                state.moods = res.moods || {};
                state.pottyTypes = res.pottyTypes || {};
                state.meals = res.meals || {};
                state.mealRatings = res.mealRatings || {};
                state.activities = res.activities || {};
                state.napRatings = res.napRatings || {};
                state.bottle = res.bottle || state.bottle;
                if (res.role === 'admin') {
                    state.quickNotes = res.quickNotes || {};
                    state.incidentTypes = res.incidentTypes || {};
                    state.napDurations = res.napDurations || state.napDurations;
                    state.bottleOunces = res.bottleOunces || state.bottleOunces;
                    state.tags = res.tags || [];
                    startAdmin();
                } else {
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

    // Shared chip layout used by mood/potty/bottle/nap/incident entries:
    // time (left, larger) -> emoji -> status label -> spacer -> optional
    // attachment badge -> optional action buttons (admin only), always in
    // that order so every entry in a timeline reads the same way.
    //
    // opts:
    //   background   - chip background color
    //   extraClass   - extra class(es) on the outer chip element
    //   time         - time string, shown first in a bold pill
    //   emoji        - emoji string
    //   label        - plain-text label (already unescaped; this function
    //                  escapes it)
    //   extraText    - optional plain text appended after label (e.g. potty
    //                  emoji extras), also escaped
    //   attachments  - optional array, renders the attachment badge
    //   note         - optional plain-text note shown on its own line below
    //   buttons      - optional array of {icon, title, onClick}, admin-only
    //                  action buttons rendered after the attachment badge
    function buildStatusChip(opts) {
        var chip = document.createElement('div');
        chip.className = 'mood-chip' + (opts.extraClass ? ' ' + opts.extraClass : '');
        if (opts.background) { chip.style.background = opts.background; }
        if (opts.note) { chip.classList.add('mood-chip-with-note'); }

        var row = document.createElement('span');
        row.className = 'mood-chip-row';

        var timeandtype = document.createElement('span');
        timeandtype.className = 'timeandtype';

        var time = document.createElement('span');
        time.className = 'chip-time';
        time.textContent = opts.time || '';
        timeandtype.appendChild(time);

        var emoji = document.createElement('span');
        emoji.className = 'emoji';
        emoji.textContent = opts.emoji || '';
        timeandtype.appendChild(emoji);

        row.appendChild(timeandtype);

        var label = document.createElement('span');
        label.className = 'chip-label';
        label.textContent = (opts.label || '') + (opts.extraText || '');
        row.appendChild(label);

        var buttonarea = document.createElement('span');
        buttonarea.className = 'chip-button-area';

        if (opts.attachments && opts.attachments.length) {
            buttonarea.appendChild(buildAttachmentBadge(opts.attachments));
        }

        if (opts.buttons && opts.buttons.length) {
            opts.buttons.forEach(function (b) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'chip-icon-btn';
                btn.innerHTML = '<i class="fa-solid fa-' + b.icon + '"></i>';
                btn.title = b.title;
                btn.addEventListener('click', b.onClick);
                buttonarea.appendChild(btn);
            });
        }
        row.appendChild(buttonarea);
        chip.appendChild(row);

        if (opts.note) {
            var noteEl = document.createElement('span');
            noteEl.className = 'mood-chip-note';
            noteEl.textContent = opts.note;
            chip.appendChild(noteEl);
        }

        return chip;
    }

    // Formats "HH:MM" as a friendly "h:mm am/pm" label. Shared by the
    // inline chip time editor and the Potty Time panel's time field.
    function updateTimeLabel(labelEl, hm) {
        var parts = (hm || '00:00').split(':');
        var h = parseInt(parts[0], 10);
        var m = parseInt(parts[1], 10) || 0;
        var suffix = h >= 12 ? 'pm' : 'am';
        var h12 = h % 12;
        if (h12 === 0) { h12 = 12; }
        labelEl.textContent = h12 + ':' + (m < 10 ? '0' : '') + m + ' ' + suffix;
    }

    // Swaps a chip's contents for an inline <input type="time"> plus a
    // persistent, live-updating text label (native time inputs can look
    // blank mid-interaction on some mobile browsers, so the label is the
    // reliable "what will be saved" indicator) plus confirm/cancel.
    function openTimeEditor(chip, currentHm, onConfirm) {
        var input = document.createElement('input');
        input.type = 'time';
        input.className = 'time-edit-input';

        //var label = document.createElement('span');
        //label.className = 'time-edit-label';

        var okBtn = document.createElement('button');
        okBtn.type = 'button';
        okBtn.className = 'chip-icon-btn';
        okBtn.innerHTML = '<i class="fa-solid fa-check"></i>';
        okBtn.title = 'Save time';

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'chip-icon-btn';
        cancelBtn.innerHTML = '<i class="fa-solid fa-ban"></i>';
        cancelBtn.title = 'Cancel';

        chip.innerHTML = '';
        chip.appendChild(cancelBtn);
        chip.appendChild(input);
        chip.appendChild(okBtn);


        // Assign as a property (not just the HTML attribute) after the
        // input is in the DOM - more reliable across mobile browsers.
        input.value = currentHm || '';
        //updateTimeLabel(label, input.value);
        input.focus();

        //input.addEventListener('input', function () { updateTimeLabel(label, input.value); });

        okBtn.addEventListener('click', function () {
            var parts = (input.value || '00:00').split(':');
            onConfirm(parseInt(parts[0], 10), parseInt(parts[1], 10));
        });
        cancelBtn.addEventListener('click', function () {
            if (state.lastAdminDay) { renderAdminDay(state.lastAdminDay); }
        });
    }

    // Admin cards collapse their "history" (the log of today's entries)
    // behind a "Today's entries (N)" toggle that sits right where that
    // history would otherwise appear, so the quick-log buttons stay put
    // without a growing timeline pushing everything else down. The
    // toggle itself only appears once there's something to show - an
    // empty history has nothing to toggle. Defaults to collapsed;
    // once the person taps it, that choice sticks for the rest of the
    // session (re-renders after every log/edit shouldn't snap it back
    // shut on them).

    // Timers for the auto-recollapse below, keyed by cardId, so a second
    // add within the window restarts the clock instead of stacking timers.
    var cardCollapseTimers = {};

    // Applies state.cardCollapsed[cardId] to a toggle/history pair that's
    // already known to have entries - used both by applyCardCollapse
    // below and by the auto-recollapse timer, which doesn't need to
    // recheck the count since it's the one that just added an entry.
    function updateCardCollapseDisplay(cardId, toggleId, historyId) {
        var toggleBtn = document.getElementById(toggleId);
        var historyEl = document.getElementById(historyId);
        if (!toggleBtn || !historyEl || toggleBtn.style.display === 'none') { return; }
        var collapsed = state.cardCollapsed[cardId];
        var count = toggleBtn.getAttribute('data-count') || '0';
        historyEl.style.display = collapsed ? 'none' : '';
        toggleBtn.textContent = (collapsed ? '\u25b8 ' : '\u25be ') + "Today's entries (" + count + ')';
        toggleBtn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }

    // When something new gets logged, pop that card's history open (even
    // if it was sitting collapsed) so the entry is visible, then quietly
    // collapse it again after a pause. A manual toggle in the meantime
    // cancels the pending auto-collapse rather than fighting the person.
    function flashExpandCard(cardId, toggleId, historyId) {
        if (cardCollapseTimers[cardId]) {
            clearTimeout(cardCollapseTimers[cardId]);
            cardCollapseTimers[cardId] = null;
        }
        state.cardCollapsed[cardId] = false;
        cardCollapseTimers[cardId] = setTimeout(function () {
            cardCollapseTimers[cardId] = null;
            state.cardCollapsed[cardId] = true;
            updateCardCollapseDisplay(cardId, toggleId, historyId);
        }, 10000);
    }

    // count is how many entries are logged for this card today. Hides
    // the toggle entirely (and the empty history under it) when there
    // are none.
    function applyCardCollapse(cardId, toggleId, historyId, count) {
        var toggleBtn = document.getElementById(toggleId);
        var historyEl = document.getElementById(historyId);
        if (!toggleBtn || !historyEl) { return; }

        if (!count) {
            toggleBtn.style.display = 'none';
            historyEl.style.display = 'none';
            return;
        }

        toggleBtn.style.display = '';
        toggleBtn.setAttribute('data-count', count);

        if (state.cardCollapsed[cardId] === undefined) {
            state.cardCollapsed[cardId] = true;
        }

        toggleBtn.onclick = function () {
            if (cardCollapseTimers[cardId]) {
                clearTimeout(cardCollapseTimers[cardId]);
                cardCollapseTimers[cardId] = null;
            }
            state.cardCollapsed[cardId] = !state.cardCollapsed[cardId];
            updateCardCollapseDisplay(cardId, toggleId, historyId);
        };

        updateCardCollapseDisplay(cardId, toggleId, historyId);
    }

    // Small icon button ("set this rating for every kid who doesn't have
    // one yet today") shared by the meal-rating and nap-rating rows.
    // Sits inline with the rating buttons instead of its own row, greys
    // out until a rating is actually selected, and confirms before
    // touching every child's record.
    function buildSetAllButton(ratingsInfo, getCurrent, actionName, extraParams, confirmLabel) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'rating-set-all-btn';
        btn.textContent = '\ud83d\udccb';
        btn.title = 'Copy this rating to every kid without one today';

        function refresh() {
            btn.disabled = !getCurrent();
        }
        refresh();

        btn.addEventListener('click', function () {
            var current = getCurrent();
            if (!current) { return; }
            var label = (ratingsInfo[current] && ratingsInfo[current].label) || current;
            if (!window.confirm('Set "' + label + '" (' + confirmLabel + ') for every other kid who doesn\'t already have a rating today?')) {
                return;
            }
            var params = { chid: state.chid, rating: current };
            for (var k in extraParams) { params[k] = extraParams[k]; }
            btn.disabled = true;
            post(actionName, params).then(function (res) {
                if (res.success) {
                    btn.textContent = '\u2705';
                    setTimeout(function () {
                        btn.textContent = '\ud83d\udccb';
                        refresh();
                    }, 1500);
                } else {
                    refresh();
                }
            });
        });

        return btn;
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
        state.previewMode = false;
        document.getElementById('preview_banner').style.display = 'none';
        document.getElementById('parent_logout').style.display = '';
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
        document.getElementById('day_label').addEventListener('click', openDatePicker);
        document.getElementById('parent_logout').addEventListener('click', function () {
            if (state.previewMode) { exitParentPreview(); } else { logout(); }
        });

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

    // ------------------------------------------------------------------
    // Custom calendar date picker (parent view) - a native <input
    // type="date"> renders inconsistently across mobile browsers, so this
    // is a small in-app month grid instead: big tap targets, out-of-range
    // days disabled, no OS-specific quirks.
    //
    // daykey is a server-side epoch that, per status_daykey()'s own
    // convention, numerically represents local midnight as if it were
    // UTC. Building/reading calendar dates with the UTC getters below
    // (not local ones) keeps every calculation in that same convention,
    // with no dependency on the browser's own timezone.
    // ------------------------------------------------------------------
    function daykeyFromCalendarDate(year, month, day) {
        return Math.floor(Date.UTC(year, month, day) / 1000);
    }
    function calendarDateFromDaykey(daykey) {
        var d = new Date(daykey * 1000);
        return { year: d.getUTCFullYear(), month: d.getUTCMonth(), day: d.getUTCDate(), weekday: d.getUTCDay() };
    }

    var datePickerView = null; // { year, month } currently displayed

    function openDatePicker() {
        if (state.todayDaykey === null) { return; }
        var cur = calendarDateFromDaykey(state.daykey !== null ? state.daykey : state.todayDaykey);
        datePickerView = { year: cur.year, month: cur.month };
        renderDatePicker();
        document.getElementById('date_picker_overlay').style.display = '';
    }

    function closeDatePicker() {
        document.getElementById('date_picker_overlay').style.display = 'none';
    }

    var MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    function renderDatePicker() {
        var panel = document.getElementById('date_picker_panel');
        var year = datePickerView.year, month = datePickerView.month;

        var firstOfMonth = daykeyFromCalendarDate(year, month, 1);
        var firstWeekday = calendarDateFromDaykey(firstOfMonth).weekday; // 0=Sun
        var daysInMonth = new Date(Date.UTC(year, month + 1, 0)).getUTCDate();

        var minDaykey = state.todayDaykey - MIN_DAYS_BACK * 86400;
        var maxDaykey = state.todayDaykey;
        var atMaxMonth = (year === calendarDateFromDaykey(maxDaykey).year && month === calendarDateFromDaykey(maxDaykey).month);

        var html = '<div class="potty-panel-header"><span>' + MONTH_NAMES[month] + ' ' + year + '</span>' +
            '<button type="button" class="link-button" data-role="close">Close</button></div>';

        html += '<div class="date-picker-nav">' +
            '<button type="button" data-role="prev-month">\u2039 Prev</button>' +
            '<button type="button" data-role="today">Today</button>' +
            '<button type="button" data-role="next-month"' + (atMaxMonth ? ' disabled' : '') + '>Next \u203a</button>' +
            '</div>';

        html += '<div class="date-picker-grid date-picker-weekdays">';
        ['S', 'M', 'T', 'W', 'T', 'F', 'S'].forEach(function (w) { html += '<div>' + w + '</div>'; });
        html += '</div>';

        html += '<div class="date-picker-grid">';
        for (var i = 0; i < firstWeekday; i++) { html += '<div></div>'; }
        for (var day = 1; day <= daysInMonth; day++) {
            var dk = daykeyFromCalendarDate(year, month, day);
            var disabled = dk < minDaykey || dk > maxDaykey;
            var isSelected = dk === state.daykey;
            var isToday = dk === state.todayDaykey;
            html += '<button type="button" class="date-picker-day' +
                (isSelected ? ' selected' : '') + (isToday ? ' today' : '') + '"' +
                (disabled ? ' disabled' : '') + ' data-daykey="' + dk + '">' + day + '</button>';
        }
        html += '</div>';

        panel.innerHTML = html;

        panel.querySelector('[data-role="close"]').addEventListener('click', closeDatePicker);
        panel.querySelector('[data-role="prev-month"]').addEventListener('click', function () {
            datePickerView.month -= 1;
            if (datePickerView.month < 0) { datePickerView.month = 11; datePickerView.year -= 1; }
            renderDatePicker();
        });
        var nextBtn = panel.querySelector('[data-role="next-month"]');
        if (!atMaxMonth) {
            nextBtn.addEventListener('click', function () {
                datePickerView.month += 1;
                if (datePickerView.month > 11) { datePickerView.month = 0; datePickerView.year += 1; }
                renderDatePicker();
            });
        }
        panel.querySelector('[data-role="today"]').addEventListener('click', function () {
            closeDatePicker();
            fetchDayParent(state.todayDaykey);
        });
        panel.querySelectorAll('.date-picker-day:not([disabled])').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var dk = parseInt(btn.getAttribute('data-daykey'), 10);
                closeDatePicker();
                fetchDayParent(dk);
            });
        });
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

        // Sticky child bar (stays visible while scrolling so it's always
        // clear whose day you're looking at)
        document.getElementById('parent_sticky_name').textContent = day.name;
        document.getElementById('parent_sticky_avatar').innerHTML = day.avatar;

        // Avatar
        var avatar = document.querySelector('#screen_parent #avatar');
        avatar.innerHTML = day.avatar;

        document.getElementById('parent_naptime_notice_text').style.display = day.show_naptime_notice ? '' : 'none';

        // Notify notes are pulled out and pinned above everything else -
        // they're the notes staff specifically flagged to catch a
        // parent's eye, not just another moment in the day.
        var notifyNotes = day.notes.filter(function (n) { return n.notify; });
        var notifyCard = document.getElementById('parent_notify_card');
        notifyCard.style.display = notifyNotes.length ? '' : 'none';
        var notifyWrap = document.getElementById('parent_notify_notes');
        notifyWrap.innerHTML = '';
        notifyNotes.forEach(function (n) {
            notifyWrap.appendChild(buildParentNoteChip(n));
        });

        // Menu (Breakfast / Lunch / Dinner) - not a timestamped event, so
        // it sits above the timeline as context for the day rather than
        // slotted in among the chips.
        renderParentMealSections(day);

        // Activities - same idea: a set for the day, not a timeline entry.
        renderParentActivitiesSection(day);

        // Naptime rating (kids 2+ get a single per-day rating instead of
        // logged nap times, so it has no "time" to sort into the
        // timeline either).
        var napRatingCard = document.getElementById('parent_nap_rating_card');
        var napRatingInfo = day.show_nap_rating ? state.napRatings[day.nap_rating] : null;
        napRatingCard.style.display = napRatingInfo ? '' : 'none';
        if (napRatingInfo) {
            var napChip = document.getElementById('parent_nap_rating_chip');
            napChip.innerHTML = '<span class="emoji">' + napRatingInfo.emoji + '</span><span>' + escapeHtml(napRatingInfo.label) + '</span>';
        }

        // Everything else - mood, potty, bottles, logged naps, incidents,
        // and regular (non-notify) notes - flows into one chronological
        // timeline, chip after chip, instead of being split into
        // separate areas.
        var entries = [];
        day.moods.forEach(function (m) {
            entries.push({ hm: m.hm, node: buildParentMoodChip(m) });
        });
        day.potty.forEach(function (p) {
            entries.push({ hm: p.hm, node: buildPottyChip(p, false) });
        });
        if (day.show_bottles) {
            day.bottles.forEach(function (b) {
                entries.push({ hm: b.hm, node: buildBottleChip(b, false) });
            });
        }
        day.naps.forEach(function (nap) {
            entries.push({ hm: nap.hm, node: buildNapChip(nap, false) });
        });
        day.incidents.forEach(function (inc) {
            entries.push({ hm: inc.hm, node: buildIncidentChip(inc, false) });
        });
        day.notes.filter(function (n) { return !n.notify; }).forEach(function (n) {
            entries.push({ hm: n.hm, node: buildParentNoteChip(n) });
        });
        entries.sort(function (a, b) {
            return a.hm < b.hm ? -1 : (a.hm > b.hm ? 1 : 0);
        });

        var timelineWrap = document.getElementById('parent_timeline');
        timelineWrap.innerHTML = '';
        entries.forEach(function (entry) {
            let divider = document.createElement('div');
            divider.className = 'timeline-divider';
            timelineWrap.appendChild(divider);

            timelineWrap.appendChild(entry.node);
        });
        document.getElementById('parent_timeline_empty').style.display = entries.length ? 'none' : '';
    }

    function buildParentMoodChip(m) {
        return buildStatusChip({
            background: m.color,
            time: m.time,
            emoji: m.emoji,
            label: m.label
        });
    }

    function buildParentNoteChip(n) {
        var item = document.createElement('div');
        item.className = 'mood-chip mood-chip-with-note';
        item.style.background = n.color;
        item.style.color = n.textcolor;
        item.innerHTML = `
            <div class="mood-chip-row">
                <span class="chip-time timeandtype">
                    ` + escapeHtml(n.time) + `
                </span>
                <span class="chip-label">
                    ` + escapeHtml(n.tag_title) + `
                </span>
                <div class="chip-button-area">
                </div>
            </div>
            <div class="mood-chip-note">
                ` + escapeHtml(n.note) + `
            </div>`;
        return item;
    }

    // Simple per-day nap rating for kids 2+ (no individually logged naps
    // for that group). Same tap-to-set / tap-again-to-clear pattern as
    // the meal rating buttons.
    function renderNapRatingButtons(wrap, currentRating) {
        wrap.innerHTML = '';
        Object.keys(state.napRatings).forEach(function (key) {
            var info = state.napRatings[key];
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'meal-rating-btn' + (key === currentRating ? ' active' : '');
            btn.innerHTML = '<span class="emoji">' + info.emoji + '</span><span>' + escapeHtml(info.label) + '</span>';
            btn.addEventListener('click', function () {
                var newRating = (key === currentRating) ? '' : key;
                post('set_nap_rating', { chid: state.chid, rating: newRating }).then(function (res) {
                    if (res.success) { renderAdminDay(res.day); }
                });
            });
            wrap.appendChild(btn);
        });
        wrap.appendChild(buildSetAllButton(state.napRatings, function () { return currentRating; }, 'set_nap_rating_all', {}, 'naptime'));
    }

    function buildNapChip(nap, editable) {
        var buttons = [];
        if (editable) {
            buttons.push({
                icon: 'clock',
                title: 'Change start time',
                onClick: function () {
                    openTimeEditor(chip, nap.hm, function (hour, minute) {
                        post('edit_nap_time', { chid: state.chid, evid: nap.evid, hour: hour, minute: minute }).then(function (res) {
                            if (res.success) { renderAdminDay(res.day); }
                        });
                    });
                }
            });
            buttons.push({
                icon: 'trash',
                title: 'Delete',
                onClick: function () {
                    post('delete_nap', { chid: state.chid, evid: nap.evid }).then(function (res) {
                        if (res.success) { renderAdminDay(res.day); }
                    });
                }
            });
        }

        var chip = buildStatusChip({
            extraClass: 'nap',
            time: nap.time,
            emoji: '\ud83d\ude34',
            label: nap.minutes + ' min nap started',
            buttons: buttons
        });

        return chip;
    }

    // ------------------------------------------------------------------
    // Bottles - shared chip builder (read-only for parents, deletable
    // for admin)
    // ------------------------------------------------------------------
    function buildBottleChip(b, editable) {
        var amountStr = b.amount ? ' \u00b7 ' + b.amount + 'oz' : '';
        var buttons = [];
        if (editable) {
            buttons.push({
                icon: 'glass-water',
                title: 'Change amount',
                onClick: function () {
                    openBottlePanel(function (ounces) {
                        post('edit_bottle_ounces', { chid: state.chid, evid: b.evid, ounces: ounces }).then(function (res) {
                            if (res.success) { renderAdminDay(res.day); }
                        });
                    });
                }
            });
            buttons.push({
                icon: 'clock',
                title: 'Change time',
                onClick: function () {
                    openTimeEditor(chip, b.hm, function (hour, minute) {
                        post('edit_bottle_time', { chid: state.chid, evid: b.evid, hour: hour, minute: minute }).then(function (res) {
                            if (res.success) { renderAdminDay(res.day); }
                        });
                    });
                }
            });
            buttons.push({
                icon: 'trash',
                title: 'Delete',
                onClick: function () {
                    post('delete_bottle', { chid: state.chid, evid: b.evid }).then(function (res) {
                        if (res.success) { renderAdminDay(res.day); }
                    });
                }
            });
        }

        var chip = buildStatusChip({
            background: state.bottle.color,
            time: b.time,
            emoji: state.bottle.emoji,
            label: state.bottle.label,
            extraText: amountStr,
            buttons: buttons
        });

        return chip;
    }

    // ------------------------------------------------------------------
    // Menu (Breakfast / Lunch / Dinner) - read-only cards for parents
    // ------------------------------------------------------------------
    function renderParentMealSections(day) {
        var container = document.getElementById('parent_meal_sections');
        container.innerHTML = '';
        orderedMealKeys().forEach(function (mealKey) {
            var info = state.meals[mealKey];
            var text = (day.menus && day.menus[mealKey]) || '';
            var ratingKey = (day.ratings && day.ratings[mealKey]) || '';
            var ratingInfo = state.mealRatings[ratingKey];
            // Nothing posted or rated for this meal today - skip the
            // section entirely rather than showing an empty card.
            if (!text && !ratingInfo) {
                return;
            }
            var section = document.createElement('section');
            section.className = 'card';
            section.innerHTML = '<h2>' + info.emoji + ' ' + escapeHtml(info.label) +
                (ratingInfo ? ' <span class="meal-rating-badge">' + ratingInfo.emoji + ' ' + escapeHtml(ratingInfo.label) + '</span>' : '') +
                '</h2>' +
                '<div class="menu-text"></div>';
            section.querySelector('.menu-text').textContent = text;
            container.appendChild(section);
        });
        container.style.display = container.children.length ? '' : 'none';
    }

    // ------------------------------------------------------------------
    // Activities - read-only chips for parents. Card hides entirely when
    // nothing's been checked yet today.
    // ------------------------------------------------------------------
    function renderParentActivitiesSection(day) {
        var card = document.getElementById('parent_activities_card');
        var wrap = document.getElementById('parent_activity_chips');
        wrap.innerHTML = '';
        var activities = day.activities || {};
        var any = false;
        orderedActivityKeys().forEach(function (key) {
            var entry = activities[key];
            if (!entry || !entry.on) { return; }
            any = true;
            var info = state.activities[key];
            var chip = document.createElement('span');
            chip.className = 'activity-chip';
            chip.innerHTML = '<span class="emoji">' + info.emoji + '</span><span>' + escapeHtml(info.label) + '</span>';
            if (entry.attachments && entry.attachments.length) {
                chip.appendChild(buildAttachmentBadge(entry.attachments));
            }
            wrap.appendChild(chip);
        });
        card.style.display = any ? '' : 'none';
    }

    // ==================================================================
    // ADMIN VIEW
    // ==================================================================
    var adminBound = false;

    function startAdmin() {
        showScreen('screen_admin');
        renderAdminChildSelect();
        if (state.children.length) {
            var savedChid = null;
            try { savedChid = parseInt(localStorage.getItem('jg_admin_chid'), 10); } catch (e) { savedChid = null; }
            var savedIsValid = state.children.some(function (c) { return c.chid === savedChid; });
            state.chid = savedIsValid ? savedChid : state.children[0].chid;
            document.getElementById('admin_child_select').value = state.chid;
        }
        renderMoodButtons();
        renderPottyTypeButtons();
        renderQuickNoteButtons();
        renderIncidentButtons();
        bindAdminEvents();
        fetchDayAdmin();
    }

    // ------------------------------------------------------------------
    // Preview as Parent - lets staff see the currently-selected child's
    // status exactly as that child's parent would see it (read-only,
    // same rendering code the parent screen uses), without logging out
    // of the admin session. Always opens on today, since the admin view
    // itself never looks at past days either.
    // ------------------------------------------------------------------
    function enterParentPreview() {
        if (!state.chid) { return; }
        state.previewMode = true;
        state.adminPreviewChid = state.chid;
        document.getElementById('parent_child_tabs').innerHTML = '';
        document.getElementById('preview_banner').style.display = '';
        document.getElementById('parent_logout').style.display = 'none';
        state.daykey = null;
        state.todayDaykey = null;
        showScreen('screen_parent');
        bindParentNav();
        fetchDayParent();
    }

    function exitParentPreview() {
        state.previewMode = false;
        document.getElementById('preview_banner').style.display = 'none';
        document.getElementById('parent_logout').style.display = '';
        state.chid = state.adminPreviewChid || state.chid;
        document.getElementById('admin_child_select').value = state.chid;
        showScreen('screen_admin');
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

        const isMobile = window.matchMedia("(pointer: coarse)").matches;
        if (isMobile) {
            state.children.forEach(function (c) {
                var fam = c.family_name || 'Family';
                var opt = document.createElement('option');
                opt.value = c.chid;
                opt.textContent = c.name;
                select.appendChild(opt);
            });
        } else {
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
                    if (res.success) {
                        flashExpandCard('mood', 'mood_card_toggle', 'admin_mood_history');
                        renderAdminDay(res.day);
                    }
                });
            });
            wrap.appendChild(btn);
        });
    }

    function findPotty(day, evid) {
        for (var i = 0; i < day.potty.length; i++) {
            if (day.potty[i].evid === evid) { return day.potty[i]; }
        }
        return null;
    }

    // Shared chip builder: read-only for parents, edit/delete for admin.
    // A prominent, visually distinct badge button (not tiny inline text)
    // showing an attachment count exists; tapping opens a viewer with all
    // of them as thumbnails, each opening full-size in a new tab - works
    // the same on desktop (click) and mobile (tap).
    function buildAttachmentBadge(attachments) {
        var badge = document.createElement('button');
        badge.type = 'button';
        badge.className = 'attachment-badge';
        badge.innerHTML = '<i class="fa-solid fa-paperclip"></i>';
        badge.title = attachments.length === 1 ? 'View attachment' : 'View ' + attachments.length + ' attachments';
        badge.addEventListener('click', function (e) {
            e.stopPropagation();
            openAttachmentViewer(attachments);
        });
        return badge;
    }

    function openAttachmentViewer(attachments) {
        var overlay = document.getElementById('attachment_viewer_overlay');
        var panel = document.getElementById('attachment_viewer_panel');
        var html = '<div class="potty-panel-header"><span>Attachments</span>' +
            '<button type="button" class="link-button" data-role="close">Done</button></div>' +
            '<div class="attachment-viewer-grid">';
        attachments.forEach(function (a) {
            var isImage = /\.(jpg|jpeg|png|gif|webp|heic)$/i.test(a.filename);
            html += '<a href="' + a.url + '" target="_blank" class="attachment-viewer-item">' +
                (isImage ? '<img src="' + a.url + '" alt="attachment">' : '📷 ' + escapeHtml(a.filename)) +
                '</a>';
        });
        html += '</div>';
        panel.innerHTML = html;
        panel.querySelector('[data-role="close"]').addEventListener('click', function () {
            overlay.style.display = 'none';
        });
        overlay.style.display = '';
    }

    function buildPottyChip(p, editable) {
        var extras = '';
        if (p.cream)  { extras += ' \ud83e\uddf4'; }
        if (p.peed)   { extras += ' \ud83d\udca7'; }
        if (p.pooped) { extras += ' \ud83d\udca9'; }
        var typeInfo = state.pottyTypes[p.type];
        if (typeInfo && typeInfo.asks_potty && !p.peed && !p.pooped) { extras += ' 👎'; }

        var buttons = [];
        if (editable) {
            buttons.push({
                icon: 'pencil',
                title: 'Edit',
                onClick: function () { openPottyPanel(p); }
            });
            buttons.push({
                icon: 'trash',
                title: 'Delete',
                onClick: function () {
                    post('delete_potty', { chid: state.chid, evid: p.evid }).then(function (res) {
                        if (res.success) { renderAdminDay(res.day); }
                    });
                }
            });
        }

        return buildStatusChip({
            extraClass: 'potty-chip',
            background: p.color,
            time: p.time,
            emoji: p.emoji,
            label: p.label,
            extraText: extras,
            attachments: p.attachments,
            buttons: buttons
        });
    }

    function renderPottyTypeButtons() {
        var wrap = document.getElementById('potty_type_buttons');
        wrap.innerHTML = '';
        Object.keys(state.pottyTypes).forEach(function (key) {
            var info = state.pottyTypes[key];
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.style.background = info.color;
            btn.innerHTML = '<span class="emoji">' + info.emoji + '</span><span>' + escapeHtml(info.label) + '</span>';
            btn.addEventListener('click', function () {
                post('add_potty', { chid: state.chid, type: key }).then(function (res) {
                    if (res.success) {
                        flashExpandCard('potty', 'potty_card_toggle', 'admin_potty_history');
                        renderAdminDay(res.day);
                        var entry = findPotty(res.day, res.evid);
                        if (entry) { openPottyPanel(entry); }
                    }
                });
            });
            wrap.appendChild(btn);
        });
    }

    function renderQuickNoteButtons() {
        var wrap = document.getElementById('quick_note_buttons');
        wrap.innerHTML = '';
        Object.keys(state.quickNotes).forEach(function (key) {
            var info = state.quickNotes[key];
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'quick-note-btn';
            btn.innerHTML = '<span class="emoji">' + info.emoji + '</span><span>' + escapeHtml(info.label) + '</span>';
            btn.addEventListener('click', function () {
                post('quick_note', { chid: state.chid, key: key }).then(function (res) {
                    if (res.success) {
                        renderAdminDay(res.day);
                        btn.classList.add('sent');
                        setTimeout(function () { btn.classList.remove('sent'); }, 1200);
                    }
                });
            });
            wrap.appendChild(btn);
        });
    }

    // ------------------------------------------------------------------
    // Incidents Quick Report - one tap creates the entry (pre-filled note)
    // and opens the editor immediately for extra detail + attachments.
    // ------------------------------------------------------------------
    function renderIncidentButtons() {
        var wrap = document.getElementById('incident_buttons');
        wrap.innerHTML = '';
        Object.keys(state.incidentTypes).forEach(function (key) {
            var info = state.incidentTypes[key];
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'incident-btn';
            btn.style.background = info.color;
            btn.title = info.label;
            btn.innerHTML = info.emoji + '<div>' + info.label + '</div>';
            btn.addEventListener('click', function () {
                post('add_incident', { chid: state.chid, type: key }).then(function (res) {
                    if (res.success) {
                        flashExpandCard('incidents', 'incidents_card_toggle', 'admin_incidents_history');
                        renderAdminDay(res.day);
                        var entry = findByEvid(res.day.incidents, res.evid);
                        if (entry) { openIncidentPanel(entry); }
                    }
                });
            });
            wrap.appendChild(btn);
        });
    }

    function findByEvid(list, evid) {
        for (var i = 0; i < list.length; i++) {
            if (list[i].evid === evid) { return list[i]; }
        }
        return null;
    }

    function buildIncidentChip(inc, editable) {
        var buttons = [];
        if (editable) {
            buttons.push({
                icon: 'pencil',
                title: 'Edit',
                onClick: function () { openIncidentPanel(inc); }
            });
            buttons.push({
                icon: 'trash',
                title: 'Delete',
                onClick: function () {
                    post('delete_incident', { chid: state.chid, evid: inc.evid }).then(function (res) {
                        if (res.success) { renderAdminDay(res.day); }
                    });
                }
            });
        }

        return buildStatusChip({
            extraClass: 'potty-chip',
            background: inc.color,
            time: inc.time,
            emoji: inc.emoji,
            label: inc.label,
            attachments: inc.attachments,
            note: inc.note,
            buttons: buttons
        });
    }

    var incidentPanelState = null;

    function openIncidentPanel(entry) {
        incidentPanelState = JSON.parse(JSON.stringify(entry));
        document.getElementById('incident_panel_overlay').style.display = '';
        renderIncidentPanel();
    }

    function closeIncidentPanel() {
        document.getElementById('incident_panel_overlay').style.display = 'none';
        incidentPanelState = null;
    }

    function saveIncidentPanelState() {
        var inc = incidentPanelState;
        var parts = (inc.hm || '00:00').split(':');
        post('edit_incident', {
            chid: state.chid, evid: inc.evid, type: inc.type, note: inc.note,
            hour: parts[0], minute: parts[1]
        }).then(function (res) {
            if (!res.success) { return; }
            renderAdminDay(res.day);
            var updated = findByEvid(res.day.incidents, inc.evid);
            if (updated) {
                incidentPanelState = updated;
                renderIncidentPanel();
            }
        });
    }

    function renderIncidentPanel() {
        var panel = document.getElementById('incident_panel');
        var inc = incidentPanelState;
        var info = state.incidentTypes[inc.type];

        var html = '<div class="potty-panel-header">' +
            '<span class="emoji">' + info.emoji + '</span><span>' + escapeHtml(info.label) + '</span>' +
            '<label class="potty-attach-icon-btn" title="Add Photo"><i class="fa-solid fa-camera"></i>&nbsp;Attach' +
            '<input type="file" data-role="file" accept="image/*,.pdf" multiple style="display:none;"></label>' +
            '</div>';

        html += '<div class="potty-type-switch">';
        Object.keys(state.incidentTypes).forEach(function (key) {
            var t = state.incidentTypes[key];
            html += '<button type="button" class="potty-type-btn' + (key === inc.type ? ' active' : '') +
                '" data-type="' + key + '" style="background:' + t.color + '">' + t.emoji + '</button>';
        });
        html += '</div>';

        html += '<label class="potty-time-row">Time <input type="time" data-role="time"></label>';

        html += '<textarea class="app-textarea" data-role="note" rows="3" placeholder="What happened?"></textarea>';

        html += '<div class="potty-attachments" data-role="attachments"></div>';

        html += '<div class="potty-panel-footer">' +
            '<button type="button" class="primary-button" data-role="close">\ud83d\udcbe Save</button>' +
            '</div>';

        panel.innerHTML = html;

        panel.querySelector('[data-role="close"]').addEventListener('click', closeIncidentPanel);

        panel.querySelectorAll('.potty-type-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var newType = btn.getAttribute('data-type');
                // Only auto-update the note if it's still the old type's
                // default (untouched) - never overwrite a customized note.
                var oldDefault = (state.incidentTypes[incidentPanelState.type] || {}).default_note || '';
                if (incidentPanelState.note === oldDefault) {
                    incidentPanelState.note = (state.incidentTypes[newType] || {}).default_note || '';
                }
                incidentPanelState.type = newType;
                saveIncidentPanelState();
            });
        });

        var timeInput = panel.querySelector('[data-role="time"]');
        timeInput.value = inc.hm || '';
        timeInput.addEventListener('change', function (e) {
            incidentPanelState.hm = e.target.value;
            saveIncidentPanelState();
        });

        var noteInput = panel.querySelector('[data-role="note"]');
        noteInput.value = inc.note || '';
        noteInput.addEventListener('change', function (e) {
            incidentPanelState.note = e.target.value;
            saveIncidentPanelState();
        });

        renderAttachmentGrid(panel.querySelector('[data-role="attachments"]'), incidentPanelState, function () { return incidentPanelState.evid; });

        panel.querySelector('[data-role="file"]').addEventListener('change', function (e) {
            uploadAttachments(e.target.files, incidentPanelState.evid, 'incident', incidentPanelState, function () {
                renderAttachmentGrid(panel.querySelector('[data-role="attachments"]'), incidentPanelState, function () { return incidentPanelState.evid; });
                fetchDayAdmin();
            });
            e.target.value = '';
        });
    }

    // ------------------------------------------------------------------
    // Potty Time entry panel - opened after a type tap or an edit (\u270e).
    // pottyPanelState holds a working copy; each field change saves via
    // edit_potty right away (no separate Save button needed).
    // ------------------------------------------------------------------
    var pottyPanelState = null;

    function openPottyPanel(entry) {
        pottyPanelState = JSON.parse(JSON.stringify(entry));
        document.getElementById('potty_panel_overlay').style.display = '';
        renderPottyPanel();
    }

    function closePottyPanel() {
        document.getElementById('potty_panel_overlay').style.display = 'none';
        pottyPanelState = null;
    }

    function savePottyPanelState() {
        var p = pottyPanelState;
        var parts = (p.hm || '00:00').split(':');
        post('edit_potty', {
            chid: state.chid, evid: p.evid, type: p.type,
            hour: parts[0], minute: parts[1],
            cream: p.cream ? '1' : '', peed: p.peed ? '1' : '', pooped: p.pooped ? '1' : ''
        }).then(function (res) {
            if (!res.success) { return; }
            renderAdminDay(res.day);
            var updated = findPotty(res.day, p.evid);
            if (updated) {
                pottyPanelState = updated;
                renderPottyPanel();
            }
        });
    }

    function renderPottyPanel() {
        var panel = document.getElementById('potty_panel');
        var p = pottyPanelState;
        var info = state.pottyTypes[p.type];

        var html = '<div class="potty-panel-header">' +
            '<span class="emoji">' + info.emoji + '</span><span>' + escapeHtml(info.label) + '</span>' +
            '<label class="potty-attach-icon-btn" title="Add Photo"><i class="fa-solid fa-camera"></i>&nbsp;Attach' +
            '<input type="file" data-role="file" accept="image/*,.pdf" multiple style="display:none;"></label>' +
            '</div>';

        html += '<div class="potty-type-switch">';
        Object.keys(state.pottyTypes).forEach(function (key) {
            var t = state.pottyTypes[key];
            html += '<button type="button" class="potty-type-btn' + (key === p.type ? ' active' : '') +
                '" data-type="' + key + '" style="background:' + t.color + '">' + t.emoji + '</button>';
        });
        html += '</div>';

        html += '<label class="potty-time-row">Time <input type="time" data-role="time"></label>';

        if (info.asks_cream) {
            html += '<div class="potty-extra-row"><span>\ud83e\uddf4 Cream used?</span>' +
                '<label><input class="styled-checkbox" type="checkbox" data-role="cream"' + (p.cream ? ' checked' : '') + '></label></div>';
        }
        if (info.asks_potty) {
            html += '<div class="potty-extra-row">' +
                '<label><input class="styled-checkbox" type="checkbox" data-role="peed"' + (p.peed ? ' checked' : '') + '> \ud83d\udca7 Peed</label>' +
                '<label><input class="styled-checkbox" type="checkbox" data-role="pooped"' + (p.pooped ? ' checked' : '') + '> \ud83d\udca9 Pooped</label></div>';
        }

        html += '<div class="potty-attachments" data-role="attachments"></div>';

        html += '<div class="potty-panel-footer">' +
            '<button type="button" class="primary-button" data-role="close">\ud83d\udcbe Save</button>' +
            '</div>';

        panel.innerHTML = html;

        panel.querySelector('[data-role="close"]').addEventListener('click', closePottyPanel);

        panel.querySelectorAll('.potty-type-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                pottyPanelState.type = btn.getAttribute('data-type');
                savePottyPanelState();
            });
        });

        var timeInput = panel.querySelector('[data-role="time"]');
        timeInput.value = p.hm || '';
        timeInput.addEventListener('change', function (e) {
            pottyPanelState.hm = e.target.value;
            savePottyPanelState();
        });

        ['cream', 'peed', 'pooped'].forEach(function (field) {
            var box = panel.querySelector('[data-role="' + field + '"]');
            if (box) {
                box.addEventListener('change', function (e) {
                    pottyPanelState[field] = e.target.checked;
                    savePottyPanelState();
                });
            }
        });

        renderAttachmentGrid(panel.querySelector('[data-role="attachments"]'), pottyPanelState, function () { return pottyPanelState.evid; });

        panel.querySelector('[data-role="file"]').addEventListener('change', function (e) {
            uploadAttachments(e.target.files, pottyPanelState.evid, 'potty', pottyPanelState, function () {
                renderAttachmentGrid(panel.querySelector('[data-role="attachments"]'), pottyPanelState, function () { return pottyPanelState.evid; });
                fetchDayAdmin();
            });
            e.target.value = '';
        });
    }

    // Shared by the Potty Time and Incident panels. $state is the panel's
    // working entry object (must have an .attachments array); refreshes
    // it in place after upload/delete and re-renders into $wrap.
    function renderAttachmentGrid(wrap, panelState, getEvid) {
        wrap.innerHTML = '';
        (panelState.attachments || []).forEach(function (a) {
            var item = document.createElement('div');
            item.className = 'potty-attachment-item';
            var isImage = /\.(jpg|jpeg|png|gif|webp|heic)$/i.test(a.filename);
            item.innerHTML = isImage
                ? '<img src="' + a.url + '" alt="attachment">'
                : '<a href="' + a.url + '" target="_blank">\ud83d\udcc4 ' + escapeHtml(a.filename) + '</a>';
            var delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'attachment-delete';
            delBtn.textContent = '\u2715';
            delBtn.addEventListener('click', function () {
                post('delete_attachment', { chid: state.chid, did: a.did }).then(function (res) {
                    if (res.success) {
                        panelState.attachments = res.attachments;
                        renderAttachmentGrid(wrap, panelState, getEvid);
                        fetchDayAdmin();
                    }
                });
            });
            item.appendChild(delBtn);
            wrap.appendChild(item);
        });
    }

    // Uploads one or more files (camera or library - accept="image/*"
    // with no "capture" attribute offers both on mobile) sequentially,
    // tagging each with $context ('potty' or 'incident') so they stay
    // organized in the underlying documents table.
    function uploadAttachments(fileList, evid, context, panelState, onDone) {
        var files = Array.prototype.slice.call(fileList);
        if (!files.length) { return; }

        function uploadNext(i) {
            if (i >= files.length) { onDone(); return; }
            var formData = new FormData();
            formData.append('action', 'upload_attachment');
            formData.append('chid', state.chid);
            formData.append('evid', evid);
            formData.append('context', context);
            formData.append('file', files[i]);
            fetch('ajax/status_ajax.php', { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        panelState.attachments = res.attachments;
                    } else {
                        alert(res.message || 'Upload failed.');
                    }
                    uploadNext(i + 1);
                });
        }
        uploadNext(0);
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
            try { localStorage.setItem('jg_admin_chid', state.chid); } catch (err) { /* ignore */ }
            fetchDayAdmin();
        });
        document.getElementById('admin_logout').addEventListener('click', logout);
        document.getElementById('admin_preview_btn').addEventListener('click', enterParentPreview);
        document.getElementById('exit_preview_btn').addEventListener('click', exitParentPreview);
        document.getElementById('admin_links_btn').addEventListener('click', openLinksPanel);
        document.getElementById('links_back_btn').addEventListener('click', function () {
            showScreen('screen_admin');
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
                    flashExpandCard('notes', 'notes_card_toggle', 'admin_notes_history');
                    renderAdminDay(res.day);
                }
            });
        });

        document.getElementById('add_bottle_btn').addEventListener('click', function () {
            openBottlePanel(function (ounces) {
                post('add_bottle', { chid: state.chid, ounces: ounces }).then(function (res) {
                    if (res.success) {
                        flashExpandCard('bottles', 'bottle_card_toggle', 'admin_bottle_history');
                        renderAdminDay(res.day);
                    }
                });
            });
        });

        bindActivitiesCopyPanel();

        document.getElementById('avatar_upload_btn').addEventListener('click', function () {
            document.getElementById('avatar_upload_input').click();
        });
        document.getElementById('avatar_upload_input').addEventListener('change', function (e) {
            var file = e.target.files && e.target.files[0];
            e.target.value = '';
            if (!file || !state.chid) { return; }
            var formData = new FormData();
            formData.append('action', 'upload_avatar');
            formData.append('chid', state.chid);
            formData.append('file', file);
            fetch('ajax/status_ajax.php', { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        renderAdminDay(res.day);
                    } else {
                        alert(res.message || 'Upload failed.');
                    }
                });
        });
    }

    // ------------------------------------------------------------------
    // Bottle ounces picker - quick-select popup, 1-8oz. Used both for
    // adding a new bottle and for editing an existing entry's amount
    // (the callback decides which action to POST).
    // ------------------------------------------------------------------
    function openBottlePanel(onSelect) {
        var overlay = document.getElementById('bottle_panel_overlay');
        var panel = document.getElementById('bottle_panel');
        var html = '<div class="potty-panel-header"><span class="emoji">' + state.bottle.emoji + '</span><span>How many ounces?</span>' +
            '<button type="button" class="link-button" data-role="close">Cancel</button></div>' +
            '<div class="ounce-buttons">';
        state.bottleOunces.forEach(function (oz) {
            html += '<button type="button" class="ounce-btn" data-oz="' + oz + '">' + oz + '</button>';
        });
        html += '</div>';
        panel.innerHTML = html;
        panel.querySelector('[data-role="close"]').addEventListener('click', function () {
            overlay.style.display = 'none';
        });
        panel.querySelectorAll('.ounce-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                overlay.style.display = 'none';
                onSelect(parseInt(btn.getAttribute('data-oz'), 10));
            });
        });
        overlay.style.display = '';
    }

    // ------------------------------------------------------------------
    // Menu (Breakfast / Lunch / Dinner) - editable panels for admin, each
    // with its own textarea, quick-fill suggestions, and "Copy to Kids"
    // scoped to that one meal only.
    // ------------------------------------------------------------------
    function renderMealCopyList(wrap, mealKey) {
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
            label.innerHTML = '<input class="styled-checkbox" type="checkbox" value="' + c.chid + '"> ' + escapeHtml(c.name);
            groups[fam].appendChild(label);
        });
    }

    function fetchMealSuggestions(mealKey, wrap, textareaEl) {
        if (!state.chid) { return; }
        post('get_menu_suggestions', { chid: state.chid, meal: mealKey }).then(function (res) {
            if (res.success) { renderMealSuggestions(wrap, res.suggestions, textareaEl); }
        });
    }

    function renderMealSuggestions(wrap, suggestions, textareaEl) {
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
                textareaEl.value = s.menu;
            });
            chipRow.appendChild(chip);
        });
        wrap.appendChild(chipRow);
    }

    // Tap an emoji to rate how this child ate this meal; tap the same one
    // again to clear it. Instant-save, like mood/potty taps.
    function renderMealRatingButtons(wrap, mealKey, currentRating) {
        wrap.innerHTML = '';
        Object.keys(state.mealRatings).forEach(function (key) {
            var info = state.mealRatings[key];
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'meal-rating-btn' + (key === currentRating ? ' active' : '');
            btn.innerHTML = '<span class="emoji">' + info.emoji + '</span><span>' + escapeHtml(info.label) + '</span>';
            btn.addEventListener('click', function () {
                var newRating = (key === currentRating) ? '' : key;
                post('set_meal_rating', { chid: state.chid, meal: mealKey, rating: newRating }).then(function (res) {
                    if (res.success) { renderAdminDay(res.day); }
                });
            });
            wrap.appendChild(btn);
        });
        wrap.appendChild(buildSetAllButton(state.mealRatings, function () { return currentRating; }, 'set_meal_rating_all', { meal: mealKey }, mealKey));
    }

    function buildAdminMealPanel(mealKey, mealInfo, day) {
        var section = document.createElement('section');
        section.className = 'card';
        section.innerHTML =
            '<h2>' + mealInfo.emoji + ' ' + escapeHtml(mealInfo.label) + '</h2>' +
            '<div class="meal-rating-buttons" data-role="rating-buttons"></div>' +
            '<div class="menu-suggestions" data-role="suggestions"></div>' +
            '<textarea class="app-textarea" rows="3" placeholder="' + escapeHtml(mealInfo.label) + ' menu\u2026" data-role="input"></textarea>' +
            '<div class="menu-actions">' +
            '<button type="button" class="primary-button" data-role="save">Save</button>' +
            '<button type="button" class="secondary-button" data-role="copy-toggle">Copy to Kids&hellip;</button>' +
            '<span class="save-status" data-role="save-status"></span>' +
            '</div>' +
            '<div class="menu-copy-panel" data-role="copy-panel" style="display:none;">' +
            '<p class="muted menu-copy-hint">Copy this ' + escapeHtml(mealInfo.label.toLowerCase()) + ' to:</p>' +
            '<div class="menu-copy-list" data-role="copy-list"></div>' +
            '<div class="menu-copy-buttons">' +
            '<button type="button" class="link-button" data-role="copy-cancel">Cancel</button>' +
            '<button type="button" class="primary-button" data-role="copy-confirm">Copy</button>' +
            '</div>' +
            '<span class="save-status" data-role="copy-status"></span>' +
            '</div>';

        var textarea    = section.querySelector('[data-role="input"]');
        var ratingWrap  = section.querySelector('[data-role="rating-buttons"]');
        var suggestions = section.querySelector('[data-role="suggestions"]');
        var saveBtn      = section.querySelector('[data-role="save"]');
        var saveStatus   = section.querySelector('[data-role="save-status"]');
        var copyToggle   = section.querySelector('[data-role="copy-toggle"]');
        var copyPanel    = section.querySelector('[data-role="copy-panel"]');
        var copyList     = section.querySelector('[data-role="copy-list"]');
        var copyStatus   = section.querySelector('[data-role="copy-status"]');

        textarea.value = (day.menus && day.menus[mealKey]) || '';
        renderMealRatingButtons(ratingWrap, mealKey, (day.ratings && day.ratings[mealKey]) || '');

        saveBtn.addEventListener('click', function () {
            post('save_menu', { chid: state.chid, meal: mealKey, menu: textarea.value }).then(function (res) {
                if (res.success) {
                    saveStatus.textContent = 'Saved';
                    setTimeout(function () { saveStatus.textContent = ''; }, 2000);
                }
            });
        });

        copyToggle.addEventListener('click', function () {
            var opening = copyPanel.style.display === 'none';
            copyPanel.style.display = opening ? '' : 'none';
            if (opening) { renderMealCopyList(copyList, mealKey); }
        });
        section.querySelector('[data-role="copy-cancel"]').addEventListener('click', function () {
            copyPanel.style.display = 'none';
        });
        section.querySelector('[data-role="copy-confirm"]').addEventListener('click', function () {
            var checked = copyList.querySelectorAll('input[type="checkbox"]:checked');
            var chids = Array.prototype.map.call(checked, function (cb) { return cb.value; });
            if (!chids.length) {
                copyStatus.textContent = 'Select at least one child.';
                return;
            }
            chids.push(state.chid); // keeps the current child's saved menu in sync too
            post('copy_menu_to_children', { meal: mealKey, menu: textarea.value, chids: chids.join(',') }).then(function (res) {
                if (res.success) {
                    var count = res.written.length;
                    copyStatus.textContent = 'Copied to ' + count + ' kid' + (count === 1 ? '' : 's') + '.';
                    copyPanel.style.display = 'none';
                    fetchMealSuggestions(mealKey, suggestions, textarea);
                } else {
                    copyStatus.textContent = res.message || 'Could not copy.';
                }
                setTimeout(function () { copyStatus.textContent = ''; }, 2500);
            });
        });

        fetchMealSuggestions(mealKey, suggestions, textarea);

        return section;
    }

    function renderAdminMealSections(day) {
        var container = document.getElementById('admin_meal_sections');
        container.innerHTML = '';
        orderedMealKeys().forEach(function (mealKey) {
            container.appendChild(buildAdminMealPanel(mealKey, state.meals[mealKey], day));
        });
    }

    // ------------------------------------------------------------------
    // Activities - multi-select toggle buttons (unlike mood/potty, more
    // than one can be active at once) with a "Copy to Kids" panel that
    // mirrors today's whole checked set onto other children.
    // ------------------------------------------------------------------
    function renderActivitiesCopyList(wrap) {
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
            label.innerHTML = '<input class="styled-checkbox" type="checkbox" value="' + c.chid + '"> ' + escapeHtml(c.name);
            groups[fam].appendChild(label);
        });
    }

    function renderAdminActivities(day) {
        var buttonsWrap = document.getElementById('activity_buttons');
        buttonsWrap.innerHTML = '';
        var activities = day.activities || {};

        orderedActivityKeys().forEach(function (key) {
            var info = state.activities[key];
            var entry = activities[key] || { on: false, arid: 0, attachments: [] };

            var wrap = document.createElement('div');
            wrap.className = 'activity-btn-wrap';

            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'activity-btn' + (entry.on ? ' active' : '');
            btn.innerHTML = '<span class="emoji">' + info.emoji + '</span><span>' + escapeHtml(info.label) + '</span>';
            btn.addEventListener('click', function () {
                post('toggle_activity', { chid: state.chid, activity: key, on: entry.on ? '' : '1' }).then(function (res) {
                    if (res.success) { renderAdminDay(res.day); }
                });
            });
            wrap.appendChild(btn);

            // Photo button only shows once an activity is checked - same
            // as Potty Time/Incidents, which only offer attachments on an
            // entry that already exists.
            if (entry.on) {
                var camBtn = document.createElement('button');
                camBtn.type = 'button';
                camBtn.className = 'activity-attach-btn' + (entry.attachments.length ? ' has-attachments' : '');
                camBtn.innerHTML = '<i class="fa-solid fa-camera"></i>' +
                    (entry.attachments.length ? '<span class="attach-count">' + entry.attachments.length + '</span>' : '');
                camBtn.title = entry.attachments.length ? 'View/add photos' : 'Add a photo';
                camBtn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    openActivityPanel(info, entry.arid, entry.attachments);
                });
                wrap.appendChild(camBtn);
            }

            buttonsWrap.appendChild(wrap);
        });
    }

    // Simple photo panel for one Activities entry - just a header with an
    // "Add Photo" control and the attachment grid, no type/time/note
    // fields since there's nothing else to edit here.
    function openActivityPanel(info, arid, attachments) {
        var overlay = document.getElementById('activity_panel_overlay');
        var panel = document.getElementById('activity_panel');
        var panelState = { attachments: attachments || [] };

        var html = '<div class="potty-panel-header">' +
            '<span class="emoji">' + info.emoji + '</span><span>' + escapeHtml(info.label) + '</span>' +
            '<label class="potty-attach-icon-btn" title="Add Photo"><i class="fa-solid fa-camera"></i>&nbsp;Attach' +
            '<input type="file" data-role="file" accept="image/*" multiple style="display:none;"></label>' +
            '</div>' +
            '<div class="potty-attachments" data-role="attachments"></div>' +
            '<div class="potty-panel-footer">' +
            '<button type="button" class="primary-button" data-role="close">Done</button>' +
            '</div>';
        panel.innerHTML = html;

        panel.querySelector('[data-role="close"]').addEventListener('click', function () {
            overlay.style.display = 'none';
        });

        renderAttachmentGrid(panel.querySelector('[data-role="attachments"]'), panelState, function () { return arid; });

        panel.querySelector('[data-role="file"]').addEventListener('change', function (e) {
            uploadActivityAttachments(e.target.files, arid, panelState, function () {
                renderAttachmentGrid(panel.querySelector('[data-role="attachments"]'), panelState, function () { return arid; });
                fetchDayAdmin();
            });
            e.target.value = '';
        });

        overlay.style.display = '';
    }

    // Same sequential-upload pattern as uploadAttachments(), just posting
    // 'arid' (an Activities row) instead of 'evid'+'context'.
    function uploadActivityAttachments(fileList, arid, panelState, onDone) {
        var files = Array.prototype.slice.call(fileList);
        if (!files.length) { return; }

        function uploadNext(i) {
            if (i >= files.length) { onDone(); return; }
            var formData = new FormData();
            formData.append('action', 'upload_attachment');
            formData.append('chid', state.chid);
            formData.append('arid', arid);
            formData.append('file', files[i]);
            fetch('ajax/status_ajax.php', { method: 'POST', body: formData })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res.success) {
                        panelState.attachments = res.attachments;
                    } else {
                        alert(res.message || 'Upload failed.');
                    }
                    uploadNext(i + 1);
                });
        }
        uploadNext(0);
    }

    function bindActivitiesCopyPanel() {
        var toggle  = document.getElementById('activities_copy_toggle');
        var panel   = document.getElementById('activities_copy_panel');
        var list    = document.getElementById('activities_copy_list');
        var status_ = document.getElementById('activities_copy_status');

        toggle.addEventListener('click', function () {
            var opening = panel.style.display === 'none';
            panel.style.display = opening ? '' : 'none';
            if (opening) { renderActivitiesCopyList(list); }
        });
        document.getElementById('activities_copy_cancel').addEventListener('click', function () {
            panel.style.display = 'none';
        });
        document.getElementById('activities_copy_confirm').addEventListener('click', function () {
            var checked = list.querySelectorAll('input[type="checkbox"]:checked');
            var chids = Array.prototype.map.call(checked, function (cb) { return cb.value; });
            if (!chids.length) {
                status_.textContent = 'Select at least one child.';
                return;
            }
            post('copy_activities_to_children', { chid: state.chid, chids: chids.join(',') }).then(function (res) {
                if (res.success) {
                    var count = res.written.length;
                    status_.textContent = 'Copied to ' + count + ' kid' + (count === 1 ? '' : 's') + '.';
                    panel.style.display = 'none';
                } else {
                    status_.textContent = res.message || 'Could not copy.';
                }
                setTimeout(function () { status_.textContent = ''; }, 2500);
            });
        });
    }

    // ------------------------------------------------------------------
    // Mood - admin chips are editable (change which mood it was) and
    // deletable, unlike the read-only parent chips.
    // ------------------------------------------------------------------
    function buildAdminMoodChip(m) {
        var chip = buildStatusChip({
            background: m.color,
            time: m.time,
            emoji: m.emoji,
            label: m.label,
            buttons: [
                {
                    icon: 'clock',
                    title: 'Change time',
                    onClick: function () {
                        openTimeEditor(chip, m.hm, function (hour, minute) {
                            post('edit_mood_time', { chid: state.chid, evid: m.evid, hour: hour, minute: minute }).then(function (res) {
                                if (res.success) { renderAdminDay(res.day); }
                            });
                        });
                    }
                },
                {
                    icon: 'pencil',
                    title: 'Change mood',
                    onClick: function () {
                        var committed = false;
                        var select = document.createElement('select');
                        select.className = 'mood-edit-select';
                        Object.keys(state.moods).forEach(function (key) {
                            var opt = document.createElement('option');
                            opt.value = key;
                            opt.textContent = state.moods[key].label;
                            if (key === m.mood) { opt.selected = true; }
                            select.appendChild(opt);
                        });
                        chip.innerHTML = '';
                        chip.appendChild(select);
                        select.focus();

                        select.addEventListener('change', function () {
                            committed = true;
                            var newmood = select.value;
                            if (newmood === m.mood) {
                                if (state.lastAdminDay) { renderAdminDay(state.lastAdminDay); }
                                return;
                            }
                            post('edit_mood', { chid: state.chid, evid: m.evid, mood: newmood }).then(function (res) {
                                if (res.success) { renderAdminDay(res.day); }
                            });
                        });
                        select.addEventListener('blur', function () {
                            if (!committed && state.lastAdminDay) { renderAdminDay(state.lastAdminDay); }
                        });
                    }
                },
                {
                    icon: 'trash',
                    title: 'Delete',
                    onClick: function () {
                        post('delete_mood', { chid: state.chid, evid: m.evid }).then(function (res) {
                            if (res.success) { renderAdminDay(res.day); }
                        });
                    }
                }
            ]
        });

        return chip;
    }

    function fetchDayAdmin() {
        if (!state.chid) { return; }
        post('get_day', { chid: state.chid }).then(function (res) {
            if (res.success) { renderAdminDay(res.day); }
        });
    }

    function renderAdminDay(day) {
        state.lastAdminDay = day;
        document.getElementById('admin_day_label').textContent = day.date_label + ' (today)';

        // Sticky child bar (stays visible while scrolling so staff always
        // know which child's log they're editing)
        document.getElementById('admin_sticky_name').textContent = day.name;
        document.getElementById('admin_sticky_avatar').innerHTML = day.avatar;

        // Avatar
        var avatar = document.querySelector('#screen_admin #avatar');
        avatar.innerHTML = day.avatar;

        // Mood timeline (recent taps today) - editable/deletable
        var moodWrap = document.getElementById('admin_mood_timeline');
        moodWrap.innerHTML = '';
        day.moods.forEach(function (m) {
            moodWrap.appendChild(buildAdminMoodChip(m));
        });
        applyCardCollapse('mood', 'mood_card_toggle', 'admin_mood_history', day.moods.length);

        // Potty Time (editable)
        var pottyWrap = document.getElementById('admin_potty_timeline');
        pottyWrap.innerHTML = '';
        day.potty.forEach(function (p) {
            pottyWrap.appendChild(buildPottyChip(p, true));
        });
        applyCardCollapse('potty', 'potty_card_toggle', 'admin_potty_history', day.potty.length);

        // Incidents Quick Report (editable)
        var incWrap = document.getElementById('admin_incidents_timeline');
        incWrap.innerHTML = '';
        day.incidents.forEach(function (inc) {
            incWrap.appendChild(buildIncidentChip(inc, true));
        });
        applyCardCollapse('incidents', 'incidents_card_toggle', 'admin_incidents_history', day.incidents.length);

        // Naptime - one consolidated card: notice text shows for every
        // child 1-3pm, buttons only for kids under the age cutoff, history
        // always shows below when there's any, and the simple rating
        // (+ Set for All) shows for kids at/above the age cutoff instead.
        // Card itself is hidden entirely when none of those apply.
        var naptimeCard = document.getElementById('admin_naptime_card');
        var noticeText = document.getElementById('admin_naptime_notice_text');
        var napBtnWrap = document.getElementById('naptime_buttons');

        noticeText.style.display = day.show_naptime_notice ? '' : 'none';

        napBtnWrap.style.display = day.show_naptime_buttons ? '' : 'none';
        if (day.show_naptime_buttons) {
            napBtnWrap.innerHTML = '';
            state.napDurations.forEach(function (mins) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.textContent = mins + 'min';
                btn.addEventListener('click', function () {
                    post('add_nap', { chid: state.chid, minutes: mins }).then(function (res) {
                        if (res.success) {
                            flashExpandCard('naps', 'naptime_card_toggle', 'admin_naps_history');
                            renderAdminDay(res.day);
                        }
                    });
                });
                napBtnWrap.appendChild(btn);
            });
        }

        var napsWrap = document.getElementById('admin_naps_timeline');
        napsWrap.innerHTML = '';
        day.naps.forEach(function (nap) {
            napsWrap.appendChild(buildNapChip(nap, true));
        });
        applyCardCollapse('naps', 'naptime_card_toggle', 'admin_naps_history', day.naps.length);

        var napRatingWrap = document.getElementById('nap_rating_buttons');

        napRatingWrap.style.display = day.show_nap_rating ? '' : 'none';
        if (day.show_nap_rating) {
            renderNapRatingButtons(napRatingWrap, day.nap_rating || '');
        }

        naptimeCard.style.display = (day.show_naptime_notice || day.show_naptime_buttons || day.naps.length || day.show_nap_rating) ? '' : 'none';

        // Bottles (only for children under the age cutoff)
        var bottleCard = document.getElementById('admin_bottle_card');
        bottleCard.style.display = day.show_bottles ? '' : 'none';
        if (day.show_bottles) {
            var bWrap = document.getElementById('admin_bottle_timeline');
            bWrap.innerHTML = '';
            day.bottles.forEach(function (b) {
                bWrap.appendChild(buildBottleChip(b, true));
            });
            applyCardCollapse('bottles', 'bottle_card_toggle', 'admin_bottle_history', day.bottles.length);
        }

        renderAdminMealSections(day);
        renderAdminActivities(day);

        var notesWrap = document.getElementById('admin_notes_list');
        notesWrap.innerHTML = '';
        day.notes.forEach(function (n) {
            var item = document.createElement('div');
            item.className = 'mood-chip mood-chip-with-note';
            item.style.background = n.color;
            item.style.color = n.textcolor;
            item.innerHTML = `
            <div class="mood-chip-row">
                <span class="chip-time timeandtype">
                    ` + escapeHtml(n.time) + `
                </span>
                <span class="chip-label">
                    ` + (n.notify ? ' \ud83d\udd14' : '') + escapeHtml(n.tag_title) + `
                </span>
                <div class="chip-button-area">
                    <button title="Edit" type="button" class="note-edit chip-icon-btn" data-nid="` + n.nid + `">
                        <i class="fa-solid fa-pencil"></i>
                    </button>
                    <button title="Delete" type="button" class="note-delete chip-icon-btn" data-nid="` + n.nid + `">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>
            </div>
            <div class="mood-chip-note">
                ` + escapeHtml(n.note) + `
            </div>
            `;

            notesWrap.appendChild(item);
        });
        notesWrap.querySelectorAll('.note-delete').forEach(function (btn) {
            btn.addEventListener('click', function () {
                post('delete_note', { chid: state.chid, nid: btn.getAttribute('data-nid') }).then(function (res) {
                    if (res.success) { renderAdminDay(res.day); }
                });
            });
        });
        applyCardCollapse('notes', 'notes_card_toggle', 'admin_notes_history', day.notes.length);
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