/* Shared by preview and permission-checked articles. Never execute author HTML. */
(function () {
    'use strict';
    var escape = function (text) { return String(text).replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); };
    if (!window.marked) return;
    var safeURL = function (href) {
        try { var url = new URL(href, window.location.href); return /^(https?:|mailto:)$/.test(url.protocol) ? url.href : null; }
        catch (e) { return null; }
    };
    var renderer = new marked.Renderer();
    renderer.html = function (html) { return escape(html); };
    renderer.link = function (href, title, text) { var url = safeURL(href); return url ? '<a href="' + escape(url) + '">' + text + '</a>' : text; };
    renderer.image = function (href, title, text) { return renderer.link(href, title, escape(text || '查看图片')); };
    marked.use({renderer:renderer, extensions:[{
        name:'editorialBlockMath', level:'block', start:function (src) { return src.indexOf('$$'); },
        tokenizer:function (src) { var m = /^\$\$\s*\n?([\s\S]+?)\$\$(?:\s*\n|$)/.exec(src); if (m) return {type:'editorialBlockMath',raw:m[0],text:m[1]}; },
        renderer:function (token) { return '<p><span data-ed-math="block">' + escape(token.text) + '</span></p>'; }
    }, {
        name:'editorialInlineMath', level:'inline', start:function (src) { return src.indexOf('$'); },
        tokenizer:function (src) { var m = /^\$([^\n$]+?)\$/.exec(src); if (m) return {type:'editorialInlineMath',raw:m[0],text:m[1]}; },
        renderer:function (token) { return '<span data-ed-math="inline">' + escape(token.text) + '</span>'; }
    }]});
    var allowed = new Set(['P','H1','H2','H3','H4','H5','H6','UL','OL','LI','PRE','CODE','STRONG','EM','DEL','BLOCKQUOTE','HR','BR','A','TABLE','THEAD','TBODY','TR','TH','TD','SPAN']);
    var modes = {cpp:'c_cpp',c:'c_cpp',cxx:'c_cpp','c++':'c_cpp',python:'python',py:'python',java:'java',javascript:'javascript',js:'javascript'};
    var loadedModes = {};
    function highlight(code, source, language) {
        if (!window.ace || !modes[language]) return;
        var mode = modes[language];
        ace.config.set('basePath', 'ace');
        if (!loadedModes[mode]) loadedModes[mode] = new Promise(function (resolve) { ace.config.loadModule('ace/mode/' + mode, resolve); });
        loadedModes[mode].then(function (module) {
            if (!code.isConnected || !module) return;
            var tokenizer = new module.Mode().getTokenizer(), state = 'start', fragment = document.createDocumentFragment();
            source.split('\n').forEach(function (line, index) {
                if (index) fragment.appendChild(document.createTextNode('\n'));
                var result = tokenizer.getLineTokens(line, state); state = result.state;
                result.tokens.forEach(function (token) {
                    var span = document.createElement('span');
                    span.className = 'ed-token-' + token.type.split('.')[0]; span.textContent = token.value; fragment.appendChild(span);
                });
            });
            code.replaceChildren(fragment);
        }).catch(function () { /* Plain code remains readable if highlighting is unavailable. */ });
    }
    function render(source, target) {
        try {
            var parsed = new DOMParser().parseFromString(marked.parse(source, {gfm:true,breaks:true,headerIds:false,mangle:false}), 'text/html');
            Array.from(parsed.body.querySelectorAll('*')).forEach(function (el) {
                if (!allowed.has(el.tagName)) { el.replaceWith(document.createTextNode(el.textContent)); return; }
                var href = el.tagName === 'A' && safeURL(el.getAttribute('href'));
                var language = el.tagName === 'CODE' && (el.getAttribute('class') || '').match(/^language-([\w+\-]+)$/);
                var math = el.tagName === 'SPAN' && el.getAttribute('data-ed-math');
                Array.from(el.attributes).forEach(function (attr) { el.removeAttribute(attr.name); });
                if (href) { el.setAttribute('href',href); el.setAttribute('rel','nofollow noopener noreferrer'); }
                if (language) el.dataset.language = language[1].toLowerCase();
                if (math === 'inline' || math === 'block') el.dataset.edMath = math;
            });
            target.replaceChildren.apply(target, Array.from(parsed.body.childNodes));
            target.querySelectorAll('[data-ed-math]').forEach(function (el) {
                if (!window.katex) return;
                katex.render(el.textContent, el, {displayMode:el.dataset.edMath === 'block',throwOnError:false,trust:false,maxExpand:1000,maxSize:20,strict:'ignore'});
            });
            target.querySelectorAll('pre > code').forEach(function (code) {
                var sourceCode = code.textContent, pre = code.parentElement;
                var shell = document.createElement('div'), bar = document.createElement('div'), label = document.createElement('span'), copy = document.createElement('button');
                shell.className = 'ed-code-shell'; bar.className = 'ed-code-bar'; label.textContent = code.dataset.language || '代码';
                copy.type = 'button'; copy.textContent = '复制代码'; copy.setAttribute('aria-label','复制代码');
                copy.addEventListener('click', function () {
                    if (!navigator.clipboard) { copy.textContent = '请选中代码复制'; return; }
                    navigator.clipboard.writeText(sourceCode).then(function () { copy.textContent = '已复制'; },function () { copy.textContent = '请选中代码复制'; });
                });
                pre.replaceWith(shell); bar.append(label,copy); shell.append(bar,pre);
                highlight(code, sourceCode, code.dataset.language);
            });
        } catch (e) { target.textContent = source; target.classList.add('ed-render-fallback'); }
    }
    window.editorialMarkdown = render;
    document.querySelectorAll('.ed-body[data-content-format="markdown"]').forEach(function (body) {
        body.editorialSource = body.textContent;
        body.classList.add('ed-markdown'); render(body.editorialSource, body);
    });
}());
