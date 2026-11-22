'use strict';

const connection = document.querySelector('#pos-connection');
const feedback = document.querySelector('#pos-feedback');
const result = document.querySelector('#pos-result');
const lookup = document.querySelector('#pos-lookup');
const retry = document.querySelector('#pos-retry');
const cart = document.querySelector('#counter-cart');
const operations = [...document.querySelectorAll('.pos-operation')];
const fields = [cart, ...operations].filter(Boolean).map(form => form.querySelector(':scope > fieldset'));
let pending = null;
let busy = false;
let billChanged = false;

function notice(text) {
    feedback.textContent = text;
}

function controls() {
    fields.forEach(fieldset => { fieldset.disabled = !!pending || busy || !navigator.onLine || billChanged; });
    for (const button of [lookup, retry]) {
        button.hidden = !pending;
        button.disabled = busy || !navigator.onLine;
    }
}

function accepted(response) {
    const url = new URL(response.bill_url, window.location.href);
    if (url.origin !== window.location.origin) {
        throw new Error('Unexpected response');
    }
    pending = null;
    billChanged = !cart;
    result.href = url.href;
    result.hidden = false;
    notice(cart ? 'Order confirmed by the server.' : 'Change confirmed by the server. Open the updated bill to continue.');
    if (cart) {
        lines.length = 0;
        cart.reset();
        renderCart();
        controls();
        refreshMenu(1);
    }
}

async function requestWork(checkOnly = false) {
    if (!pending || busy || !navigator.onLine) return;
    busy = true;
    controls();
    const previouslyUnknown = pending.unknown;
    try {
        const url = checkOnly
            ? connection.dataset.status.replace('00000000-0000-4000-8000-000000000000', pending.key)
            : connection.dataset.submit;
        const response = await fetch(url, {
            method: checkOnly ? 'GET' : 'POST',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': connection.dataset.csrf},
            body: checkOnly ? undefined : JSON.stringify(pending.body),
            cache: 'no-store',
            redirect: 'error',
            signal: AbortSignal.timeout(12000),
        });

        if (checkOnly && response.status === 404) {
            notice('The server has no completed record yet. Retry the same request before starting another sale.');
        } else if (response.ok) {
            const data = await response.json();
            if (typeof data.bill_url !== 'string') throw new Error('Missing bill');
            accepted(data);
        } else if (!checkOnly && response.status >= 400 && response.status < 500) {
            const data = await response.json().catch(() => ({}));
            const errors = Object.values(data.errors ?? {}).flat();
            if (!previouslyUnknown) pending = null;
            notice((errors[0] ?? data.message ?? 'The server declined the request.')
                + (previouslyUnknown ? ' The original outcome still needs confirmation. Check the server status.' : ''));
        } else {
            throw new Error('The server did not confirm the request');
        }
    } catch {
        if (!pending) return;
        pending.unknown = true;
        notice('Connection interrupted. The result is unknown. Check server status or retry this same request.');
    } finally {
        busy = false;
        controls();
    }
}

function submit(body) {
    if (pending || busy || !navigator.onLine) return;
    if (!window.crypto?.randomUUID) {
        notice('Open the counter over HTTPS to submit orders.');
        return;
    }
    const key = crypto.randomUUID();
    result.hidden = true;
    pending = {key, body: {...body, key}, unknown: false};
    notice('Waiting for the server…');
    requestWork();
}

lookup.addEventListener('click', () => requestWork(true));
retry.addEventListener('click', () => requestWork());
window.addEventListener('offline', () => {
    notice(pending ? 'Connection lost. The current result needs server confirmation.' : 'Connection lost. Transactions require a live connection.');
    controls();
});
window.addEventListener('online', () => {
    notice(pending ? 'Connection restored. Check server status before starting another sale.' : 'Connection restored.');
    controls();
    if (cart && !pending) refreshMenu();
});
window.addEventListener('beforeunload', event => {
    if (pending || (cart && lines.length)) event.preventDefault();
});

