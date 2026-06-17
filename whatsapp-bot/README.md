# Zentrum WhatsApp Web Bot

Servidor Node para responder no WhatsApp Business usando WhatsApp Web e o chat Laravel/OpenAI existente.

Esta via nao usa WhatsApp Cloud API, por isso nao precisa de `WHATSAPP_VERIFY_TOKEN`, `WHATSAPP_ACCESS_TOKEN` ou `WHATSAPP_PHONE_NUMBER_ID`. Tem de manter este processo ligado, tal como uma sessao de WhatsApp Web.

## Instalar

```bash
npm install
copy .env.example .env
npm start
```

Quando aparecer o QR code no terminal, abre o WhatsApp Business no telemovel:

1. Definicoes
2. Dispositivos associados
3. Associar dispositivo
4. Ler o QR code

Depois disso, as mensagens recebidas por esse WhatsApp sao enviadas para o chat Laravel em `LARAVEL_CHAT_BASE_URL`.

## Variaveis

```env
PORT=3100
LARAVEL_CHAT_BASE_URL=https://zentrum-tvde.com
SESSION_STORE_PATH=./storage/sessions.json
WHATSAPP_SESSION_PATH=./storage/whatsapp-session
WHATSAPP_BROWSER_PATH=
WHATSAPP_HEADLESS=true
IGNORE_GROUPS=true
```

No Windows pode apontar para o Chrome instalado:

```env
WHATSAPP_BROWSER_PATH=C:\Program Files\Google\Chrome\Application\chrome.exe
```

## Producao

Exemplo com PM2:

```bash
npm install -g pm2
pm2 start server.js --name zentrum-whatsapp-bot
pm2 save
```

Notas:

- Nao ha custo Meta/WhatsApp por mensagem nesta via.
- Continua a haver custo OpenAI quando o chat gera respostas.
- Esta solucao depende de WhatsApp Web e nao e uma API oficial. Pode exigir novo QR se a sessao cair.
