import 'dotenv/config';
import axios from 'axios';
import express from 'express';
import QRCode from 'qrcode';
import qrcode from 'qrcode-terminal';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import whatsappWeb from 'whatsapp-web.js';

const { Client, LocalAuth } = whatsappWeb;

const app = express();

app.use(express.json({ limit: '2mb' }));

const host = process.env.HOST || '127.0.0.1';
const port = Number(process.env.PORT || 3100);
const laravelBaseUrl = (process.env.LARAVEL_CHAT_BASE_URL || 'https://zentrum-tvde.com').replace(/\/+$/, '');
const sessionStorePath = resolve(process.env.SESSION_STORE_PATH || './storage/sessions.json');
const whatsappSessionPath = resolve(process.env.WHATSAPP_SESSION_PATH || './storage/whatsapp-session');
const ignoreGroups = process.env.IGNORE_GROUPS !== 'false';

let isWhatsappReady = false;
let lastWhatsappState = 'starting';
let lastQr = null;
let lastQrAt = null;
let lastError = null;
const processingMessages = new Set();
const chatQueues = new Map();
let reconnectTimer = null;
let isShuttingDown = false;

function log(level, event, details = {}) {
  const entry = {
    timestamp: new Date().toISOString(),
    level,
    event,
    ...details,
  };

  const output = JSON.stringify(entry);

  if (level === 'error') {
    console.error(output);
  } else if (level === 'warn') {
    console.warn(output);
  } else {
    console.log(output);
  }
}

process.on('unhandledRejection', (error) => {
  log('error', 'unhandled_rejection', { error: error?.stack || String(error) });
});

process.on('uncaughtException', (error) => {
  log('error', 'uncaught_exception', { error: error?.stack || String(error) });
});

async function readSessionStore() {
  try {
    return JSON.parse(await readFile(sessionStorePath, 'utf8'));
  } catch {
    return {};
  }
}

async function writeSessionStore(store) {
  await mkdir(dirname(sessionStorePath), { recursive: true });
  await writeFile(sessionStorePath, JSON.stringify(store, null, 2));
}

async function resolveChatSession(from, externalName = null, externalPhone = null) {
  const store = await readSessionStore();

  if (store[from]) {
    return store[from];
  }

  const { data } = await axios.post(`${laravelBaseUrl}/app/chat/session`, {
    session_token: null,
    source: 'whatsapp',
    external_id: from,
    external_name: externalName,
    external_phone: externalPhone,
  }, {
    headers: { Accept: 'application/json' },
    timeout: 30000,
  });

  store[from] = data.session_token;
  await writeSessionStore(store);

  return store[from];
}

async function resetChatSession(from) {
  const store = await readSessionStore();
  delete store[from];
  await writeSessionStore(store);
}

async function askLaravelChat(from, message, externalName = null, externalPhone = null) {
  const sessionToken = await resolveChatSession(from, externalName, externalPhone);
  const { data } = await axios.post(`${laravelBaseUrl}/app/chat/message`, {
    session_token: sessionToken,
    message,
    source: 'whatsapp',
    external_id: from,
    external_name: externalName,
    external_phone: externalPhone,
  }, {
    headers: { Accept: 'application/json' },
    timeout: 60000,
  });

  if (data.session_token && data.session_token !== sessionToken) {
    const store = await readSessionStore();
    store[from] = data.session_token;
    await writeSessionStore(store);
  }

  return data.assistant_message?.content || 'Obrigado pela mensagem. Vamos responder assim que possivel.';
}

function splitReply(text, maxLength = 3500) {
  const chunks = [];
  let remaining = String(text || '').trim();

  while (remaining.length > maxLength) {
    let splitAt = remaining.lastIndexOf('\n', maxLength);

    if (splitAt < maxLength * 0.5) {
      splitAt = remaining.lastIndexOf(' ', maxLength);
    }

    if (splitAt < maxLength * 0.5) {
      splitAt = maxLength;
    }

    chunks.push(remaining.slice(0, splitAt).trim());
    remaining = remaining.slice(splitAt).trim();
  }

  if (remaining) {
    chunks.push(remaining);
  }

  return chunks.length ? chunks : [''];
}

