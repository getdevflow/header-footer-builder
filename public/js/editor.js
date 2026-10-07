(() => {
    'use strict';
    window.addEventListener('vihzhuo:grapesjs:ready', event => {
        const editor = event.detail || window.editor;
        if (!editor || editor.__hfbInstalled) return;
        editor.__hfbInstalled = true;
        const sm = editor.StyleManager;
        const enableStructures = () => {
            const visit = component => {
                if (component.get('tagName') === 'section' || ['container', 'hfb-section'].includes(component.get('block-slug'))) {
                    component.set({selectable: true, stylable: true, hoverable: true, highlightable: true});
                    if (component.get('tagName') === 'section' || component.get('block-slug') === 'hfb-section') {
                        component.set('droppable', true);
                    }
                    if (!component.get('style-identifier')) {
                        const bytes = crypto.getRandomValues(new Uint8Array(10));
                        const id = 'IDhfb' + Array.from(bytes, value => value.toString(16).padStart(2, '0')).join('');
                        component.set('style-identifier', id); component.addClass(id);
                    }
                }
                component.components().forEach(visit);
            };
            visit(editor.getWrapper());
        };
        enableStructures();
        window.jQuery?.(window).on('pagebuilder-page-loaded.hfb', () => requestAnimationFrame(enableStructures));
        editor.on('block:drag:stop', () => requestAnimationFrame(enableStructures));
        let structuresPending = false;
        editor.on('component:add', () => {
            if (structuresPending) return;
            structuresPending = true;
            requestAnimationFrame(() => { structuresPending = false; enableStructures(); });
        });
        const sector = (id, name, properties) => sm.addSector(id, {name, open: false, properties});
        if (!sm.getSector('typography')) sector('hfb-typography', 'Typography', [
            'font-family', 'font-size', 'font-weight', 'line-height', 'letter-spacing', 'color', 'text-align', 'text-decoration', 'text-transform'
        ]);
        sector('hfb-decoration', 'Border & Shadow', ['border', 'border-radius', 'box-shadow', 'opacity']);
        sector('hfb-layout', 'Layout & Spacing', ['display', 'flex-direction', 'justify-content', 'align-items', 'gap', 'padding', 'margin', 'max-width']);
        sector('hfb-navigation-style', 'Navigation Styling', [
            ['--hfb-link-color', 'Item color', 'color'], ['--hfb-link-background', 'Item background', 'color'],
            ['--hfb-hover-color', 'Hover color', 'color'], ['--hfb-hover-background', 'Hover background', 'color'],
            ['--hfb-active-color', 'Active color', 'color'], ['--hfb-active-background', 'Active background', 'color'],
            ['--hfb-dropdown-color', 'Dropdown color', 'color'], ['--hfb-panel-background', 'Panel background', 'color'],
            ['--hfb-item-padding', 'Item padding'], ['--hfb-menu-gap', 'Item gap'],
            ['--hfb-item-border', 'Item border'], ['--hfb-item-radius', 'Item radius'], ['--hfb-divider', 'Divider'],
            ['--hfb-panel-border', 'Panel border'], ['--hfb-panel-radius', 'Panel radius'],
            ['--hfb-panel-shadow', 'Panel shadow'], ['--hfb-panel-padding', 'Panel padding'],
        ].map(([property, name, type = 'base']) => ({property, name, type})));
        sector('hfb-button-style', 'Icon Button Styling', [
            ['--hfb-button-color', 'Text color', 'color'], ['--hfb-button-background', 'Background', 'color'],
            ['--hfb-button-hover-color', 'Hover text', 'color'], ['--hfb-button-hover-background', 'Hover background', 'color'],
            ['--hfb-button-padding', 'Padding'], ['--hfb-button-border', 'Border'], ['--hfb-button-radius', 'Radius'],
            ['--hfb-top-background', 'Top bar background', 'color'],
        ].map(([property, name, type = 'base']) => ({property, name, type})));
        // Persist effects as CSS custom properties, including on PHP containers whose
        // arbitrary wrapper attributes are not serialized by Vihzhuo.
        if (window.HfbTemplateKind === 'header') {
            sm.addType('hfb-effects-panel', {
                create() {
                    const host = document.createElement('div');
                    host.style.cssText = 'padding:10px;display:grid;gap:8px';
                    const title = document.createElement('strong'); title.textContent = 'Header Effects'; host.append(title);
                    const hint = document.createElement('p');
                    hint.textContent = 'Select a Section or Container. Effects apply to the selected element.';
                    host.append(hint);
                    const fields = [
                        ['--hfb-effects', 'Enable effects', 'select', [['0', 'Off'], ['1', 'On']], '0'],
                        ['--hfb-sticky', 'Sticky header', 'select', [['0', 'Off'], ['1', 'On']], '0'],
                        ['--hfb-transparent', 'Transparent before scroll', 'select', [['0', 'Off'], ['1', 'On']], '0'],
                        ['--hfb-scroll-distance', 'Scroll distance (px)', 'number', null, '80'],
                        ['--hfb-solid-color', 'Solid background', 'color', null, '#ffffff'],
                        ['--hfb-shadow', 'Bottom shadow', 'select', [['0', 'Off'], ['1', 'On']], '0'],
                        ['--hfb-animation', 'Scroll animation', 'select', [['none', 'None'], ['slide', 'Slide down'], ['fade', 'Fade in']], 'none'],
                        ['--hfb-duration', 'Transition duration (ms)', 'number', null, '300'],
                        ['--hfb-z-index', 'Stack order', 'number', null, '10000'],
                    ];
                    const inputs = [];
                    const allowed = component => {
                        if (!component) return false;
                        const attrs = component.getAttributes();
                        const classes = (attrs.class || '').split(/\s+/);
                        return component.get('tagName') === 'section' || attrs['block-slug'] === 'container' || component.get('block-slug') === 'container'
                            || classes.some(value => value === 'hfb-layout' || /^container(?:-|$)/.test(value));
                    };
                    for (const [property, labelText, type, options, fallback] of fields) {
                        const label = document.createElement('label'); label.textContent = labelText;
                        label.style.cssText = 'display:grid;gap:4px';
                        const input = document.createElement(type === 'select' ? 'select' : 'input');
                        if (type === 'select') for (const [value, text] of options) {
                            const option = document.createElement('option'); option.value = value; option.textContent = text; input.append(option);
                        } else { input.type = type; if (type === 'number') { input.min = '0'; input.max = property === '--hfb-z-index' ? '2147483647' : '10000'; } }
                        input.style.cssText = 'width:100%;padding:5px;color:#111;background:#fff';
                        label.append(input); host.append(label);
                        inputs.push({input, property, fallback});
                        input.addEventListener('change', () => {
                            const selected = editor.getSelected(); if (!allowed(selected)) return;
                            let value = input.value;
                            if (type === 'number') value = String(Math.max(0, Math.min(Number(input.max), Number(value) || 0)));
                            if (property === '--hfb-duration') value += 'ms';
                            const selector = '.' + selected.get('style-identifier');
                            const rule = editor.CssComposer.getRule(selector);
                            editor.CssComposer.setRule(selector, {...(rule?.getStyle() || {}), [property]: value});
                            sync();
                            editor.Canvas.getWindow()?.HfbFrontend?.refresh();
                        });
                    }
                    const sync = () => {
                        const selected = editor.getSelected(), enabled = allowed(selected);
                        hint.hidden = enabled;
                        const rule = selected?.get('style-identifier')
                            ? editor.CssComposer.getRule('.' + selected.get('style-identifier')) : null;
                        const style = {...(selected?.getStyle() || {}), ...(rule?.getStyle() || {})};
                        for (const {input, property, fallback} of inputs) {
                            input.disabled = !enabled;
                            input.value = String(style[property] ?? fallback).replace(property === '--hfb-duration' ? /ms$/ : /$^/, '');
                        }
                    };
                    editor.on('component:selected component:deselected component:styleUpdate', sync);
                    this.__cleanup = () => editor.off('component:selected component:deselected component:styleUpdate', sync);
                    sync(); return host;
                },
                emit() {}, update() {}, destroy() { this.__cleanup?.(); },
            });
            sm.addProperty('advanced', {property: '--hfb-panel-host', type: 'hfb-effects-panel', name: '', full: true});
        }
        const frameRefresh = () => editor.Canvas.getWindow()?.HfbFrontend?.refresh();
        editor.on('component:styleUpdate component:add canvas:frame:load', frameRefresh);
        // Conditions remain editable from the library while the design stays open.
        const conditions = document.createElement('a');
        const templateId = new URLSearchParams(window.location.search).get('page') || '';
        conditions.href = `../?template=${encodeURIComponent(templateId)}`;
        conditions.target = '_blank'; conditions.rel = 'noopener'; conditions.className = 'btn';
        conditions.textContent = 'Display conditions';
        document.querySelector('#sidebar-bottom-buttons')?.append(conditions);
    });
})();
