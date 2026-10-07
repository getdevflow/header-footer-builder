(() => {
    'use strict';
    const dataNode = document.getElementById('hfb-admin-data');
    if (!dataNode) return;
    const data = JSON.parse(dataNode.textContent), items = data.items;
    const host = document.getElementById('hfb-menu-items');
    const defaultItem = () => ({label: 'New item', url: '#', target: '_self', template: '', trigger: 'hover', width_mode: 'default', width: '600px', mobile_width: '100%', align: 'left', ajax: false, children: []});
    function button(text, action) {
        const node = document.createElement('button'); node.type = 'button'; node.className = 'btn btn-default btn-sm';
        node.textContent = text; node.addEventListener('click', action); return node;
    }
    function field(item, key, text, choices, onChange) {
        const label = document.createElement('label'); label.textContent = text;
        const input = document.createElement(choices ? 'select' : 'input'); input.className = 'form-control';
        if (choices) choices.forEach(([value, name]) => {
            const option = document.createElement('option'); option.value = value; option.textContent = name; input.append(option);
        });
        else input.type = 'text';
        input.value = String(item[key] ?? '');
        if (choices && input.value === '') item[key] = '';
        if (key === 'label') { input.required = true; input.maxLength = 191; }
        if (['width', 'mobile_width'].includes(key)) input.pattern = '\\d+(?:\\.\\d+)?(?:px|%|vw|rem)';
        input.addEventListener('input', () => { item[key] = input.value; });
        input.addEventListener('change', () => { item[key] = input.value; onChange?.(); });
        label.append(input); return label;
    }
    function renderList(list, container, depth = 0) {
        list.forEach((item, index) => {
            item.children ||= [];
            const row = document.createElement('div'); row.className = 'hfb-menu-row';
            const fields = document.createElement('div'); fields.className = 'hfb-fields';
            fields.append(field(item, 'label', 'Label'), field(item, 'url', 'URL'),
                field(item, 'target', 'Open in', [['_self', 'Same window'], ['_blank', 'New window']]));
            row.append(fields);
            const actions = document.createElement('div'); actions.className = 'hfb-item-actions';
            const moveUp = button('↑ Move up', () => { [list[index - 1], list[index]] = [list[index], list[index - 1]]; render(); });
            moveUp.disabled = index === 0;
            const moveDown = button('↓ Move down', () => { [list[index], list[index + 1]] = [list[index + 1], list[index]]; render(); });
            moveDown.disabled = index === list.length - 1;
            actions.append(moveUp, moveDown,
                button('Add child', () => { item.children.push(defaultItem()); render(); }),
                button('Remove', () => { list.splice(index, 1); render(); }));
            row.append(actions);
            if (depth === 0) {
                const mega = document.createElement('div'); mega.className = 'hfb-fields hfb-mega-fields';
                mega.append(field(item, 'template', 'Mega menu template', [['', 'Use ordinary dropdown'], ...data.templates.map(t => [t.id, t.name])]),
                    field(item, 'trigger', 'Open on', [['hover', 'Hover'], ['click', 'Click']]),
                    field(item, 'width_mode', 'Panel width', [['default', 'Default'], ['full', 'Full viewport'], ['custom', 'Custom']]),
                    field(item, 'width', 'Custom width'), field(item, 'mobile_width', 'Mobile width'),
                    field(item, 'align', 'Screen alignment', [['left', 'Left'], ['center', 'Center'], ['right', 'Right']]));
                const ajaxLabel = document.createElement('label'); ajaxLabel.textContent = 'Load mega content on demand';
                const ajax = document.createElement('input'); ajax.type = 'checkbox'; ajax.checked = !!item.ajax;
                ajax.addEventListener('change', () => { item.ajax = ajax.checked; }); ajaxLabel.append(ajax); mega.append(ajaxLabel);
                row.append(mega);
            }
            if (item.children.length) {
                const children = document.createElement('div'); children.className = 'hfb-children';
                renderList(item.children, children, depth + 1); row.append(children);
            }
            container.append(row);
        });
    }
    function render() { host.replaceChildren(); renderList(items, host); }
    document.getElementById('hfb-add-item')?.addEventListener('click', () => { items.push(defaultItem()); render(); });
    document.getElementById('hfb-menu-form')?.addEventListener('submit', () => {
        document.getElementById('hfb-items-json').value = JSON.stringify(items);
    });
    document.querySelectorAll('.hfb-delete-form').forEach(form => form.addEventListener('submit', event => {
        if (!window.confirm('Delete this item? This cannot be undone.')) event.preventDefault();
    }));
    const kind = document.getElementById('hfb-kind'), layout = document.getElementById('hfb-layout');
    kind?.addEventListener('change', () => {
        if (!layout) return;
        layout.replaceChildren();
        Object.entries(data.layouts[kind.value]).forEach(([value, name]) => {
            const option = document.createElement('option'); option.value = value; option.textContent = name; layout.append(option);
        });
    });
    render();
})();