async function replyInChunks(message, text) {
  for (const chunk of splitReply(text)) {
    await client.sendMessage(message.from, chunk);
  }
}

function queueChatJob(chatId, job) {
  const previous = chatQueues.get(chatId) || Promise.resolve();
  const current = previous.catch(() => undefined).then(job);

  chatQueues.set(chatId, current.finally(() => {
    if (chatQueues.get(chatId) === current) {
      chatQueues.delete(chatId);
    }
  }));

  return current;
}

const client = new Client({
  authStrategy: new LocalAuth({
    clientId: 'zentrum-whatsapp',
    dataPath: whatsappSessionPath,
  }),
  takeoverOnConflict: true,
  takeoverTimeoutMs: 0,
  puppeteer: {
    headless: process.env.WHATSAPP_HEADLESS !== 'false',
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--disable-gpu',
    ],
  },
});

client.on('qr', (qr) => {
  isWhatsappReady = false;
  lastWhatsappState = 'qr';
  lastQr = qr;
  lastQrAt = new Date().toISOString();
  log('info', 'whatsapp_qr_ready', { generated_at: lastQrAt });
  console.log('Scan this QR code with WhatsApp Business: Settings > Linked devices > Link a device');
  qrcode.generate(qr, { small: true });
});

client.on('authenticated', () => {
  lastWhatsappState = 'authenticated';
  log('info', 'whatsapp_authenticated');
});

client.on('ready', () => {
  isWhatsappReady = true;
  lastWhatsappState = 'ready';
  lastQr = null;
  lastError = null;
  log('info', 'whatsapp_ready');
});

client.on('loading_screen', (percent, message) => {
  log('info', 'whatsapp_loading', { percent, message });
});

client.on('change_state', (state) => {
  log('info', 'whatsapp_state_changed', { state });
});

client.on('auth_failure', (message) => {
  isWhatsappReady = false;
  lastWhatsappState = 'auth_failure';
  lastError = message;
  log('error', 'whatsapp_authentication_failed', { error: message });
});

client.on('disconnected', (reason) => {
  isWhatsappReady = false;
  lastWhatsappState = 'disconnected';
  lastError = reason;
  log('warn', 'whatsapp_disconnected', { reason });
  scheduleReconnect();
});

function scheduleReconnect() {
  if (isShuttingDown || reconnectTimer) {
    return;
  }

  reconnectTimer = setTimeout(async () => {
    reconnectTimer = null;

    try {
      await client.destroy().catch(() => undefined);
      await client.initialize();
    } catch (error) {
      log('error', 'whatsapp_reconnect_failed', { error: error?.stack || String(error) });
      scheduleReconnect();
    }
  }, 10000);
}

client.on('message', async (message) => {
  if (!isWhatsappReady) {
    isWhatsappReady = true;
    lastWhatsappState = 'message_received';
  }

  if (message.fromMe) {
    return;
  }

  if (ignoreGroups && message.from.endsWith('@g.us')) {
    return;
  }

  const messageKey = message.id?._serialized || `${message.from}:${message.timestamp}`;

  if (processingMessages.has(messageKey)) {
    return;
  }

  processingMessages.add(messageKey);

  await queueChatJob(message.from, async () => {
    await handleIncomingMessage(message, messageKey);
  });

  processingMessages.delete(messageKey);
});

