package com.pcstatus.monitor

import android.app.Activity
import android.content.SharedPreferences
import android.os.Bundle
import android.view.MenuItem
import android.widget.Button
import android.widget.EditText
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity

class SettingsActivity : AppCompatActivity() {

    private lateinit var prefs: SharedPreferences

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_settings)

        supportActionBar?.apply {
            setDisplayHomeAsUpEnabled(true)
            title = "Configurar servidor"
        }

        prefs = getSharedPreferences(MainActivity.PREFS_NAME, MODE_PRIVATE)

        val editUrl = findViewById<EditText>(R.id.editUrl)
        val btnSave = findViewById<Button>(R.id.btnSave)

        val saved = prefs.getString(MainActivity.KEY_URL, "").orEmpty()
        editUrl.setText(if (saved.isNotBlank()) saved else "http://")
        editUrl.setSelection(editUrl.text.length)

        btnSave.setOnClickListener { save(editUrl.text.toString().trim()) }
    }

    private fun save(raw: String) {
        if (raw.isBlank() || raw == "http://") {
            Toast.makeText(this, "Digite a URL do servidor", Toast.LENGTH_SHORT).show()
            return
        }
        var url = if (!raw.startsWith("http")) "http://$raw" else raw
        url = url.trimEnd('/')
        prefs.edit().putString(MainActivity.KEY_URL, url).apply()
        Toast.makeText(this, "URL salva!", Toast.LENGTH_SHORT).show()
        setResult(Activity.RESULT_OK)
        finish()
    }

    override fun onOptionsItemSelected(item: MenuItem): Boolean {
        if (item.itemId == android.R.id.home) {
            setResult(Activity.RESULT_CANCELED)
            finish()
            return true
        }
        return super.onOptionsItemSelected(item)
    }

    @Suppress("OVERRIDE_DEPRECATION")
    override fun onBackPressed() {
        setResult(Activity.RESULT_CANCELED)
        super.onBackPressed()
    }
}
