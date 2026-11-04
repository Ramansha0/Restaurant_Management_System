// Restaurant Live — single server for Customer page + Waiter page
// Live updates via Server-Sent Events (no extra packages needed)
import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// ---------------- MENU ----------------
const MENU = [
  { id: 1,  name: 'Steam Momo (10 pcs)',   price: 180, emoji: '🥟', category: 'Starters' },
  { id: 2,  name: 'Fried Momo (10 pcs)',   price: 200, emoji: '🥟', category: 'Starters' },
  { id: 3,  name: 'Chatpate',              price: 90,  emoji: '🥗', category: 'Starters' },
  { id: 4,  name: 'Chilli Sausage',        price: 250, emoji: '🌭', category: 'Starters' },
  { id: 5,  name: 'Chicken Biryani',       price: 320, emoji: '🍛', category: 'Mains' },
  { id: 6,  name: 'Mutton Thakali Set',    price: 450, emoji: '🍲', category: 'Mains' },
  { id: 7,  name: 'Dal Bhat Set',          price: 250, emoji: '🍚', category: 'Mains' },
  { id: 8,  name: 'Chicken Chowmein',      price: 190, emoji: '🍜', category: 'Mains' },
  { id: 9,  name: 'Veg Fried Rice',        price: 180, emoji: '🍛', category: 'Mains' },
  { id: 10, name: 'Chicken Sizzler',       price: 420, emoji: '🔥', category: 'Mains' },
  { id: 11, name: 'Milk Tea',              price: 40,  emoji: '☕', category: 'Drinks' },
  { id: 12, name: 'Cold Coffee',           price: 120, emoji: '🧋', category: 'Drinks' },
  { id: 13, name: 'Lemon Soda',            price: 100, emoji: '🍋', category: 'Drinks' },
  { id: 14, name: 'Coke (500ml)',          price: 80,  emoji: '🥤', category: 'Drinks' },
];

let orders = [];
let nextId = 1;
const STATUSES = ['new', 'preparing', 'ready', 'served', 'paid'];
const clients = new Set();

// ---------------- SSE ----------------
function broadcast() {
  const msg = `event: orders\ndata: ${JSON.stringify(orders)}\n\n`;
  for (const res of clients) {
    try { res.write(msg); } catch { clients.delete(res); }
  }
}

setInterval(() => {
  for (const res of clients) {
    try { res.write(': ping\n\n'); } catch { clients.delete(res); }
  }
}, 25000);

// ---------------- helpers ----------------
const json = (res, code, data) => {
  res.writeHead(code, {
    'Content-Type': 'application/json; charset=utf-8',
    'Cache-Control': 'no-store',
    'Access-Control-Allow-Origin': '*',
  });
  res.end(JSON.stringify(data));
};

const readBody = (req) => new Promise((resolve, reject) => {
  let raw = '';
  req.on('data', (c) => { raw += c; if (raw.length > 1e6) req.destroy(); });
  req.on('end', () => { try { resolve(raw ? JSON.parse(raw) : {}); } catch (e) { reject(e); } });
  req.on('error', reject);
});

const serveFile = (res, file, type) => {
  fs.readFile(path.join(__dirname, file), (err, buf) => {
    if (err) { res.writeHead(404, { 'Content-Type': 'text/plain' }); res.end('Not found'); return; }
    res.writeHead(200, { 'Content-Type': type, 'Cache-Control': 'no-store' });
    res.end(buf);
  });
};

