package com.pcstatus.monitor

import android.app.Activity
import android.content.Intent
import android.content.SharedPreferences
import android.os.Bundle
import android.view.ContextMenu
import android.view.MenuItem
import android.view.View
import android.webkit.*
import androidx.appcompat.app.AppCompatActivity

class MainActivity : AppCompatActivity() {

    private lateinit var webView: WebView
    private lateinit var prefs: SharedPreferences

    companion object {
        const val PREFS_NAME   = "pcstatus"
        const val KEY_URL      = "server_url"
        const val REQ_SETTINGS = 1
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)
        window.addFlags(android.view.WindowManager.LayoutParams.FLAG_KEEP_SCREEN_ON)

        prefs   = getSharedPreferences(PREFS_NAME, MODE_PRIVATE)
        webView = findViewById(R.id.webView)

        setupWebView()
        registerForContextMenu(webView)
        loadConfiguredUrl()
    }

    private fun setupWebView() {
        webView.settings.apply {
            javaScriptEnabled    = true
            domStorageEnabled    = true
            useWideViewPort      = true
            loadWithOverviewMode = true
            setSupportZoom(true)
            builtInZoomControls  = true
            displayZoomControls  = false
            mixedContentMode     = WebSettings.MIXED_CONTENT_COMPATIBILITY_MODE
        }

        webView.webViewClient = object : WebViewClient() {
            override fun shouldOverrideUrlLoading(
                view: WebView, request: WebResourceRequest
            ) = false

            override fun onReceivedError(
                view: WebView, request: WebResourceRequest, error: WebResourceError
            ) {
                if (request.isForMainFrame) loadErrorPage()
            }
        }

        webView.webChromeClient = WebChromeClient()
    }

    private fun loadConfiguredUrl() {
        val url = prefs.getString(KEY_URL, "").orEmpty().trim()
        if (url.isBlank()) {
            openSettings()
        } else {
            webView.loadUrl(url)
        }
    }

    private fun loadErrorPage() {
        val url = prefs.getString(KEY_URL, "?").orEmpty()
        webView.loadData("""
            <html><head>
            <meta name="viewport" content="width=device-width,initial-scale=1">
            <style>
              body{margin:0;background:#0d1117;color:#c9d1d9;font-family:monospace;
                display:flex;flex-direction:column;align-items:center;justify-content:center;
                min-height:100vh;padding:24px;box-sizing:border-box;text-align:center}
              .icon{font-size:3em;margin-bottom:16px}
              h2{color:#58a6ff;font-size:1.1em;margin-bottom:8px}
              p{color:#8b949e;font-size:.82em;margin-bottom:6px;word-break:break-all}
              button{margin-top:20px;background:#238636;color:#fff;border:none;
                border-radius:6px;padding:12px 28px;font-family:monospace;
                font-size:.9em;cursor:pointer}
            </style></head>
            <body>
              <div class="icon">&#128225;</div>
              <h2>Servidor inaccessivel</h2>
              <p>$url</p>
              <p>Verifique a URL e se o servidor esta acessivel na rede.</p>
              <button onclick="window.location.href='$url'">Tentar novamente</button>
            </body></html>
        """.trimIndent(), "text/html", "UTF-8")
    }

    // ── Menu de contexto (pressao longa) ──────────────────────────────────────

    override fun onCreateContextMenu(
        menu: ContextMenu, v: View, menuInfo: ContextMenu.ContextMenuInfo?
    ) {
        val url = prefs.getString(KEY_URL, "").orEmpty()
        menu.setHeaderTitle(if (url.isNotBlank()) url else "PC Status")
        menu.add(0, 1, 0, "Recarregar")
        menu.add(0, 2, 1, "Configurar URL do servidor")
    }

    override fun onContextItemSelected(item: MenuItem): Boolean {
        return when (item.itemId) {
            1 -> { webView.reload(); true }
            2 -> { openSettings(); true }
            else -> super.onContextItemSelected(item)
        }
    }

    // ── Settings ──────────────────────────────────────────────────────────────

    private fun openSettings() {
        startActivityForResult(Intent(this, SettingsActivity::class.java), REQ_SETTINGS)
    }

    @Suppress("OVERRIDE_DEPRECATION")
    override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
        super.onActivityResult(requestCode, resultCode, data)
        if (requestCode == REQ_SETTINGS) {
            if (resultCode == Activity.RESULT_OK) {
                loadConfiguredUrl()
            } else {
                // Se cancelou e ainda nao tem URL configurada, fecha o app
                val url = prefs.getString(KEY_URL, "").orEmpty()
                if (url.isBlank()) finish()
            }
        }
    }

    // ── Navegacao para tras ───────────────────────────────────────────────────

    @Suppress("OVERRIDE_DEPRECATION")
    override fun onBackPressed() {
        if (webView.canGoBack()) webView.goBack()
        else super.onBackPressed()
    }
}
