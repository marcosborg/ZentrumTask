# Android Push Notifications

Este fluxo envia uma notificacao push Android quando entra um novo contacto e abre a app diretamente na task correta ao tocar na notificacao.

## O que ja existe

- Registo de dispositivos Android autenticados na tabela `app_device_tokens`
- API para registar e remover tokens do dispositivo
- Envio de push FCM ao criar contactos via:
  - formulario publico
  - area reservada
- Payload com `task_id` e `route=/reserved/tasks?task={id}`
- A app abre automaticamente a task no modal ao tocar na notificacao

## Backend

Configurar no `.env`:

```env
FCM_SERVICE_ACCOUNT=private/firebase-service-account.json
FCM_PROJECT_ID=
FCM_ANDROID_CHANNEL_ID=new-contacts
```

Notas:

- `FCM_SERVICE_ACCOUNT` e o caminho relativo ao disco `local` do Laravel
- `FCM_PROJECT_ID` pode ficar vazio se existir no ficheiro JSON, mas e seguro defini-lo tambem no `.env`
- `FCM_ANDROID_CHANNEL_ID` deve coincidir com o canal criado na app (`new-contacts` por defeito)

## App Android

O projeto Android vive em `/Users/marcosborges/Desktop/GitHub/Zentrumtvde-App/android`.

E necessario colocar o ficheiro:

```text
android/app/google-services.json
```

Esse ficheiro nao deve ser versionado.

## Credencial do backend

O backend usa a service account JSON do Firebase em:

```text
storage/app/private/firebase-service-account.json
```

Esse ficheiro nao deve ser versionado.

## Permissoes e comportamento

- A app pede permissao de notificacoes no Android
- O token push e registado automaticamente quando a sessao autenticada fica ativa
- Ao terminar sessao, o token e removido da API
- Ao tocar na notificacao, a app navega para:

```text
/reserved/tasks?task={id}
```

## Criacao da task

O envio de push acontece quando entra um novo contacto por:

- `WebsiteController::createContactLead()`
- `AppKanbanController::storeContact()`

Se a task tiver `assigned_to_id`, a notificacao tenta ser enviada primeiro para os dispositivos desse utilizador.
Se nao houver dispositivos desse responsavel, o sistema usa os dispositivos Android registados mais recentes.

## Validacao recomendada

1. Fazer login na app Android
2. Confirmar que a tabela `app_device_tokens` recebeu o token
3. Criar um novo contacto
4. Confirmar rececao da notificacao
5. Tocar na notificacao
6. Verificar abertura da task correta e acesso imediato ao botao de ligar