// ---------------- server ----------------
const server = http.createServer(async (req, res) => {
  const url = new URL(req.url, 'http://localhost');
  const p = url.pathname;

  // live event stream
  if (p === '/api/events') {
    res.writeHead(200, {
      'Content-Type': 'text/event-stream',
      'Cache-Control': 'no-cache, no-transform',
      Connection: 'keep-alive',
      'Access-Control-Allow-Origin': '*',
    });
    res.write('retry: 2000\n\n');
    clients.add(res);
    req.on('close', () => clients.delete(res));
    return;
  }

  if (p === '/api/menu') return json(res, 200, MENU);

  if (p === '/api/orders' && req.method === 'GET') return json(res, 200, orders);

  if (p === '/api/orders' && req.method === 'POST') {
    try {
      const body = await readBody(req);
      const table = Number(body.table);
      if (!Number.isInteger(table) || table < 1 || table > 50) {
        return json(res, 400, { error: 'Invalid table number (1-50)' });
      }
      if (!Array.isArray(body.items) || body.items.length === 0) {
        return json(res, 400, { error: 'Cart is empty' });
      }
      const items = [];
      for (const it of body.items) {
        const m = MENU.find((x) => x.id === Number(it.id));
        const qty = Number(it.qty);
        if (!m || !Number.isInteger(qty) || qty < 1 || qty > 20) {
          return json(res, 400, { error: 'Invalid item' });
        }
        items.push({ id: m.id, name: m.name, price: m.price, qty });
      }
      const total = items.reduce((s, i) => s + i.price * i.qty, 0);
      const order = {
        id: nextId++,
        table,
        items,
        total,
        status: 'new',
        createdAt: Date.now(),
        updatedAt: Date.now(),
      };
      orders.push(order);
      if (orders.length > 300) {
        const paidIdx = orders.findIndex((o) => o.status === 'paid');
        if (paidIdx >= 0) orders.splice(paidIdx, 1); else orders.shift();
      }
      broadcast();
      return json(res, 201, order);
    } catch {
      return json(res, 400, { error: 'Bad request' });
    }
  }

  const statusMatch = p.match(/^\/api\/orders\/(\d+)\/status$/);
  if (statusMatch && req.method === 'POST') {
    const order = orders.find((o) => o.id === Number(statusMatch[1]));
    if (!order) return json(res, 404, { error: 'Order not found' });
    const { status } = await readBody(req);
    if (!STATUSES.includes(status)) return json(res, 400, { error: 'Invalid status' });
    order.status = status;
    order.updatedAt = Date.now();
    broadcast();
    return json(res, 200, order);
  }

  if (p === '/api/orders/clear-paid' && req.method === 'POST') {
    orders = orders.filter((o) => o.status !== 'paid');
    broadcast();
    return json(res, 200, { ok: true });
  }

  // payment QR image — owner saves payment-qr.png/.jpg/.jpeg/.webp in this folder
  const QR_TYPES = { '.png': 'image/png', '.jpg': 'image/jpeg', '.jpeg': 'image/jpeg', '.webp': 'image/webp' };
  if (p === '/payment-qr') {
    const name = Object.keys(QR_TYPES)
      .map((ext) => 'payment-qr' + ext)
      .find((f) => fs.existsSync(path.join(__dirname, f)));
    if (!name) {
      return json(res, 404, { error: 'Payment QR image not added yet. Owner: save payment-qr.png in the restaurant folder.' });
    }
    return serveFile(res, name, QR_TYPES[path.extname(name)]);
  }

  // pages
  if (p === '/' || p === '/customer') return serveFile(res, 'customer.html', 'text/html; charset=utf-8');
  if (p === '/waiter') return serveFile(res, 'waiter.html', 'text/html; charset=utf-8');

  res.writeHead(404, { 'Content-Type': 'text/plain' });
  res.end('Not found');
});

// ---------------- start (auto-picks a free port) ----------------
const PORTS = [3000, 3001, 3002, 5174];

function start(i) {
  if (i >= PORTS.length) { console.error('No free port found.'); process.exit(1); }
  const s = server.listen(PORTS[i], '0.0.0.0', () => {
    const port = PORTS[i];
    let lan = null;
    for (const list of Object.values(os.networkInterfaces())) {
      for (const n of list) if (n.family === 'IPv4' && !n.internal) lan = lan || n.address;
    }
    console.log('');
    console.log('  🍽️  Restaurant Live is running!');
    console.log('');
    console.log(`  👤 Customer page : http://localhost:${port}/customer`);
    console.log(`  🧑‍🍳 Waiter page  : http://localhost:${port}/waiter`);
    const qrFile = ['payment-qr.png', 'payment-qr.jpg', 'payment-qr.jpeg', 'payment-qr.webp']
      .find((f) => fs.existsSync(path.join(__dirname, f)));
    console.log(qrFile
      ? '  💳 Payment QR   : ' + qrFile + ' ✔ (shown to customers on "Proceed for Payment")'
      : '  💳 Payment QR   : not added yet — save your eSewa QR as payment-qr.png in this folder');
    if (lan) {
      console.log('');
      console.log('  📱 Open on phone (same Wi-Fi):');
      console.log(`  👤 http://${lan}:${port}/customer`);
      console.log(`  🧑‍🍳 http://${lan}:${port}/waiter`);
    }
    console.log('');
  });
  s.on('error', (e) => {
    if (e.code === 'EADDRINUSE') start(i + 1);
    else { console.error(e); process.exit(1); }
  });
}
start(0);