for (const form of operations) {
    form.addEventListener('submit', event => {
        event.preventDefault();
        const values = new FormData(form);
        submit({
            action: form.dataset.action,
            bill_id: Number(form.dataset.bill),
            ...(form.dataset.order ? {order_id: Number(form.dataset.order)} : {}),
            ...(form.dataset.amount ? {paid_amount: form.dataset.amount} : {}),
            method: values.get('method'),
            reference: values.get('reference'),
            reason: values.get('reason'),
            confirmed: values.has('confirmed'),
        });
    });
}

const lines = [];
let menuPage = 1;
let searchTimer;
let menuRequest;
const moneyKeys = ['discount', 'vat', 'sd', 'service'];

function format(minor) {
    return Math.floor(minor / 100) + '.' + String(minor % 100).padStart(2, '0');
}

function minor(value) {
    if (!/^[0-9]{1,9}(?:\.[0-9]{1,2})?$/.test(value)) return NaN;
    const [whole, fraction = ''] = value.split('.');
    return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
}

function calculate() {
    const subtotal = lines.reduce((total, row) => total + row.unit_price_minor * row.quantity, 0);
    const [discount, vat, sd, service] = moneyKeys.map(key => minor(cart.elements[key]?.value.trim() || '0'));
    const total = subtotal - discount + vat + sd + service;
    return {subtotal, discount, charges: vat + sd + service, total,
        valid: Number.isSafeInteger(total) && discount <= subtotal && total >= 0 && total <= 99999999999};
}

function requirePaymentReview() {
    if (cart.elements.confirmed) cart.elements.confirmed.checked = false;
}

function renderTotals() {
    const totals = calculate();
    document.querySelector('#cart-total').value = totals.valid ? format(totals.total) : 'Review amounts';
    document.querySelector('#cart-breakdown').textContent = totals.valid
        ? 'Subtotal ' + format(totals.subtotal) + ' · Discount ' + format(totals.discount) + ' · Charges ' + format(totals.charges)
        : 'Enter amounts with up to two decimal places. Discount cannot exceed subtotal.';
}

function button(text, click) {
    const element = document.createElement('button');
    element.type = 'button';
    element.textContent = text;
    element.addEventListener('click', click);
    return element;
}

function renderCart() {
    const display = document.querySelector('#cart-lines');
    display.replaceChildren();
    if (!lines.length) {
        const empty = document.createElement('p');
        empty.textContent = 'Your cart is empty.';
        display.append(empty);
    }
    for (const line of lines) {
        const row = document.createElement('div');
        row.className = 'cart-row';
        const title = document.createElement('strong');
        title.textContent = line.name + ' · ' + format(line.unit_price_minor);
        const remove = button('Remove', () => {
            lines.splice(lines.indexOf(line), 1);
            requirePaymentReview();
            renderCart();
        });
        remove.setAttribute('aria-label', 'Remove ' + line.name);
        const quantityLabel = document.createElement('label');
        quantityLabel.textContent = 'Quantity';
        const quantity = document.createElement('input');
        quantity.type = 'number';
        quantity.min = '1';
        quantity.max = '99';
        quantity.required = true;
        quantity.value = line.quantity;
        quantity.addEventListener('input', () => {
            line.quantity = quantity.valueAsNumber;
            requirePaymentReview();
            renderTotals();
        });
        quantityLabel.append(quantity);
        const noteLabel = document.createElement('label');
        noteLabel.className = 'cart-note';
        noteLabel.textContent = 'Item note';
        const note = document.createElement('input');
        note.maxLength = 250;
        note.value = line.note;
        note.addEventListener('input', () => { line.note = note.value; });
        noteLabel.append(note);
        row.append(title, remove, quantityLabel, noteLabel);
        display.append(row);
    }
    renderTotals();
}

