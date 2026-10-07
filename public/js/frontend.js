(() => {
    'use strict';
    if (window.HfbFrontend) { window.HfbFrontend.refresh(); return; }
    const menus = new Set(), effects = new Map();
    let scheduled = false;
    const directPanel = item => Array.from(item.children).find(child => child.classList.contains('hfb-panel'));
    const directToggle = item => Array.from(item.children).find(child => child.classList.contains('hfb-panel-toggle'));
    function close(item) {
        item.classList.remove('is-open');
        const panel = directPanel(item), toggle = directToggle(item);
        if (panel) { panel.hidden = true; panel.querySelectorAll('.hfb-item.is-open').forEach(close); }
        toggle?.setAttribute('aria-expanded', 'false');
    }
    function positionPanel(item) {
        const panel = directPanel(item), nav = item.closest('[data-hfb-nav]');
        if (!panel || nav.classList.contains('hfb-mobile') || panel.dataset.widthMode === 'default') {
            if (panel) { panel.style.removeProperty('left'); panel.style.removeProperty('width'); }
            return;
        }
        const rect = item.getBoundingClientRect();
        panel.style.maxWidth = panel.dataset.widthMode === 'full' ? '100vw' : '';
        panel.style.width = panel.dataset.widthMode === 'full' ? `${document.documentElement.clientWidth}px` : 'var(--hfb-panel-width)';
        const width = Math.min(panel.getBoundingClientRect().width, document.documentElement.clientWidth);
        const screenLeft = panel.dataset.widthMode === 'full' ? 0 : panel.dataset.align === 'right' ? document.documentElement.clientWidth - width - 8
            : panel.dataset.align === 'center' ? (document.documentElement.clientWidth - width) / 2 : 8;
        panel.style.left = `${screenLeft - rect.left}px`;
    }
    async function load(panel) {
        if (!panel.dataset.hfbUrl || panel.dataset.loaded === '1' || panel.dataset.loading === '1') return;
        const url = new URL(panel.dataset.hfbUrl, window.location.href);
        if (url.origin !== window.location.origin) return;
        panel.dataset.loading = '1';
        panel.setAttribute('aria-busy', 'true');
        try {
            const response = await fetch(url, {credentials: 'same-origin', headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error('Menu unavailable');
            const data = await response.json();
            if (typeof data.html !== 'string') throw new Error('Invalid menu');
            const template = document.createElement('template');
            template.innerHTML = data.html;
            const scripts = Array.from(template.content.querySelectorAll('script'));
            scripts.forEach(script => script.remove());
            panel.replaceChildren(template.content);
            // Run the scripts belonging to trusted, authored Vihzhuo blocks in order.
            for (const original of scripts) {
                if (original.hasAttribute('data-hfb-asset')) continue;
                const script = document.createElement('script');
                Array.from(original.attributes).forEach(attribute => script.setAttribute(attribute.name, attribute.value));
                script.textContent = original.textContent;
                script.async = false;
                if (script.src) await new Promise(resolve => {script.onload = resolve; script.onerror = resolve; panel.append(script);});
                else panel.append(script);
                script.dispatchEvent(new Event('run-script'));
            }
            panel.querySelectorAll('[data-bs-ride="carousel"]').forEach(element => window.bootstrap?.Carousel?.getOrCreateInstance(element));
            panel.dataset.loaded = '1';
            refresh();
        } catch {
            const status = document.createElement('p');
            status.setAttribute('role', 'status');
            status.textContent = 'Unable to load menu. ';
            const retry = document.createElement('button');
            retry.type = 'button'; retry.textContent = 'Retry';
            retry.addEventListener('click', () => load(panel));
            status.append(retry); panel.replaceChildren(status);
        } finally {
            delete panel.dataset.loading;
            panel.removeAttribute('aria-busy');
            positionPanel(panel.parentElement);
        }
    }
    function open(item) {
        const panel = directPanel(item);
        if (!panel) return;
        Array.from(item.parentElement.children).filter(sibling => sibling !== item).forEach(close);
        panel.hidden = false; item.classList.add('is-open');
        directToggle(item)?.setAttribute('aria-expanded', 'true');
        positionPanel(item); load(panel);
    }
    function initMenu(nav) {
        if (menus.has(nav)) return;
        menus.add(nav);
        nav.querySelector(':scope > .hfb-hamburger')?.addEventListener('click', event => {
            const expanded = nav.classList.toggle('is-menu-open');
            event.currentTarget.setAttribute('aria-expanded', String(expanded));
            if (!expanded) nav.querySelectorAll('.hfb-item.is-open').forEach(close);
            resize();
        });
        nav.addEventListener('click', event => {
            if (event.target.closest('[data-hfb-nav]') !== nav) return;
            const toggle = event.target.closest('.hfb-panel-toggle');
            const link = event.target.closest('.hfb-link');
            const item = (toggle || link)?.closest('.hfb-item');
            if (!item || !directPanel(item)) return;
            if (toggle || item.dataset.trigger === 'click' || nav.classList.contains('hfb-mobile')) {
                event.preventDefault();
                item.classList.contains('is-open') ? close(item) : open(item);
            }
        });
        nav.addEventListener('keydown', event => {
            if (event.target.closest('[data-hfb-nav]') !== nav) return;
            const item = event.target.closest('.hfb-item');
            if (event.key === 'Escape') {
                const owner = item?.closest('.hfb-panel')?.parentElement || item;
                if (owner) { close(owner); directToggle(owner)?.focus(); }
                else { nav.classList.remove('is-menu-open'); nav.querySelector('.hfb-hamburger')?.focus(); }
                event.preventDefault();
            } else if (event.key === 'ArrowDown' && item && directPanel(item)) {
                open(item); directPanel(item).querySelector('a,button')?.focus(); event.preventDefault();
            }
        });
    }
    function initItems() {
        document.querySelectorAll('[data-hfb-nav] .hfb-item').forEach(item => {
            if (item.__hfbBound) return;
            item.__hfbBound = true;
            let timer;
            item.addEventListener('mouseenter', () => {
                clearTimeout(timer);
                if (item.dataset.trigger !== 'click' && !item.closest('[data-hfb-nav]').classList.contains('hfb-mobile')) open(item);
            });
            item.addEventListener('mouseleave', () => {
                if (item.dataset.trigger !== 'click' && !item.closest('[data-hfb-nav]').classList.contains('hfb-mobile')) {
                    timer = setTimeout(() => { if (!item.contains(document.activeElement)) close(item); }, 160);
                }
            });
            item.addEventListener('focusout', () => setTimeout(() => {
                if (!item.contains(document.activeElement) && !item.matches(':hover')) close(item);
            }, 0));
        });
    }
    const cssNumber = (style, property, fallback) => {
        const value = parseFloat(style.getPropertyValue(property));
        return Number.isFinite(value) ? value : fallback;
    };
    function updateEffects() {
        for (const [element, state] of effects) {
            if (!element.isConnected) { state.spacer?.remove(); effects.delete(element); continue; }
            const style = getComputedStyle(element);
            if (cssNumber(style, '--hfb-effects', 0) !== 1) {
                state.spacer?.remove();
                element.classList.remove('hfb-effects', 'hfb-transparent', 'hfb-shadow', 'is-scrolled', 'is-sticky', 'hfb-animate-slide', 'hfb-animate-fade');
                element.style.removeProperty('left'); element.style.removeProperty('width');
                effects.delete(element); continue;
            }
            const sticky = cssNumber(style, '--hfb-sticky', 0) === 1;
            const transparent = cssNumber(style, '--hfb-transparent', 0) === 1;
            element.classList.toggle('hfb-transparent', transparent);
            element.classList.toggle('hfb-shadow', cssNumber(style, '--hfb-shadow', 0) === 1);
            element.classList.toggle('is-scrolled', window.scrollY >= Math.max(0, cssNumber(style, '--hfb-scroll-distance', 80)));
            const animation = style.getPropertyValue('--hfb-animation').trim();
            element.classList.toggle('hfb-animate-slide', animation === 'slide');
            element.classList.toggle('hfb-animate-fade', animation === 'fade');
            if (sticky) {
                if (!state.spacer) {
                    state.spacer = document.createElement('div');
                    state.spacer.setAttribute('aria-hidden', 'true');
                    state.spacer.className = 'hfb-spacer';
                    element.before(state.spacer);
                }
                state.spacer.style.height = transparent ? '0px' : `${element.getBoundingClientRect().height}px`;
                element.classList.add('is-sticky');
                const rect = state.spacer.getBoundingClientRect();
                element.style.left = `${rect.left}px`; element.style.width = `${rect.width}px`;
            } else if (state.spacer) {
                state.spacer.remove(); state.spacer = null;
                element.classList.remove('is-sticky'); element.style.removeProperty('left'); element.style.removeProperty('width');
            }
        }
    }
    function refresh() {
        document.querySelectorAll('[data-hfb-nav]').forEach(initMenu);
        initItems();
        document.querySelectorAll('section,header,div[class],div[style],phpb-block').forEach(element => {
            if (effects.has(element) || element.classList.contains('hfb-spacer')) return;
            if (cssNumber(getComputedStyle(element), '--hfb-effects', 0) !== 1) return;
            // Custom properties inherit; only the element owning the effect should run it.
            if (element.parentElement && cssNumber(getComputedStyle(element.parentElement), '--hfb-effects', 0) === 1) return;
            element.classList.add('hfb-effects'); effects.set(element, {spacer: null});
        });
        resize();
    }
    function resize() {
        for (const nav of menus) {
            if (!nav.isConnected) { menus.delete(nav); continue; }
            const mobile = window.innerWidth <= Number(nav.dataset.breakpoint || 768);
            nav.classList.toggle('hfb-mobile', mobile);
            if (!mobile) { nav.classList.remove('is-menu-open'); nav.querySelector('.hfb-hamburger')?.setAttribute('aria-expanded', 'false'); }
            const list = nav.querySelector(':scope > .hfb-menu');
            if (list && mobile) {
                list.style.left = `${-nav.getBoundingClientRect().left}px`; list.style.right = 'auto';
                list.style.width = `${document.documentElement.clientWidth}px`;
            } else if (list) {
                list.style.removeProperty('left'); list.style.removeProperty('right'); list.style.removeProperty('width');
            }
            nav.querySelectorAll('.hfb-item.is-open').forEach(positionPanel);
        }
        updateEffects();
    }
    function schedule() {
        if (scheduled) return;
        scheduled = true;
        requestAnimationFrame(() => { scheduled = false; refresh(); });
    }
    window.HfbFrontend = {refresh};
    document.addEventListener('click', event => {
        for (const nav of menus) if (!nav.contains(event.target)) {
            nav.querySelectorAll('.hfb-item.is-open').forEach(close);
            nav.classList.remove('is-menu-open'); nav.querySelector('.hfb-hamburger')?.setAttribute('aria-expanded', 'false');
        }
    });
    window.addEventListener('resize', schedule);
    window.addEventListener('scroll', () => requestAnimationFrame(updateEffects), {passive: true});
    function start() {
        refresh();
        new MutationObserver(records => {
            if (records.some(record => record.type === 'childList' && Array.from(record.addedNodes).some(node => node.nodeType === 1 && !node.classList.contains('hfb-spacer')))) schedule();
        }).observe(document.body, {childList: true, subtree: true});
    }
    document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', start, {once: true}) : start();
})();
