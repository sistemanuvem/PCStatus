# PC Status Monitor

Sistema de monitoramento remoto de PCs em tempo real, composto por três partes: um **agente Windows** que coleta e envia métricas, um **servidor web PHP** que armazena e exibe os dados, e um **app Android** para acompanhar tudo pelo celular.

```
[ PC Windows ]                    [ Servidor XAMPP ]              [ Celular Android ]
PCStatusMonitor.exe  ──POST/5s──▶  api/receive.php                App (WebView)
                                       │                               │
                                   data/*.json                         │
                                       │                               │
                                   index.php  ◀────────────────────────┘
                                  (dashboard, atualiza a cada 2s)
```

---

## Componentes

### 1. Servidor web (XAMPP + PHP)

O servidor é o centro do sistema. Ele recebe os dados dos agentes Windows, armazena em JSON e serve o dashboard para navegadores e para o app Android.

**Arquivos principais:**

| Arquivo | Função |
|---|---|
| `index.php` | Dashboard principal com gráficos e gauges |
| `login.php` | Tela de login com autenticação por sessão |
| `api/receive.php` | Endpoint que recebe os dados dos agentes (POST) |
| `api/status.php` | Endpoint que devolve os dados para o dashboard (GET) |
| `auth.php` | Lógica de autenticação (sessão PHP) |
| `settings.php` | Tela para trocar senha e nome de usuário |
| `data/*.json` | Um arquivo JSON por PC monitorado |

**Autenticação:**
- Credenciais padrão: `admin` / `admin`
- Para trocar, acesse `settings.php` pelo menu do dashboard
- Ou crie manualmente `data/auth.json`:
  ```json
  { "username": "seu_usuario", "password": "sua_senha" }
  ```
- Suporta hash bcrypt: `{ "username": "...", "password_hash": "$2y$10$..." }`

**O que o dashboard mostra:**
- Abas para cada PC monitorado, com indicador online/offline
- Alerta pulsante quando um PC para de enviar dados por mais de 5 minutos
- Gauge de CPU com uso % e temperatura
- Gauge de RAM com uso % e total/usado em GB
- GPU com uso %, VRAM e temperatura
- Discos com espaço por partição e velocidade de leitura/escrita (MB/s)
- Lista dos 20 processos que mais consomem CPU e RAM

**Instalar:**

1. Coloque a pasta `PCStatus` em `C:\xampp\htdocs\`
2. Inicie o Apache no XAMPP Control Panel
3. Acesse: `http://localhost/PCStatus/`
4. Login padrão: `admin` / `admin`

---

### 2. App Windows (PCStatusMonitor.exe)

Agente leve que roda silenciosamente na bandeja do Windows e envia métricas do PC para a API a cada 5 segundos (configurável).

**Para usuários finais — usar o .exe:**

1. Clique duas vezes em `PCStatusMonitor.exe`
2. Um ícone aparece na bandeja (ao lado do relógio)
3. Pronto — o app já está enviando os dados

Clique com o botão direito no ícone para acessar o menu:

```
PC Status Monitor
─────────────────
Configurações         ← URL do servidor, API Key, nome do PC, intervalo
─────────────────
[✓] Iniciar com o Windows
─────────────────
Sair
```

A tela de configurações (acessível pelo menu) permite alterar sem precisar recompilar:

| Campo | Padrão |
|---|---|
| URL da API | `http://localhost/PCStatus/api/receive.php` |
| API Key | `pcstatus-key-changeme` |
| Nome do PC | Nome do computador (hostname) |
| Intervalo (s) | `5` |

As configurações são salvas em `config.json` na mesma pasta do `.exe`.

**Para o desenvolvedor — compilar o .exe:**

Requisitos: Python 3.8+ com "Add Python to PATH" marcado na instalação.

```
Abra a pasta client\ e clique duas vezes em build.bat
```

O script instala todas as dependências e compila automaticamente. O arquivo `dist\PCStatusMonitor.exe` é criado — distribua apenas ele.

**O que é monitorado:**

| Componente | Dados coletados |
|---|---|
| CPU | Nome, uso %, temperatura (via ACPI — funciona em laptops; N/A em alguns desktops) |
| RAM | Total, usado, disponível, % |
| GPU NVIDIA | Uso %, VRAM total/usado/%, temperatura (via driver NVIDIA) |
| GPU AMD | Uso %, temperatura (via driver Radeon) |
| GPU Intel/outros | Uso % via contadores do Windows |
| Discos | Espaço por partição, velocidade de leitura/escrita (MB/s) |
| Processos | Top 20 por CPU e RAM (nome, cpu %, mem %) |