async function handleIncomingMessage(message, messageKey) {
  log('info', 'incoming_message', {
    from: message.from,
    id: messageKey,
    type: message.type,
    hasBody: Boolean(message.body?.trim()),
  });

  try {
    const text = message.body?.trim();

    if (!text) {
      if (message.type !== 'chat') {
        await client.sendMessage(message.from, 'Por enquanto consigo responder melhor a mensagens de texto.');
      }
      return;
    }

    if (text.toLowerCase() === '/reset') {
      await resetChatSession(message.from);
      await client.sendMessage(message.from, 'Conversa reiniciada.');
      return;
    }

    await client.sendSeen(message.from).catch(() => undefined);
    const contact = await message.getContact().catch(() => null);
    const externalName = contact?.pushname || contact?.name || null;
    const externalPhone = contact?.number || contact?.id?.user || null;
    const reply = await askLaravelChat(message.from, text, externalName, externalPhone);
    await replyInChunks(message, reply);
  } catch (error) {
    log('error', 'whatsapp_message_failed', {
      from: message.from,
      id: messageKey,
      error: error?.response?.data || error?.message || error,
    });

    await client.sendMessage(message.from, 'Ocorreu um erro temporario. Tenta novamente dentro de momentos.').catch(() => undefined);
  }
}

app.get('/health', (_request, response) => {
  response.json({
    ok: true,
    whatsapp_ready: isWhatsappReady,
    whatsapp_state: lastWhatsappState,
    last_qr_at: lastQrAt,
    last_error: lastError,
    queued_chats: chatQueues.size,
    processing_messages: processingMessages.size,
  });
});

app.get('/qr', async (_request, response) => {
  if (isWhatsappReady) {
    response.type('html').send('<!doctype html><meta charset="utf-8"><title>Zentrum WhatsApp</title><body style="font-family:Arial,sans-serif;text-align:center;padding:40px"><h1>WhatsApp ligado</h1><p>O bot ja esta pronto.</p></body>');
    return;
  }

  if (!lastQr) {
    response.type('html').send('<!doctype html><meta charset="utf-8"><title>Zentrum WhatsApp QR</title><meta http-equiv="refresh" content="2"><body style="font-family:Arial,sans-serif;text-align:center;padding:40px"><h1>A aguardar QR...</h1><p>Atualiza automaticamente.</p></body>');
    return;
  }

  const qrDataUrl = await QRCode.toDataURL(lastQr, {
    errorCorrectionLevel: 'M',
    margin: 2,
    width: 360,
  });

  response.type('html').send(`<!doctype html>
<meta charset="utf-8">
<title>Zentrum WhatsApp QR</title>
<meta http-equiv="refresh" content="20">
<body style="font-family:Arial,sans-serif;text-align:center;padding:32px;background:#f6f7f9;color:#111827">
  <h1>Associar WhatsApp Business</h1>
  <p>Abre o WhatsApp Business: Definicoes &gt; Dispositivos associados &gt; Associar dispositivo.</p>
  <img src="${qrDataUrl}" alt="QR WhatsApp" style="width:360px;height:360px;background:white;padding:16px;border:1px solid #d1d5db">
  <p style="color:#6b7280">QR gerado em ${lastQrAt || ''}. Esta pagina atualiza automaticamente.</p>
</body>`);
});

const server = app.listen(port, host, () => {
  log('info', 'health_server_listening', { host, port });
});

server.on('error', (error) => {
  log('error', 'health_server_failed', { error: error?.stack || String(error) });
});

client.initialize().catch((error) => {
  log('error', 'whatsapp_initialize_failed', { error: error?.stack || String(error) });
  scheduleReconnect();
});

async function shutdown(signal) {
  if (isShuttingDown) {
    return;
  }

  isShuttingDown = true;
  log('info', 'shutdown_started', { signal });

  if (reconnectTimer) {
    clearTimeout(reconnectTimer);
    reconnectTimer = null;
  }

  await new Promise((resolveClose) => server.close(resolveClose));
  await client.destroy().catch((error) => {
    log('warn', 'whatsapp_destroy_failed', { error: error?.message || String(error) });
  });

  log('info', 'shutdown_complete', { signal });
  process.exit(0);
}

process.once('SIGINT', () => shutdown('SIGINT'));
process.once('SIGTERM', () => shutdown('SIGTERM'));
