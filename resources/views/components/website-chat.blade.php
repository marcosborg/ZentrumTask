@php
    $whatsappNumber = preg_replace('/\D+/', '', (string) config('services.whatsapp.public_number'));
    $whatsappMessage = (string) config('services.whatsapp.public_message');
    $whatsappHref = $whatsappNumber !== ''
        ? 'https://wa.me/'.$whatsappNumber.'?text='.rawurlencode($whatsappMessage)
        : null;
@endphp

<div
    id="zt-chat-widget"
    data-session-url="{{ route('website.chat.session') }}"
    data-message-url="{{ route('website.chat.message') }}"
    data-csrf="{{ csrf_token() }}"
>
    <style>
        #zt-chat-widget {
            position: fixed;
            right: 1rem;
            bottom: 1rem;
            z-index: 1200;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
        }

        #zt-chat-widget .zt-chat-toggle {
            border: 0;
            width: 62px;
            height: 62px;
            border-radius: 999px;
            background: linear-gradient(135deg, #ff8a00 0%, #ff4d00 100%);
            color: #fff;
            box-shadow: 0 18px 30px rgba(255, 92, 0, 0.35);
            font-size: 24px;
        }

        #zt-chat-widget .zt-chat-actions {
            display: flex;
            gap: 0.7rem;
            justify-content: flex-end;
            align-items: center;
        }

        #zt-chat-widget .zt-whatsapp-toggle {
            width: 62px;
            height: 62px;
            border-radius: 999px;
            background: #25d366;
            color: #fff;
            box-shadow: 0 18px 30px rgba(37, 211, 102, 0.32);
            font-size: 27px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        #zt-chat-widget .zt-whatsapp-toggle:hover {
            color: #fff;
            background: #1ebe5d;
        }

        #zt-chat-widget .zt-chat-panel {
            width: min(92vw, 390px);
            height: min(75vh, 640px);
            border-radius: 18px;
            background: linear-gradient(180deg, #1f2937 0%, #111827 100%);
            color: #e5e7eb;
            box-shadow: 0 28px 60px rgba(15, 23, 42, 0.45);
            display: none;
            flex-direction: column;
            overflow: hidden;
            margin-bottom: 0.75rem;
        }

        #zt-chat-widget .zt-chat-panel.is-open {
            display: flex;
        }

        #zt-chat-widget .zt-chat-header {
            padding: 0.9rem 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.12);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, rgba(255, 138, 0, 0.2), rgba(255, 77, 0, 0.2));
        }

        #zt-chat-widget .zt-chat-title {
            font-size: 0.95rem;
            font-weight: 700;
            margin: 0;
        }

        #zt-chat-widget .zt-chat-close {
            border: 0;
            background: transparent;
            color: #e5e7eb;
            font-size: 1.2rem;
        }

        #zt-chat-widget .zt-chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.7rem;
            background: radial-gradient(circle at top right, rgba(255, 138, 0, 0.1), transparent 38%);
        }

        #zt-chat-widget .zt-chat-bubble {
            max-width: 85%;
            border-radius: 14px;
            padding: 0.65rem 0.8rem;
            font-size: 0.92rem;
            line-height: 1.4;
            white-space: pre-wrap;
        }

        #zt-chat-widget .zt-chat-bubble--assistant {
            background: rgba(255, 255, 255, 0.1);
            align-self: flex-start;
        }

        #zt-chat-widget .zt-chat-bubble--user {
            background: linear-gradient(135deg, #ff8a00 0%, #ff4d00 100%);
            color: #fff;
            align-self: flex-end;
        }

        #zt-chat-widget .zt-chat-footer {
            border-top: 1px solid rgba(255, 255, 255, 0.12);
            padding: 0.8rem;
            background: rgba(2, 6, 23, 0.72);
        }

        #zt-chat-widget .zt-chat-input-wrap {
            display: flex;
            align-items: end;
            gap: 0.5rem;
        }

        #zt-chat-widget .zt-chat-input {
            width: 100%;
            resize: none;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(15, 23, 42, 0.55);
            color: #e5e7eb;
            padding: 0.55rem 0.75rem;
            min-height: 44px;
            max-height: 120px;
        }

        #zt-chat-widget .zt-chat-send {
            border: 0;
            border-radius: 12px;
            min-width: 44px;
            height: 44px;
            color: #fff;
            background: linear-gradient(135deg, #ff8a00 0%, #ff4d00 100%);
        }

        #zt-chat-widget .zt-chat-note {
            margin-top: 0.55rem;
            font-size: 0.72rem;
            color: #93a1b4;
        }

        #zt-chat-widget .zt-chat-typing {
            display: none;
            font-size: 0.78rem;
            color: #cbd5e1;
            margin-top: 0.2rem;
        }

        #zt-chat-widget .zt-chat-typing.is-visible {
            display: block;
        }
    </style>

    <div class="zt-chat-panel" id="zt-chat-panel" aria-live="polite">
        <div class="zt-chat-header">
            <p class="zt-chat-title" id="zt-chat-title">Assistente</p>
            <button type="button" class="zt-chat-close" id="zt-chat-close" aria-label="Fechar chat">x</button>
        </div>

        <div class="zt-chat-messages" id="zt-chat-messages"></div>

        <div class="zt-chat-footer">
            <div class="zt-chat-input-wrap">
                <textarea
                    id="zt-chat-input"
                    class="zt-chat-input"
                    placeholder="Escreve a tua mensagem..."
                    rows="1"
                ></textarea>
                <button type="button" id="zt-chat-send" class="zt-chat-send" aria-label="Enviar">
                    <i class="fa-solid fa-paper-plane"></i>
                </button>
            </div>
            <div class="zt-chat-typing" id="zt-chat-typing">A responder...</div>
            <div class="zt-chat-note">Este chat usa IA e guarda historico para melhoria de atendimento.</div>
        </div>
    </div>

    <div class="zt-chat-actions">
        @if($whatsappHref)
            <a
                class="zt-whatsapp-toggle"
                href="{{ $whatsappHref }}"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="Abrir WhatsApp"
            >
                <i class="fa-brands fa-whatsapp"></i>
            </a>
        @endif
        <button type="button" class="zt-chat-toggle" id="zt-chat-toggle" aria-label="Abrir chat">
            <i class="fa-regular fa-comments"></i>
        </button>
    </div>

    <script>
        (() => {
            const root = document.getElementById('zt-chat-widget');

            if (!root) {
                return;
            }

            const storageKey = 'zentrum_chat_session_token';
            const panel = root.querySelector('#zt-chat-panel');
            const toggleButton = root.querySelector('#zt-chat-toggle');
            const closeButton = root.querySelector('#zt-chat-close');
            const sendButton = root.querySelector('#zt-chat-send');
            const input = root.querySelector('#zt-chat-input');
            const title = root.querySelector('#zt-chat-title');
            const messagesWrap = root.querySelector('#zt-chat-messages');
            const typing = root.querySelector('#zt-chat-typing');

            const sessionUrl = root.dataset.sessionUrl;
            const messageUrl = root.dataset.messageUrl;
            const csrf = root.dataset.csrf;

            let enabled = true;
            let sessionToken = localStorage.getItem(storageKey) || null;
            let isSending = false;

            const autoResizeInput = () => {
                input.style.height = 'auto';
                input.style.height = `${Math.min(input.scrollHeight, 120)}px`;
            };

            const showTyping = (visible) => {
                typing.classList.toggle('is-visible', visible);
            };

            const addMessage = (role, content) => {
                const item = document.createElement('div');
                item.className = `zt-chat-bubble ${role === 'user' ? 'zt-chat-bubble--user' : 'zt-chat-bubble--assistant'}`;
                item.textContent = content;
                messagesWrap.appendChild(item);
                messagesWrap.scrollTop = messagesWrap.scrollHeight;
            };

            const postJson = async (url, payload) => {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(payload),
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || 'Erro no chat.');
                }

                return data;
            };

            const bootstrap = async () => {
                try {
                    const data = await postJson(sessionUrl, {
                        session_token: sessionToken,
                    });

                    enabled = !!data.enabled;

                    if (!enabled) {
                        root.style.display = 'none';
                        return;
                    }

                    sessionToken = data.session_token;
                    localStorage.setItem(storageKey, sessionToken);
                    title.textContent = data.assistant_name || 'Assistente';
                    messagesWrap.innerHTML = '';

                    (data.messages || []).forEach((message) => {
                        addMessage(message.role, message.content);
                    });
                } catch (error) {
                    addMessage('assistant', 'Nao foi possivel iniciar o chat agora.');
                }
            };

            const sendMessage = async () => {
                if (!enabled || isSending) {
                    return;
                }

                const message = input.value.trim();

                if (!message) {
                    return;
                }

                isSending = true;
                addMessage('user', message);
                input.value = '';
                autoResizeInput();
                showTyping(true);

                try {
                    const data = await postJson(messageUrl, {
                        session_token: sessionToken,
                        message,
                    });

                    sessionToken = data.session_token || sessionToken;
                    localStorage.setItem(storageKey, sessionToken);

                    if (data.assistant_message?.content) {
                        addMessage('assistant', data.assistant_message.content);
                    }
                } catch (error) {
                    addMessage('assistant', 'Ocorreu um erro no envio da mensagem.');
                } finally {
                    isSending = false;
                    showTyping(false);
                }
            };

            toggleButton.addEventListener('click', () => {
                panel.classList.toggle('is-open');
                if (panel.classList.contains('is-open')) {
                    input.focus();
                }
            });

            closeButton.addEventListener('click', () => {
                panel.classList.remove('is-open');
            });

            sendButton.addEventListener('click', sendMessage);
            input.addEventListener('input', autoResizeInput);
            input.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && !event.shiftKey) {
                    event.preventDefault();
                    sendMessage();
                }
            });

            autoResizeInput();
            bootstrap();
        })();
    </script>
</div>