**Dependências Python** (instaladas automaticamente pelo `build.bat`):
`psutil`, `requests`, `wmi`, `pywin32`, `pystray`, `pillow`, `nvidia-ml-py3`, `pyadl`, `pyinstaller`

---

### 3. App Android (PCStatus.apk)

App nativo Android que exibe o dashboard do servidor web em uma WebView de tela cheia, sem barra de endereços nem controles de navegador — parece um app dedicado.

**Instalar no celular:**

1. Copie `android\app\build\outputs\apk\debug\app-debug.apk` para o celular
2. Em **Configurações → Segurança**, ative "Fontes desconhecidas" (ou "Instalar apps desconhecidos")
3. Abra o arquivo APK no celular e instale
4. Na primeira abertura, o app pede a URL do servidor
5. Digite a URL completa, por exemplo: `http://192.168.1.10/PCStatus/`
6. O dashboard abre diretamente no app

**Funcionalidades do app:**

- Na primeira abertura, abre automaticamente a tela de configuração de URL
- Pressione e segure a tela para o menu de contexto:
  - **Recarregar** — recarrega o dashboard
  - **Configurar URL do servidor** — muda o endereço do servidor
- Botão voltar navega para a página anterior dentro do dashboard
- Página de erro amigável quando o servidor está inacessível, com botão "Tentar novamente"

**Compilar o APK (para desenvolvedor):**

Requisitos: Android Studio instalado (com SDK Android 34).

```powershell
# No PowerShell, dentro da pasta android\
$env:ANDROID_HOME = "C:\Users\<usuario>\AppData\Local\Android\Sdk"
.\gradlew.bat assembleDebug
```

O APK gerado fica em `android\app\build\outputs\apk\debug\app-debug.apk`.

> **Nota:** Se o build falhar por erro de SSL (Norton Antivirus ou proxy corporativo interceptando HTTPS), veja a seção de solução de problemas abaixo.

**Especificações técnicas:**

| Item | Valor |
|---|---|
| minSdk | Android 5.0 (API 21) |
| targetSdk | Android 14 (API 34) |
| Linguagem | Kotlin |
| Permissões | Apenas acesso à internet |

---

## Configuração de rede

Para acessar o servidor de outros dispositivos (celular, outros PCs), o servidor precisa ser acessível na rede local:

1. Descubra o IP do PC com o servidor: `ipconfig` → anote o IPv4 (ex: `192.168.1.10`)
2. No agente Windows (outros PCs), configure a URL: `http://192.168.1.10/PCStatus/api/receive.php`
3. No app Android, configure a URL: `http://192.168.1.10/PCStatus/`
4. Verifique que o Firewall do Windows permite o Apache na rede local

---

## Segurança

- A API key em `api/receive.php` e nos agentes deve ser a mesma — troque o valor padrão `pcstatus-key-changeme`
- O dashboard exige login (sessão PHP) — troque a senha padrão `admin` após instalar
- A pasta `data/` está protegida por `.htaccess` — acesso direto aos JSONs é bloqueado pelo Apache

---

## Solução de problemas

**Build do APK falha com erro de SSL (Norton/proxy)**

O Norton Antivirus intercepta conexões HTTPS, substituindo certificados. Execute no PowerShell:

```powershell
# Copiar cacerts para caminho sem espaços
Copy-Item "C:\Program Files\Android\Android Studio\jbr\lib\security\cacerts" "C:\cacerts"

# Importar certificado do Norton
$keytool = "C:\Program Files\Android\Android Studio\jbr\bin\keytool.exe"
$norton = Get-ChildItem Cert:\LocalMachine\Root | Where-Object { $_.Subject -like "*Norton*" }
$certPath = "C:\temp\norton.cer"
[System.IO.File]::WriteAllBytes($certPath, $norton.Export("Cert"))
& $keytool -import -noprompt -alias "norton-ssl" -file $certPath -keystore C:\cacerts -storepass changeit
```

Certifique-se que `android\gradle.properties` contém:
```properties
org.gradle.jvmargs=-Xmx2048m -Djavax.net.ssl.trustStoreType=JKS -Djavax.net.ssl.trustStore=C:/cacerts -Djavax.net.ssl.trustStorePassword=changeit
```

**App Android mostra "Servidor inacessível"**

- Confirme que o Apache está rodando no XAMPP
- Confirme que o celular e o servidor estão na mesma rede Wi-Fi
- Use o IP da rede local (ex: `192.168.1.10`), não `localhost`
- Verifique o Firewall do Windows

**Dashboard não mostra nenhum PC**

- Confirme que o `PCStatusMonitor.exe` está rodando (ícone na bandeja)
- Verifique a URL e API Key nas configurações do agente
- Teste a API diretamente: `http://localhost/PCStatus/api/status.php`
