import { mount } from '../src/app.js';

const log = document.querySelector('[data-log]');
window.svszEvents = [];
window.svsz = mount(document.querySelector('[data-svsz-root]'), {
    onEvent(name, params) {
        window.svszEvents.push({ name, params });
        const li = document.createElement('li');
        li.textContent = `${name} ${JSON.stringify(params)}`;
        log.appendChild(li);
    },
});
