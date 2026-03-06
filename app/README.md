# AI Auto Reply - App Android

App React Native com módulo nativo Kotlin para resposta automática via WhatsApp.

## Estrutura

```
app/
├── App.tsx                          # Entry point
├── src/
│   ├── screens/                     # Telas do app
│   │   ├── LoginScreen.tsx
│   │   ├── HomeScreen.tsx
│   │   ├── ConversationsScreen.tsx
│   │   ├── LogsScreen.tsx
│   │   └── SettingsScreen.tsx
│   ├── navigation/
│   │   └── AppNavigator.tsx         # Stack + Tab navigation
│   ├── services/
│   │   ├── api.ts                   # Axios HTTP client
│   │   └── notification.ts          # Bridge para módulo nativo
│   ├── store/
│   │   ├── auth.ts                  # Zustand auth store
│   │   └── service.ts              # Estado do serviço
│   └── types/
│       └── index.ts
├── android/
│   └── app/src/main/
│       ├── AndroidManifest.xml
│       └── java/com/aireply/
│           ├── WhatsAppNotificationListener.kt  # Core: intercepta notificações
│           ├── KeepAliveService.kt               # Foreground service anti-kill
│           ├── NotificationBridge.kt             # Bridge RN ↔ Kotlin
│           └── NotificationBridgePackage.kt      # Registra módulo nativo
└── package.json
```

## Setup

```bash
cd app
npm install
npx react-native run-android
```

## Fluxo de Funcionamento

1. Usuário faz login (mesma conta do painel web)
2. Concede permissão de Notification Listener
3. Ativa o serviço via toggle na Home
4. `WhatsAppNotificationListener` intercepta notificações do WhatsApp
5. Envia mensagem para `POST /api/messages/process` no backend
6. Backend processa com IA e retorna resposta
7. App responde no WhatsApp via Reply Action da notificação
8. Logs aparecem em tempo real na aba Logs

## Módulo Nativo (Kotlin)

- **WhatsAppNotificationListener**: `NotificationListenerService` que filtra notificações do WhatsApp, extrai mensagem e contato, chama a API, e responde via `RemoteInput`
- **KeepAliveService**: `ForegroundService` com notificação persistente para manter o app vivo
- **NotificationBridge**: Bridge React Native ↔ Kotlin com métodos para controlar o serviço, verificar permissões, e receber eventos
