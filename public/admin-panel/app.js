(function () {
  const API = '/api';

  const state = {
    token: localStorage.getItem('admin_token'),
    user: readUser(),
    route: 'dashboard',
    users: { role: '', search: '', page: 1 },
    products: { filter: '', search: '', page: 1, editingId: null },
    orders: { status: '', page: 1, openId: null },
    reports: { loaded: false },
  };

  const orderTransitions = {
    pending: ['paid', 'cancelled'],
    paid: ['shipped', 'cancelled'],
    shipped: [],
    cancelled: [],
  };

  const el = (id) => document.getElementById(id);

  const money = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' });

  function readUser() {
    try {
      return JSON.parse(localStorage.getItem('admin_user'));
    } catch (e) {
      return null;
    }
  }

  function saveSession(token, user) {
    state.token = token;
    state.user = user;
    localStorage.setItem('admin_token', token);
    localStorage.setItem('admin_user', JSON.stringify(user));
  }

  function clearSession() {
    state.token = null;
    state.user = null;
    localStorage.removeItem('admin_token');
    localStorage.removeItem('admin_user');
  }

  async function request(method, path, body, token) {
    const authToken = token || state.token;
    const headers = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };

    if (authToken) {
      headers['Authorization'] = 'Bearer ' + authToken;
    }

    const res = await fetch(API + path, {
      method: method,
      headers: headers,
      body: body ? JSON.stringify(body) : undefined,
    });

    const data = await res.json().catch(() => ({}));

    if (res.status === 401 && state.token && !token) {
      clearSession();
      showLogin();
    }

    if (!res.ok) {
      const error = new Error(firstError(data) || 'Something went wrong');
      error.status = res.status;
      error.errors = data.errors || null;
      throw error;
    }

    return data;
  }

  function firstError(data) {
    if (data.errors) {
      const key = Object.keys(data.errors)[0];
      if (key && data.errors[key][0]) {
        return data.errors[key][0];
      }
    }
    return data.message;
  }

  let toastTimer;
  function toast(message, isError) {
    const box = el('toast');
    box.textContent = message;
    box.classList.toggle('toast--error', !!isError);
    box.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { box.hidden = true; }, 3000);
  }

  function formatDate(value) {
    if (!value) return '–';
    const date = new Date(value.replace(' ', 'T'));
    if (isNaN(date)) return value;
    return date.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
  }

  function make(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function emptyRow(body, colspan, text) {
    const row = make('tr');
    const cell = make('td', 'empty', text);
    cell.colSpan = colspan;
    row.appendChild(cell);
    body.appendChild(row);
  }

  function setupSegmented(groupId, onChange) {
    const buttons = document.querySelectorAll('#' + groupId + ' .segmented__btn');
    buttons.forEach((btn) => {
      btn.addEventListener('click', () => {
        buttons.forEach((b) => {
          const active = b === btn;
          b.classList.toggle('is-active', active);
          b.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        onChange(btn.dataset.value);
      });
    });
  }

  function debounce(fn, wait) {
    let timer;
    return function (event) {
      clearTimeout(timer);
      timer = setTimeout(() => fn(event), wait);
    };
  }

  function showLogin() {
    el('app-view').hidden = true;
    el('login-view').hidden = false;
    el('email').focus();
  }

  function showApp() {
    el('login-view').hidden = true;
    el('app-view').hidden = false;
    el('me-name').textContent = state.user.name;
    el('me-email').textContent = state.user.email;
    el('me-avatar').textContent = state.user.name.charAt(0).toUpperCase();
    applyRoute();
  }

  function applyRoute() {
    const hash = location.hash.replace('#/', '');
    const route = ['dashboard', 'users', 'products', 'orders', 'reports'].includes(hash) ? hash : 'dashboard';
    const view = ['products', 'orders', 'reports'].includes(route) ? route : 'dashboard';

    el('view-dashboard').hidden = view !== 'dashboard';
    el('view-products').hidden = view !== 'products';
    el('view-orders').hidden = view !== 'orders';
    el('view-reports').hidden = view !== 'reports';

    document.querySelectorAll('.nav__item[data-route]').forEach((link) => {
      link.classList.toggle('is-active', link.dataset.route === route);
    });

    if (view === 'products') {
      loadProducts();
    } else if (view === 'orders') {
      loadOrders();
    } else if (view === 'reports') {
      if (!state.reports.loaded) {
        setReportRange(30);
        state.reports.loaded = true;
      }
      loadReport();
    } else {
      loadStats();
      loadUsers();
      if (route === 'users') {
        el('users').scrollIntoView({ behavior: 'smooth' });
      }
    }

    state.route = route;
  }

  async function handleLogin(event) {
    event.preventDefault();

    const errorBox = el('login-error');
    const button = el('login-btn');
    const email = el('email').value.trim();
    const password = el('password').value;

    errorBox.hidden = true;

    if (!email || !password) {
      errorBox.textContent = 'Please enter your email and password.';
      errorBox.hidden = false;
      return;
    }

    button.disabled = true;
    button.textContent = 'Signing in…';

    try {
      const res = await request('POST', '/login', { email: email, password: password });
      const user = res.data.user;
      const token = res.data.token;

      if (user.role !== 'admin') {
        await request('POST', '/logout', null, token).catch(() => {});
        throw new Error('Only admin accounts can sign in here.');
      }

      saveSession(token, user);
      el('login-form').reset();
      showApp();
    } catch (err) {
      errorBox.textContent = err.message;
      errorBox.hidden = false;
    } finally {
      button.disabled = false;
      button.textContent = 'Sign in';
    }
  }

  async function handleLogout() {
    try {
      await request('POST', '/logout');
    } catch (e) {
    }
    clearSession();
    showLogin();
  }

  async function loadStats() {
    try {
      const res = await request('GET', '/admin/dashboard');
      const stats = res.data.stats;
      el('stat-total').textContent = stats.total;
      el('stat-customers').textContent = stats.customers;
      el('stat-admins').textContent = stats.admins;
      el('stat-week').textContent = stats.this_week;
    } catch (err) {
      if (err.status !== 401) toast(err.message, true);
    }
  }

  async function loadUsers() {
    const params = new URLSearchParams({ page: state.users.page });
    if (state.users.role) params.set('role', state.users.role);
    if (state.users.search) params.set('search', state.users.search);

    el('page-info').textContent = 'Loading…';

    try {
      const res = await request('GET', '/admin/users?' + params.toString());
      renderUsers(res.data.data);
      renderPager(res.data.meta, 'page-info', 'prev-btn', 'next-btn', 'users');
    } catch (err) {
      if (err.status !== 401) toast(err.message, true);
      el('page-info').textContent = '';
    }
  }

  function renderUsers(users) {
    const body = el('users-body');
    body.replaceChildren();

    if (!users.length) {
      emptyRow(body, 5, 'No users found.');
      return;
    }

    users.forEach((user) => {
      const row = make('tr');

      const nameCell = make('td');
      const wrap = make('div', 'user-cell');
      wrap.append(make('div', 'avatar', user.name.charAt(0).toUpperCase()), make('span', '', user.name));
      nameCell.appendChild(wrap);

      const roleCell = make('td');
      roleCell.appendChild(make('span', 'badge badge--' + user.role, user.role));

      const actionCell = make('td', 'right');
      if (user.role === 'customer') {
        const del = make('button', 'btn btn--danger', 'Delete');
        del.type = 'button';
        del.setAttribute('aria-label', 'Delete ' + user.name);
        del.addEventListener('click', () => deleteUser(user));
        actionCell.appendChild(del);
      } else {
        actionCell.textContent = '–';
      }

      row.append(nameCell, make('td', 'muted-cell', user.email), roleCell, make('td', 'muted-cell', formatDate(user.created_at)), actionCell);
      body.appendChild(row);
    });
  }

  function renderPager(meta, infoId, prevId, nextId, label) {
    if (!meta || !meta.total) {
      el(infoId).textContent = 'Showing 0 ' + label;
    } else {
      el(infoId).textContent = 'Showing ' + meta.from + '–' + meta.to + ' of ' + meta.total + ' ' + label;
    }
    el(prevId).disabled = !meta || meta.current_page <= 1;
    el(nextId).disabled = !meta || meta.current_page >= meta.last_page;
  }

  async function deleteUser(user) {
    if (!confirm('Delete ' + user.name + '? This cannot be undone.')) return;

    try {
      await request('DELETE', '/admin/users/' + user.id);
      toast(user.name + ' deleted');
      loadStats();
      loadUsers();
    } catch (err) {
      toast(err.message, true);
    }
  }

  async function loadProducts() {
    const p = state.products;
    const params = new URLSearchParams({ page: p.page });

    if (p.search) params.set('search', p.search);
    if (p.filter === 'active' || p.filter === 'inactive') params.set('status', p.filter);
    if (p.filter === 'deleted') params.set('trashed', 'only');

    el('product-page-info').textContent = 'Loading…';

    try {
      const res = await request('GET', '/admin/products?' + params.toString());
      renderProducts(res.data.data);
      renderPager(res.data.meta, 'product-page-info', 'product-prev', 'product-next', 'products');
    } catch (err) {
      if (err.status !== 401) toast(err.message, true);
      el('product-page-info').textContent = '';
    }
  }

  function renderProducts(products) {
    const body = el('products-body');
    body.replaceChildren();

    if (!products.length) {
      emptyRow(body, 6, state.products.search ? 'No products match your search.' : 'No products yet. Click "Add product" to create one.');
      return;
    }

    products.forEach((product) => {
      const row = make('tr');
      const deleted = !!product.deleted_at;
      if (deleted) row.className = 'is-deleted';

      const nameCell = make('td');
      const wrap = make('div', 'user-cell');
      wrap.append(make('div', 'avatar avatar--square', product.name.charAt(0).toUpperCase()), make('span', '', product.name));
      nameCell.appendChild(wrap);

      const skuCell = make('td');
      skuCell.appendChild(make('code', 'sku', product.sku));

      const priceCell = make('td', 'right', money.format(Number(product.price)));

      const stockCell = make('td', 'right');
      if (product.stock_quantity > 0) {
        stockCell.textContent = product.stock_quantity;
      } else {
        stockCell.appendChild(make('span', 'stock-out', 'Out of stock'));
      }

      const statusCell = make('td');
      if (deleted) {
        statusCell.appendChild(make('span', 'badge badge--deleted', 'deleted'));
      } else {
        statusCell.appendChild(make('span', 'badge badge--' + product.status, product.status));
      }

      const actionCell = make('td', 'right');
      const actions = make('div', 'row-actions');

      if (deleted) {
        const restore = make('button', 'btn btn--outline', 'Restore');
        restore.type = 'button';
        restore.setAttribute('aria-label', 'Restore ' + product.name);
        restore.addEventListener('click', () => restoreProduct(product));
        actions.appendChild(restore);
      } else {
        const edit = make('button', 'btn btn--outline', 'Edit');
        edit.type = 'button';
        edit.setAttribute('aria-label', 'Edit ' + product.name);
        edit.addEventListener('click', () => openProductForm(product));

        const del = make('button', 'btn btn--danger', 'Delete');
        del.type = 'button';
        del.setAttribute('aria-label', 'Delete ' + product.name);
        del.addEventListener('click', () => deleteProduct(product));

        actions.append(edit, del);
      }

      actionCell.appendChild(actions);
      row.append(nameCell, skuCell, priceCell, stockCell, statusCell, actionCell);
      body.appendChild(row);
    });
  }

  function clearFormErrors() {
    document.querySelectorAll('#product-form .field__error').forEach((span) => { span.textContent = ''; });
    document.querySelectorAll('#product-form .is-invalid').forEach((input) => { input.classList.remove('is-invalid'); });
    el('product-form-error').hidden = true;
  }

  function showFormErrors(errors) {
    Object.keys(errors).forEach((field) => {
      const span = document.querySelector('#product-form [data-error-for="' + field + '"]');
      const input = document.querySelector('#product-form [name="' + field + '"]');
      if (span) span.textContent = errors[field][0];
      if (input) input.classList.add('is-invalid');
    });
  }

  function openProductForm(product) {
    const form = el('product-form');
    form.reset();
    clearFormErrors();

    state.products.editingId = product ? product.id : null;

    el('product-form-title').textContent = product ? 'Edit product' : 'Add product';
    el('product-form-endpoint').textContent = product
      ? 'PATCH /api/admin/products/' + product.id
      : 'POST /api/admin/products';
    el('product-save').textContent = product ? 'Update product' : 'Save product';

    if (product) {
      el('p-name').value = product.name;
      el('p-sku').value = product.sku;
      el('p-price').value = product.price;
      el('p-stock').value = product.stock_quantity;
      el('p-status').value = product.status;
    } else {
      el('p-status').value = 'active';
    }

    el('product-form-card').hidden = false;
    el('product-form-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
    el('p-name').focus();
  }

  function closeProductForm() {
    el('product-form-card').hidden = true;
    state.products.editingId = null;
    clearFormErrors();
  }

  function validateProductForm(payload) {
    const errors = {};

    if (!payload.name) errors.name = ['Name is required.'];
    if (!payload.sku) errors.sku = ['SKU is required.'];
    else if (!/^[A-Za-z0-9_-]+$/.test(payload.sku)) errors.sku = ['Use only letters, numbers, dashes and underscores.'];
    if (payload.price === '' || isNaN(payload.price) || Number(payload.price) < 0) errors.price = ['Enter a valid price.'];
    if (payload.stock_quantity === '' || !Number.isInteger(Number(payload.stock_quantity)) || Number(payload.stock_quantity) < 0) {
      errors.stock_quantity = ['Enter a whole number, 0 or more.'];
    }

    return errors;
  }

  async function handleProductSave(event) {
    event.preventDefault();
    clearFormErrors();

    const payload = {
      name: el('p-name').value.trim(),
      sku: el('p-sku').value.trim(),
      price: el('p-price').value,
      stock_quantity: el('p-stock').value,
      status: el('p-status').value,
    };

    const localErrors = validateProductForm(payload);
    if (Object.keys(localErrors).length) {
      showFormErrors(localErrors);
      return;
    }

    payload.price = Number(payload.price);
    payload.stock_quantity = Number(payload.stock_quantity);

    const button = el('product-save');
    const editingId = state.products.editingId;
    button.disabled = true;

    try {
      if (editingId) {
        await request('PATCH', '/admin/products/' + editingId, payload);
        toast(payload.name + ' updated');
      } else {
        await request('POST', '/admin/products', payload);
        toast(payload.name + ' added');
        state.products.page = 1;
      }
      closeProductForm();
      loadProducts();
    } catch (err) {
      if (err.errors) {
        showFormErrors(err.errors);
      } else {
        el('product-form-error').textContent = err.message;
        el('product-form-error').hidden = false;
      }
    } finally {
      button.disabled = false;
    }
  }

  async function deleteProduct(product) {
    if (!confirm('Delete ' + product.name + '? You can restore it later from the Deleted tab.')) return;

    try {
      await request('DELETE', '/admin/products/' + product.id);
      toast(product.name + ' deleted');
      if (state.products.editingId === product.id) closeProductForm();
      loadProducts();
    } catch (err) {
      toast(err.message, true);
    }
  }

  async function restoreProduct(product) {
    try {
      await request('POST', '/admin/products/' + product.id + '/restore');
      toast(product.name + ' restored');
      loadProducts();
    } catch (err) {
      toast(err.message, true);
    }
  }

  async function loadOrders() {
    const o = state.orders;
    const params = new URLSearchParams({ page: o.page });
    if (o.status) params.set('status', o.status);

    el('order-page-info').textContent = 'Loading…';

    try {
      const res = await request('GET', '/admin/orders?' + params.toString());
      renderOrders(res.data.data);
      renderPager(res.data.meta, 'order-page-info', 'order-prev', 'order-next', 'orders');
    } catch (err) {
      if (err.status !== 401) toast(err.message, true);
      el('order-page-info').textContent = '';
    }
  }

  function renderOrders(orders) {
    const body = el('orders-body');
    body.replaceChildren();

    if (!orders.length) {
      emptyRow(body, 7, state.orders.status ? 'No ' + state.orders.status + ' orders.' : 'No orders yet.');
      return;
    }

    orders.forEach((order) => {
      const row = make('tr');

      const idCell = make('td');
      idCell.appendChild(make('span', 'order-id', '#' + order.id));

      const customerCell = make('td');
      const customer = make('div', 'customer-cell');
      if (order.customer) {
        customer.append(make('span', '', order.customer.name), make('span', 'customer-cell__email', order.customer.email));
      } else {
        customer.textContent = '–';
      }
      customerCell.appendChild(customer);

      const itemsCell = make('td', 'right', String(order.items_count ?? (order.items || []).length));
      const totalCell = make('td', 'right', money.format(Number(order.total)));

      const statusCell = make('td');
      statusCell.appendChild(make('span', 'badge badge--' + order.status, order.status));

      const actionCell = make('td', 'right');
      const actions = make('div', 'row-actions');

      const next = orderTransitions[order.status] || [];
      if (next.length) {
        const select = make('select', 'status-select');
        select.setAttribute('aria-label', 'Change status of order #' + order.id);
        const placeholder = make('option', '', 'Change status');
        placeholder.value = '';
        select.appendChild(placeholder);
        next.forEach((status) => {
          const option = make('option', '', 'Mark ' + status);
          option.value = status;
          select.appendChild(option);
        });
        select.addEventListener('change', () => {
          if (select.value) updateOrderStatus(order, select.value, select);
        });
        actions.appendChild(select);
      }

      const isOpen = state.orders.openId === order.id;
      const view = make('button', 'btn btn--outline', isOpen ? 'Hide' : 'View');
      view.type = 'button';
      view.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
      view.setAttribute('aria-label', (isOpen ? 'Hide' : 'View') + ' items of order #' + order.id);
      view.addEventListener('click', () => {
        state.orders.openId = isOpen ? null : order.id;
        renderOrders(orders);
      });
      actions.appendChild(view);
      actionCell.appendChild(actions);

      row.append(idCell, customerCell, itemsCell, totalCell, statusCell, make('td', 'muted-cell', formatDate(order.created_at)), actionCell);
      body.appendChild(row);

      if (isOpen) {
        body.appendChild(orderDetailRow(order));
      }
    });
  }

  function orderDetailRow(order) {
    const row = make('tr', 'order-detail');
    const cell = make('td');
    cell.colSpan = 7;

    const table = make('table', 'items-table');
    const head = make('thead');
    const headRow = make('tr');
    [['Product', ''], ['SKU', ''], ['Unit price', 'right'], ['Qty', 'right'], ['Line total', 'right']].forEach(([label, cls]) => {
      const th = make('th', cls, label);
      th.scope = 'col';
      headRow.appendChild(th);
    });
    head.appendChild(headRow);

    const tbody = make('tbody');
    (order.items || []).forEach((item) => {
      const tr = make('tr');
      const skuCell = make('td');
      skuCell.appendChild(make('code', 'sku', item.product_sku));
      tr.append(
        make('td', '', item.product_name),
        skuCell,
        make('td', 'right', money.format(Number(item.unit_price))),
        make('td', 'right', String(item.quantity)),
        make('td', 'right', money.format(Number(item.line_total)))
      );
      tbody.appendChild(tr);
    });

    const foot = make('tfoot');
    const footRow = make('tr');
    const label = make('td', 'right', 'Order total');
    label.colSpan = 4;
    footRow.append(label, make('td', 'right', money.format(Number(order.total))));
    foot.appendChild(footRow);

    table.append(head, tbody, foot);
    cell.appendChild(table);
    row.appendChild(cell);
    return row;
  }

  async function updateOrderStatus(order, status, select) {
    if (status === 'cancelled' && !confirm('Cancel order #' + order.id + '? Its stock will be returned.')) {
      select.value = '';
      return;
    }

    select.disabled = true;

    try {
      await request('PATCH', '/admin/orders/' + order.id + '/status', { status: status });
      toast('Order #' + order.id + ' marked ' + status);
      loadOrders();
    } catch (err) {
      toast(err.message, true);
      select.value = '';
      select.disabled = false;
    }
  }

  function isoDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
  }

  function setReportRange(days) {
    const to = new Date();
    const from = new Date();
    from.setDate(to.getDate() - (days - 1));
    el('r-from').value = isoDate(from);
    el('r-to').value = isoDate(to);
  }

  function clearReportErrors() {
    document.querySelectorAll('#report-form .field__error').forEach((span) => { span.textContent = ''; });
    document.querySelectorAll('#report-form .is-invalid').forEach((input) => { input.classList.remove('is-invalid'); });
  }

  function showReportErrors(errors) {
    Object.keys(errors).forEach((field) => {
      const span = document.querySelector('#report-form [data-error-for="' + field + '"]');
      const input = document.querySelector('#report-form [name="' + field + '"]');
      if (span) span.textContent = errors[field][0];
      if (input) input.classList.add('is-invalid');
    });
  }

  async function loadReport() {
    clearReportErrors();

    const from = el('r-from').value;
    const to = el('r-to').value;
    const limit = el('r-limit').value;

    if (!from || !to) {
      showReportErrors({ from: from ? [] : ['Pick a start date.'], to: to ? [] : ['Pick an end date.'] });
      return;
    }
    if (from > to) {
      showReportErrors({ to: ['End date must be on or after the start date.'] });
      return;
    }

    const params = new URLSearchParams({ from: from, to: to, limit: limit });
    el('report-endpoint').textContent = 'GET /api/admin/reports/top-products?' + params.toString();
    el('report-range').textContent = 'Loading…';

    const button = el('report-run');
    button.disabled = true;

    try {
      const res = await request('GET', '/admin/reports/top-products?' + params.toString());
      renderReport(res.data);
    } catch (err) {
      if (err.errors) {
        showReportErrors(err.errors);
      } else if (err.status !== 401) {
        toast(err.message, true);
      }
      el('report-range').textContent = '';
    } finally {
      button.disabled = false;
    }
  }

  function renderReport(data) {
    const rows = data.products || [];
    const body = el('report-body');
    body.replaceChildren();

    el('report-range').textContent = formatDate(data.from) + ' – ' + formatDate(data.to);
    el('report-statuses').textContent = (data.counted_statuses || []).join(', ') || '–';

    const units = rows.reduce((sum, row) => sum + row.quantity_sold, 0);
    const revenue = rows.reduce((sum, row) => sum + Number(row.revenue), 0);
    el('report-units').textContent = rows.length ? units : '0';
    el('report-revenue').textContent = money.format(revenue);

    if (!rows.length) {
      emptyRow(body, 6, 'No paid or shipped orders in this date range.');
      return;
    }

    const max = Math.max.apply(null, rows.map((row) => row.quantity_sold)) || 1;

    rows.forEach((row, index) => {
      const tr = make('tr');

      const rankCell = make('td');
      rankCell.appendChild(make('span', 'rank' + (index === 0 ? ' rank--1' : ''), String(index + 1)));

      const nameCell = make('td');
      const wrap = make('div', 'user-cell');
      wrap.append(make('div', 'avatar avatar--square', row.name.charAt(0).toUpperCase()), make('span', '', row.name));
      nameCell.appendChild(wrap);

      const skuCell = make('td');
      skuCell.appendChild(make('code', 'sku', row.sku));

      const qtyCell = make('td', 'qty-cell');
      const bar = make('div', 'qty-bar');
      const track = make('div', 'qty-bar__track');
      const fill = make('div', 'qty-bar__fill');
      fill.style.width = Math.max(4, Math.round((row.quantity_sold / max) * 100)) + '%';
      track.appendChild(fill);
      bar.append(track, make('span', 'qty-bar__value', String(row.quantity_sold)));
      qtyCell.appendChild(bar);

      tr.append(
        rankCell,
        nameCell,
        skuCell,
        qtyCell,
        make('td', 'right', String(row.orders_count)),
        make('td', 'right', money.format(Number(row.revenue)))
      );
      body.appendChild(tr);
    });
  }

  el('login-form').addEventListener('submit', handleLogin);
  el('logout-btn').addEventListener('click', handleLogout);

  el('search').addEventListener('input', debounce((event) => {
    state.users.search = event.target.value.trim();
    state.users.page = 1;
    loadUsers();
  }, 350));
  el('prev-btn').addEventListener('click', () => { state.users.page -= 1; loadUsers(); });
  el('next-btn').addEventListener('click', () => { state.users.page += 1; loadUsers(); });
  setupSegmented('role-filter', (value) => {
    state.users.role = value;
    state.users.page = 1;
    loadUsers();
  });

  el('product-search').addEventListener('input', debounce((event) => {
    state.products.search = event.target.value.trim();
    state.products.page = 1;
    loadProducts();
  }, 350));
  el('product-prev').addEventListener('click', () => { state.products.page -= 1; loadProducts(); });
  el('product-next').addEventListener('click', () => { state.products.page += 1; loadProducts(); });
  setupSegmented('product-filter', (value) => {
    state.products.filter = value;
    state.products.page = 1;
    loadProducts();
  });
  el('add-product-btn').addEventListener('click', () => openProductForm(null));
  el('product-cancel').addEventListener('click', closeProductForm);
  el('product-form').addEventListener('submit', handleProductSave);

  el('order-prev').addEventListener('click', () => { state.orders.page -= 1; loadOrders(); });
  el('order-next').addEventListener('click', () => { state.orders.page += 1; loadOrders(); });
  setupSegmented('order-filter', (value) => {
    state.orders.status = value;
    state.orders.page = 1;
    state.orders.openId = null;
    loadOrders();
  });

  el('report-form').addEventListener('submit', (event) => {
    event.preventDefault();
    loadReport();
  });
  document.querySelectorAll('#report-presets .segmented__btn').forEach((btn) => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#report-presets .segmented__btn').forEach((b) => {
        const active = b === btn;
        b.classList.toggle('is-active', active);
        b.setAttribute('aria-pressed', active ? 'true' : 'false');
      });
      setReportRange(Number(btn.dataset.days));
      loadReport();
    });
  });
  ['r-from', 'r-to'].forEach((id) => {
    el(id).addEventListener('input', () => {
      document.querySelectorAll('#report-presets .segmented__btn').forEach((b) => {
        b.classList.remove('is-active');
        b.setAttribute('aria-pressed', 'false');
      });
    });
  });
  el('r-limit').addEventListener('change', loadReport);

  window.addEventListener('hashchange', () => {
    if (state.token) applyRoute();
  });

  if (state.token && state.user && state.user.role === 'admin') {
    showApp();
  } else {
    clearSession();
    showLogin();
  }
})();