function addProduct(product) {
    requirePaymentReview();
    const row = lines.find(item => item.product_id === Number(product.id) && !item.note
        && item.unit_price_minor === Number(product.price_minor) && item.quantity < 99);
    if (row) {
        row.quantity += 1;
    } else {
        if (lines.length >= 50) {
            notice('This order has reached its line limit.');
            return;
        }
        lines.push({product_id: Number(product.id), name: product.name, unit_price_minor: Number(product.price_minor), quantity: 1, note: ''});
    }
    renderCart();
}

async function refreshMenu(page = menuPage) {
    if (!navigator.onLine) return;
    menuRequest?.abort();
    const controller = new AbortController();
    menuRequest = controller;
    const url = new URL(cart.dataset.menu);
    url.search = new URLSearchParams({q: cart.elements.search.value, category: cart.elements.category.value, page});
    try {
        const response = await fetch(url, {
            headers: {'Accept': 'application/json'}, cache: 'no-store',
            signal: AbortSignal.any([controller.signal, AbortSignal.timeout(8000)]),
        });
        if (!response.ok) throw new Error('Menu unavailable');
        const {products, categories, more} = await response.json();
        if (controller.signal.aborted) return;
        const category = cart.elements.category;
        const selection = category.value;
        category.replaceChildren(new Option('All categories', ''), ...categories.map(item => new Option(item.name, String(item.id))));
        category.value = categories.some(item => String(item.id) === selection) ? selection : '';
        const grid = document.querySelector('#pos-products');
        if (products.length) {
            grid.replaceChildren(...products.map(product => {
                const select = button(product.name + ' · ' + format(Number(product.price_minor)), () => addProduct(product));
                if (product.description) select.title = product.description;
                return select;
            }));
        } else {
            const none = document.createElement('p');
            none.textContent = 'No products match.';
            grid.replaceChildren(none);
        }
        menuPage = page;
        document.querySelector('#products-previous').hidden = page <= 1;
        document.querySelector('#products-next').hidden = !more;
    } catch {
        if (!controller.signal.aborted && !pending && result.hidden) notice('Menu could not be loaded. Check the connection and refresh the menu.');
    }
}

if (cart) {
    cart.elements.search.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => refreshMenu(1), 140);
    });
    cart.elements.search.addEventListener('keydown', event => {
        if (event.key === 'Enter') {
            event.preventDefault();
            document.querySelector('#pos-products button')?.focus();
        }
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'F2' && !cart.elements.search.disabled && !pending) {
            event.preventDefault();
            cart.elements.search.focus();
        }
    });
    cart.elements.category.addEventListener('change', () => refreshMenu(1));
    document.querySelector('#products-previous').addEventListener('click', () => refreshMenu(menuPage - 1));
    document.querySelector('#products-next').addEventListener('click', () => refreshMenu(menuPage + 1));
    document.querySelector('#products-refresh').addEventListener('click', () => refreshMenu(1));
    for (const key of moneyKeys) cart.elements[key]?.addEventListener('input', () => {
        requirePaymentReview();
        renderTotals();
    });
    for (const key of ['method', 'reference']) cart.elements[key]?.addEventListener('input', requirePaymentReview);
    cart.addEventListener('submit', event => {
        event.preventDefault();
        if (!lines.length) {
            notice('Add a product before sending an order.');
            return;
        }
        const totals = calculate();
        if (!totals.valid) {
            notice('Review discount and charge amounts.');
            return;
        }
        const data = new FormData(cart);
        submit({
            action: 'order', mode: cart.dataset.mode,
            bill_id: cart.dataset.visit ? Number(cart.dataset.visit) : null,
            lines: lines.map(({name, ...line}) => line),
            expected_total: format(totals.total),
            ...(cart.dataset.mode === 'pay_first' ? {paid_amount: format(totals.total)} : {}),
            ...Object.fromEntries(['visit_label', 'collection_token', 'method', 'reference', ...moneyKeys].map(field => [field, data.get(field)])),
            confirmed: data.has('confirmed'),
        });
    });
    renderCart();
    refreshMenu();
}

if (!navigator.onLine) notice('Connection lost. Transactions require a live connection.');
controls();
