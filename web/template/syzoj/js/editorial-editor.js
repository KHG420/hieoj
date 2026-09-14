(function () {
    'use strict';
    // Clear only the draft whose contents match this successfully submitted article.
    document.querySelectorAll('[data-submitted-draft]').forEach(function (body) {
        try {
            var key = body.dataset.submittedDraft, draft = JSON.parse(localStorage.getItem(key));
            var source = body.editorialSource === undefined ? body.textContent : body.editorialSource;
            if (draft && draft.title.trim() === body.dataset.submittedTitle && draft.content.trim() === source && draft.format === body.dataset.contentFormat) localStorage.removeItem(key);
        } catch (e) { /* Submission succeeded even if browser storage is unavailable. */ }
    });
    var form = document.getElementById('editorial-form');
    if (!form) return;
    var title = document.getElementById('editorial-title'), content = document.getElementById('editorial-content');
    var format = form.elements.content_format.value, key = form.dataset.draftKey;
    var status = document.getElementById('draft-status'), restore = document.getElementById('draft-restore');
    var preview = document.getElementById('editorial-preview'), workspace = form.querySelector('.ed-editor-workspace');
    var submit = form.querySelector('button[type="submit"]'), canSubmit = !submit.disabled;
    var saveTimer, previewTimer, latest = null, conflict = false, dirty = false, submitting = false;
    var lastStored = null;
    function read(raw) {
        try {
            var d = JSON.parse(raw);
            return d && typeof d.title === 'string' && typeof d.content === 'string' && d.title.length <= 240 && d.content.length <= 100000 && d.format === format ? d : null;
        } catch (e) { return null; }
    }
    function same(d) { return d && d.title === title.value && d.content === content.value; }
    function counts() {
        document.getElementById('title-count').textContent = Array.from(title.value).length + ' / 120 字';
        document.getElementById('content-count').textContent = Array.from(content.value).length.toLocaleString() + ' / 50,000 字';
    }
    function render() {
        if (!content.value.trim()) { preview.textContent = '从一个关键思路开始。这里会展示读者看到的效果。'; return; }
        if (format === 'markdown' && window.editorialMarkdown) window.editorialMarkdown(content.value, preview);
        else { preview.textContent = content.value; preview.classList.add('ed-render-fallback'); }
    }
    function setMode(mode) {
        workspace.dataset.mode = mode;
        workspace.querySelector('.ed-editor-input').hidden = mode === 'preview';
        workspace.querySelector('.ed-editor-preview').hidden = mode === 'edit';
        form.querySelectorAll('[data-mode]').forEach(function (b) { b.setAttribute('aria-pressed',String(b.dataset.mode === mode)); });
        if (mode !== 'edit') render();
    }
    function announceConflict(d) {
        latest = d; conflict = true; restore.hidden = false;
        document.getElementById('draft-message').textContent = '本机另有一份草稿。恢复它，或保留当前内容后继续保存；当前正文尚未被覆盖。';
        document.getElementById('restore-draft').disabled = !d;
        status.textContent = '草稿冲突：已暂停自动保存，请选择要保留的内容。';
    }
    function save() {
        clearTimeout(saveTimer);
        if (!dirty || conflict) return;
        if (!key) { status.textContent = '尚未登录，无法保存本机草稿。请先下载正文。'; return; }
        try {
            var current = localStorage.getItem(key);
            if (current !== lastStored && !same(read(current))) { announceConflict(read(current)); return; }
            var data = {title:title.value,content:content.value,format:format,savedAt:new Date().toISOString()};
            if (!data.title && !data.content) { localStorage.removeItem(key); lastStored = null; }
            else { var serialized = JSON.stringify(data); localStorage.setItem(key,serialized); lastStored = serialized; }
            dirty = false;
            status.textContent = '已保存到当前浏览器 · ' + new Date().toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'});
        } catch (e) { status.textContent = '本机草稿保存失败，请下载草稿备份。'; }
    }
    function changed() {
        dirty = true; counts(); title.setCustomValidity(''); content.setCustomValidity('');
        if (!conflict) status.textContent = '正在保存本机草稿…';
        clearTimeout(saveTimer); saveTimer = setTimeout(save,600);
        clearTimeout(previewTimer); previewTimer = setTimeout(function () { if (workspace.dataset.mode !== 'edit') render(); },250);
    }
    try {
        lastStored = key ? localStorage.getItem(key) : null;
        var existing = read(lastStored);
        if (existing && !title.value && !content.value) {
            title.value = existing.title; content.value = existing.content;
            status.textContent = '已恢复本题草稿 · 仅保存在当前浏览器';
        } else if (existing && !same(existing)) announceConflict(existing);
        else if (existing) status.textContent = '本题草稿已保存在当前浏览器';
    } catch (e) { status.textContent = '浏览器不允许保存本机草稿，请使用下载草稿。'; }
    document.getElementById('restore-draft').addEventListener('click', function () {
        if (!latest) return;
        title.value = latest.title; content.value = latest.content;
        conflict = false; restore.hidden = true;
        try { lastStored = localStorage.getItem(key); } catch (e) { lastStored = null; }
        changed(); save(); render();
    });
    document.getElementById('keep-current').addEventListener('click', function () {
        conflict = false; restore.hidden = true;
        try { lastStored = localStorage.getItem(key); } catch (e) { lastStored = null; }
        changed(); save();
    });
    window.addEventListener('storage', function (event) {
        if (event.key !== key && event.key !== null) return;
        // Storage events may arrive after a newer local write. Read the current value.
        try {
            var current = localStorage.getItem(key), next = read(current);
            if (current === lastStored) return;
            if (same(next)) { lastStored = current; return; }
            announceConflict(next);
        } catch (e) { status.textContent = '无法读取本机草稿，请先下载当前内容。'; }
    });
    function insert(kind) {
        var selected = content.value.slice(content.selectionStart,content.selectionEnd), language = document.getElementById('code-language');
        // A longer fence keeps pasted code containing backticks inside the code block.
        var runs = selected.match(/`+/g) || [], fence = '`'.repeat(Math.max(3, runs.reduce(function (n,s) { return Math.max(n,s.length + 1); },3)));
        var text = {heading:'\n## ' + (selected || '小节标题') + '\n',bold:'**' + (selected || '重点内容') + '**',list:'\n- ' + (selected || '算法步骤') + '\n',link:'[' + (selected || '参考资料') + '](https://example.com)',code:'\n' + fence + (language ? language.value : '') + '\n' + (selected || '// 在此粘贴代码') + '\n' + fence + '\n',math:'$' + (selected || 'O(n)') + '$'}[kind];
        if (content.value.length - selected.length + text.length > content.maxLength) { status.textContent = '正文已接近长度上限，无法插入。'; return; }
        setMode(workspace.dataset.mode === 'preview' ? 'edit' : workspace.dataset.mode);
        content.setRangeText(text,content.selectionStart,content.selectionEnd,'end'); content.focus(); changed();
    }
    form.querySelectorAll('[data-insert]').forEach(function (button) { button.addEventListener('click', function () { insert(button.dataset.insert); }); });
    var template = document.getElementById('insert-template');
    if (template) template.addEventListener('click', function () {
        var text = '\n## 解题思路\n\n说明关键观察，以及为什么这个方法成立。\n\n## 算法步骤\n\n1. \n\n## 复杂度分析\n\n- 时间复杂度：$O(n)$\n- 空间复杂度：$O(n)$\n\n## 参考代码\n\n```' + document.getElementById('code-language').value + '\n// 在此粘贴代码\n```\n';
        if (content.value.length + text.length > content.maxLength) { status.textContent = '正文已接近长度上限，无法插入模板。'; return; }
        var position = content.selectionEnd;
        content.setRangeText(text,position,position,'end'); setMode('edit'); content.focus(); changed();
    });
    form.querySelectorAll('[data-mode]').forEach(function (button) { button.addEventListener('click', function () { setMode(button.dataset.mode); }); });
    function focusWriting(active) {
        document.body.classList.toggle('ed-focus-writing',active);
        var button = document.getElementById('editor-focus'); button.setAttribute('aria-pressed',String(active)); button.textContent = active ? '退出专注' : '专注写作';
    }
    document.getElementById('editor-focus').addEventListener('click',function () { focusWriting(!document.body.classList.contains('ed-focus-writing')); });
    document.addEventListener('keydown',function (event) { if (event.key === 'Escape') focusWriting(false); });
    form.addEventListener('keydown',function (event) {
        if (!(event.ctrlKey || event.metaKey)) return;
        if (event.key.toLowerCase() === 's') { event.preventDefault(); dirty = true; save(); }
        if (event.key.toLowerCase() === 'b' && event.target === content && format === 'markdown') { event.preventDefault(); insert('bold'); }
    });
    title.addEventListener('input',changed); content.addEventListener('input',changed);
    form.addEventListener('invalid',function (event) { if (event.target === content) setMode('edit'); },true);
    form.addEventListener('submit',function (event) {
        if (submitting) { event.preventDefault(); return; }
        if (!title.value.trim() || !content.value.trim()) {
            event.preventDefault(); var field = title.value.trim() ? content : title;
            setMode('edit'); field.setCustomValidity(field === title ? '请填写题解标题。' : '请填写题解正文。'); field.reportValidity(); return;
        }
        dirty = true; save(); submitting = true; submit.disabled = true; submit.textContent = '正在提交…';
    });
    window.addEventListener('pageshow',function () { submitting = false; submit.disabled = !canSubmit; submit.textContent = '提交审核'; });
    document.addEventListener('visibilitychange',function () { if (document.hidden) save(); });
    window.addEventListener('beforeunload',function (event) { save(); if (!submitting && (dirty || conflict)) { event.preventDefault(); event.returnValue = ''; } });
    var download = document.getElementById('download-draft'); download.hidden = false;
    download.addEventListener('click',function () {
        var blob = new Blob([title.value + '\n\n' + content.value],{type:'text/plain;charset=utf-8'}), url = URL.createObjectURL(blob), link = document.createElement('a');
        link.href = url; link.download = '题解草稿' + (format === 'markdown' ? '.md' : '.txt'); link.click(); setTimeout(function () { URL.revokeObjectURL(url); },1000);
    });
    form.querySelector('.ed-editor-tools').hidden = false;
    counts(); setMode(window.matchMedia('(max-width: 800px)').matches ? 'edit' : 'split');
    if (title.value || content.value) { dirty = true; save(); }
}());
