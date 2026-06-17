import 'dotenv/config';
import axios from 'axios';
import express from 'express';
import qrcode from 'qrcode-terminal';
import { existsSync } from 'node:fs';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, resolve } from 'node:path';
import whatsappWeb from 'whatsapp-web.js';

const { Client, LocalAuth } = whatsappWeb;

const app = express();

app.use(express.json({ limit: '2mb' }));

const port = Number(process.env.PORT || 3100);
const laravelBaseUrl = (process.env.LARAVEL_CHAT_BASE_URL || 'https://zentrum-tvde.com').replace(/\/+$/, '');
const sessionStorePath = resolve(process.env.SESSION_STORE_PATH || './storage/sessions.json');
const whatsappSessionPath = resolve(process.env.WHATSAPP_SESSION_PATH || './storage/whatsapp-session');
const ignoreGroups = process.env.IGNORE_GROUPS !== 'false';
const browserPathCandidates = [
  process.env.WHATSAPP_BROWSER_PATH,
  'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
  'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
  'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
  'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
].filter(Boolean);
const browserPath = browserPathCandidates.find((path) => existsSync(path));

let isWhatsappReady = false;
const processingMessages = new Set();
const chatQueues = new Map();

process.on('unhandledRejection', (error) => {
  console.error('unhandled_rejection', error?.stack || error);
});

process.on('uncaughtException', (error) => {
  console.error('uncaught_exception', error?.stack || error);
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

async function resolveChatSession(from) {
  const store = await readSessionStore();

  if (store[from]) {
    return store[from];
  }

  const { data } = await axios.post(`${laravelBaseUrl}/app/chat/session`, {
    session_token: null,
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

async function askLaravelChat(from, message) {
  const sessionToken = await resolveChatSession(from);
  const { data } = await axios.post(`${laravelBaseUrl}/app/chat/message`, {
    session_token: sessionToken,
    message,
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
  puppeteer: {
    headless: process.env.WHATSAPP_HEADLESS !== 'false',
    executablePath: browserPath,
    args: ['--no-sandbox', '--disable-setuid-sandbox'],
  },
});

client.on('qr', (qr) => {
  isWhatsappReady = false;
  console.log('Scan this QR code with WhatsApp Business: Settings > Linked devices > Link a device');
  qrcode.generate(qr, { small: true });
});

client.on('authenticated', () => {
  console.log('WhatsApp authenticated.');
});

client.on('ready', () => {
  isWhatsappReady = true;
  console.log('WhatsApp bot is ready.');
});

client.on('loading_screen', (percent, message) => {
  console.log('WhatsApp loading:', percent, message);
});

client.on('change_state', (state) => {
  console.log('WhatsApp state:', state);
});

client.on('auth_failure', (message) => {
  isWhatsappReady = false;
  console.error('WhatsApp authentication failed:', message);
});

client.on('disconnected', (reason) => {
  isWhatsappReady = false;
  console.warn('WhatsApp disconnected:', reason);
});

client.on('message', async (message) => {
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
  console.log('incoming_message', {
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
    const reply = await askLaravelChat(message.from, text);
    await replyInChunks(message, reply);
  } catch (error) {
    console.error('whatsapp_message_failed', {
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
  });
});

const server = app.listen(port, () => {
  console.log(`Zentrum WhatsApp Web bot health server listening on port ${port}`);
});

server.on('error', (error) => {
  console.error('health_server_failed', error);
});

client.initialize().catch((error) => {
  console.error('whatsapp_initialize_failed', error?.stack || error);
});
