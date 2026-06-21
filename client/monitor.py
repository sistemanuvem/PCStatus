"""
PC Status Monitor - System Tray
Roda silenciosamente na bandeja do Windows e envia metricas para a API PHP.
Compilar em .exe: execute build.bat
"""

import json
import os
import socket
import sys
import time
import winreg
import threading
from datetime import datetime
from typing import Any, Dict, List, Optional

import psutil
import requests
import pystray
from PIL import Image, ImageDraw

# ── Modulos opcionais — o app funciona sem eles ───────────────────────────────

try:
    import wmi as _wmi_mod
    _wmi_obj = _wmi_mod.WMI()
    WMI_OK = True
except Exception:
    WMI_OK = False

try:
    import pynvml
    pynvml.nvmlInit()
    NV_OK = True
except Exception:
    NV_OK = False

try:
    from pyadl import ADLManager as _ADL
    _ADL.getInstance().getDevices()
    AMD_OK = True
except Exception:
    AMD_OK = False

# ── Config (config.json ao lado do .exe) ─────────────────────────────────────

_DEFAULT_URL = "http://localhost/PCStatus/api/receive.php"
_DEFAULT_KEY = "pcstatus-key-changeme"
_DEFAULT_INT = 5
_DEFAULT_PC  = socket.gethostname()


def _cfg_path() -> str:
    base = os.path.dirname(
        sys.executable if getattr(sys, "frozen", False)
        else os.path.abspath(__file__)
    )
    return os.path.join(base, "config.json")


def _load_cfg() -> dict:
    try:
        with open(_cfg_path(), "r", encoding="utf-8") as f:
            return json.load(f)
    except Exception:
        return {}


def _save_cfg(cfg: dict):
    with open(_cfg_path(), "w", encoding="utf-8") as f:
        json.dump(cfg, f, indent=2, ensure_ascii=False)

# ── Registro Windows — iniciar com o Windows ─────────────────────────────────

_APP_NAME = "PCStatusMonitor"
_REG_PATH = r"SOFTWARE\Microsoft\Windows\CurrentVersion\Run"


def _exe_path() -> str:
    if getattr(sys, "frozen", False):
        return f'"{sys.executable}"'
    return f'"{sys.executable}" "{os.path.abspath(__file__)}"'


def startup_enabled() -> bool:
    try:
        k = winreg.OpenKey(winreg.HKEY_CURRENT_USER, _REG_PATH, 0, winreg.KEY_READ)
        winreg.QueryValueEx(k, _APP_NAME)
        winreg.CloseKey(k)
        return True
    except OSError:
        return False


def startup_enable():
    k = winreg.OpenKey(winreg.HKEY_CURRENT_USER, _REG_PATH, 0, winreg.KEY_SET_VALUE)
    winreg.SetValueEx(k, _APP_NAME, 0, winreg.REG_SZ, _exe_path())
    winreg.CloseKey(k)


def startup_disable():
    try:
        k = winreg.OpenKey(winreg.HKEY_CURRENT_USER, _REG_PATH, 0, winreg.KEY_SET_VALUE)
        winreg.DeleteValue(k, _APP_NAME)
        winreg.CloseKey(k)
    except OSError:
        pass

# ── Coleta de dados ───────────────────────────────────────────────────────────

def _gb(b: int) -> float:
    return round(b / (1024 ** 3), 2)


def _collect_cpu() -> Dict[str, Any]:
    name = "CPU"
    if WMI_OK:
        try:
            for c in _wmi_obj.Win32_Processor():
                name = c.Name.strip()
                break
        except Exception:
            pass

    temp = None
    if WMI_OK:
        try:
            w = _wmi_mod.WMI(namespace=r"root\wmi")
            vals = []
            for z in w.MSAcpi_ThermalZoneTemperature():
                raw = getattr(z, "CurrentTemperature", 0) or 0
                if raw > 0:
                    c = round(raw / 10.0 - 273.15, 1)
                    if 0 < c < 120:
                        vals.append(c)
            if vals:
                temp = max(vals)
        except Exception:
            pass

    return {
        "name":        name,
        "usage":       round(psutil.cpu_percent(interval=0.3), 1),
        "temperature": temp,
    }


def _collect_memory() -> Dict[str, Any]:
    m = psutil.virtual_memory()
    return {
        "total_gb":     _gb(m.total),
        "used_gb":      _gb(m.used),
        "available_gb": _gb(m.available),
        "percent":      round(m.percent, 1),
    }


def _gpu_usage_perf() -> Optional[float]:
    if not WMI_OK:
        return None
    try:
        items = _wmi_mod.WMI().Win32_PerfFormattedData_GPUPerformanceCounters_GPUEngine()
        total = sum(
            float(getattr(e, "UtilizationPercentage", 0) or 0)
            for e in items
            if "engtype_3D" in (getattr(e, "Name", "") or "")
        )
        return round(min(100.0, total), 1) if items else None
    except Exception:
        return None


def _vram_used_perf() -> Optional[float]:
    if not WMI_OK:
        return None
    try:
        items = _wmi_mod.WMI().Win32_PerfFormattedData_GPUPerformanceCounters_GPUAdapterMemory()
        total = sum(int(getattr(e, "DedicatedUsage", 0) or 0) for e in items)
        return _gb(total) if total > 0 else None
    except Exception:
        return None


def _collect_gpu() -> List[Dict[str, Any]]:
    gpus: List[Dict[str, Any]] = []

    if NV_OK:
        try:
            for i in range(pynvml.nvmlDeviceGetCount()):
                h   = pynvml.nvmlDeviceGetHandleByIndex(i)
                nm  = pynvml.nvmlDeviceGetName(h)
                nm  = nm.decode() if isinstance(nm, bytes) else nm
                mem = pynvml.nvmlDeviceGetMemoryInfo(h)
                ut  = pynvml.nvmlDeviceGetUtilizationRates(h)
                tmp = pynvml.nvmlDeviceGetTemperature(h, pynvml.NVML_TEMPERATURE_GPU)
                gpus.append({
                    "name":          nm,
                    "usage":         ut.gpu,
                    "vram_total_gb": _gb(mem.total),
                    "vram_used_gb":  _gb(mem.used),
                    "vram_percent":  round(mem.used / mem.total * 100, 1) if mem.total else 0,
                    "temperature":   tmp,
                })
        except Exception:
            pass

    if AMD_OK:
        try:
            known = {g["name"] for g in gpus}
            for dev in _ADL.getInstance().getDevices():
                nm = getattr(dev, "adapterName", b"AMD GPU")
                nm = nm.decode("utf-8", errors="replace").strip() if isinstance(nm, bytes) else str(nm)
                if nm in known:
                    continue
                temp = usage = None
                try: temp  = float(dev.getCurrentTemperature())
                except Exception: pass
                try: usage = float(dev.getCurrentUsage())
                except Exception: pass
                vt = None
                if WMI_OK:
                    try:
                        for vc in _wmi_obj.Win32_VideoController():
                            if nm.lower() in (vc.Name or "").lower():
                                ram = getattr(vc, "AdapterRAM", None)
                                if ram: vt = _gb(int(ram))
                                break
                    except Exception: pass
                vu = _vram_used_perf()
                vp = round(vu / vt * 100, 1) if vu and vt else None
                gpus.append({
                    "name": nm, "usage": usage,
                    "vram_total_gb": vt, "vram_used_gb": vu,
                    "vram_percent": vp, "temperature": temp,
                })
        except Exception:
            pass

    if WMI_OK:
        try:
            known  = {g["name"] for g in gpus}
            usage  = _gpu_usage_perf()
            vram_u = _vram_used_perf()
            for vc in _wmi_obj.Win32_VideoController():
                nm = (vc.Name or "GPU").strip()
                if nm in known:
                    continue
                ram = getattr(vc, "AdapterRAM", None)
                vt  = _gb(int(ram)) if ram else None
                vp  = round(vram_u / vt * 100, 1) if vram_u and vt else None
                gpus.append({
                    "name": nm, "usage": usage,
                    "vram_total_gb": vt, "vram_used_gb": vram_u,
                    "vram_percent": vp, "temperature": None,
                })
        except Exception:
            pass

    return gpus


_prev_io: Dict[str, Any] = {}
_prev_io_t: float = 0.0


def _collect_disks() -> List[Dict[str, Any]]:
    global _prev_io, _prev_io_t

    curr = psutil.disk_io_counters(perdisk=True) or {}
    now  = time.monotonic()
    dt   = now - _prev_io_t if _prev_io_t else 0.0

    read_mbs = write_mbs = None
    if dt > 0 and curr and _prev_io:
        rb = sum(curr[k].read_bytes  - _prev_io.get(k, curr[k]).read_bytes  for k in curr) / dt
        wb = sum(curr[k].write_bytes - _prev_io.get(k, curr[k]).write_bytes for k in curr) / dt
        read_mbs  = round(max(0, rb) / (1024 ** 2), 2)
        write_mbs = round(max(0, wb) / (1024 ** 2), 2)

    _prev_io   = curr
    _prev_io_t = now

    disks = []
    for p in psutil.disk_partitions():
        if "cdrom" in p.opts or not p.fstype:
            continue
        try:
            u = psutil.disk_usage(p.mountpoint)
        except (PermissionError, OSError):
            continue
        disks.append({
            "device":     p.device,
            "mountpoint": p.mountpoint,
            "fstype":     p.fstype,
            "total_gb":   _gb(u.total),
            "used_gb":    _gb(u.used),
            "free_gb":    _gb(u.free),
            "percent":    round(u.percent, 1),
            "read_mb_s":  read_mbs,
            "write_mb_s": write_mbs,
        })
    return disks


# Processos do sistema sem utilidade para monitoramento
_PROC_IGNORE = {'System Idle Process', 'Idle', 'Registry', 'Memory Compression'}

# Cache de objetos de processo para cpu_percent() preciso entre ciclos
_proc_objs: Dict[int, Any] = {}


def _collect_processes(top_n: int = 20) -> List[Dict[str, Any]]:
    global _proc_objs
    results: List[Dict[str, Any]] = []
    new_objs: Dict[int, Any] = {}

    for p in psutil.process_iter(['pid', 'name', 'memory_percent']):
        try:
            pid  = p.pid
            name = p.info.get('name') or '?'
            if name in _PROC_IGNORE:
                continue
            proc = _proc_objs.get(pid, p)
            new_objs[pid] = proc
            cpu  = proc.cpu_percent()          # 0.0 na primeira chamada; preciso depois
            mem  = p.info.get('memory_percent') or 0.0
            if cpu > 0.1 or mem > 0.5:
                results.append({'name': name, 'cpu': round(cpu, 1), 'mem': round(mem, 1)})
        except (psutil.NoSuchProcess, psutil.AccessDenied, psutil.ZombieProcess):
            pass

    _proc_objs = new_objs
    results.sort(key=lambda x: (x['cpu'], x['mem']), reverse=True)
    return results[:top_n]


def collect_all() -> Dict[str, Any]:
    cfg     = _load_cfg()
    pc_name = cfg.get("pc_name", _DEFAULT_PC) or _DEFAULT_PC
    return {
        "timestamp": datetime.now().astimezone().isoformat(),
        "pc_name":   pc_name,
        "cpu":       _collect_cpu(),
        "memory":    _collect_memory(),
        "gpu":       _collect_gpu(),
        "disks":     _collect_disks(),
        "processes": _collect_processes(),
    }

# ── Icone do tray ─────────────────────────────────────────────────────────────

def _make_icon() -> Image.Image:
    sz  = 64
    img = Image.new("RGBA", (sz, sz), (0, 0, 0, 0))
    d   = ImageDraw.Draw(img)
    d.rounded_rectangle([3, 5, 61, 45], radius=5, fill="#161b22", outline="#58a6ff", width=3)
    for i, (h, cor) in enumerate([(22, "#3fb950"), (32, "#58a6ff"), (18, "#d29922"), (28, "#3fb950")]):
        x = 10 + i * 13
        d.rectangle([x, 43 - h, x + 9, 41], fill=cor)
    d.rectangle([26, 45, 38, 53], fill="#58a6ff")
    d.rectangle([18, 53, 46, 58], fill="#58a6ff")
    return img

# ── Dialogo de configuracoes ──────────────────────────────────────────────────

def _run_settings():
    import tkinter as tk

    cfg = _load_cfg()

    root = tk.Tk()
    root.title("PC Status Monitor - Configuracoes")
    root.configure(bg="#0d1117")
    root.resizable(False, False)
    root.attributes("-topmost", True)

    lbl_kw = dict(bg="#0d1117", fg="#8b949e", font=("Consolas", 9), anchor="w")
    ent_kw = dict(bg="#161b22", fg="white", font=("Consolas", 10),
                  insertbackground="white", relief="flat", bd=6, width=40)
    btn_kw = dict(font=("Consolas", 10), relief="flat", cursor="hand2", pady=5, padx=18)

    tk.Label(root, text="PC Status Monitor", bg="#0d1117",
             fg="#58a6ff", font=("Consolas", 12, "bold")).grid(
        row=0, column=0, columnspan=2, sticky="w", padx=14, pady=(14, 10))

    fields = [
        ("URL da API:",  "api_url", _DEFAULT_URL),
        ("API Key:",     "api_key", _DEFAULT_KEY),
        ("Nome do PC:", "pc_name", _DEFAULT_PC),
    ]
    vars_: Dict[str, tk.StringVar] = {}
    for i, (label, key, default) in enumerate(fields, start=1):
        tk.Label(root, text=label, **lbl_kw).grid(
            row=i, column=0, sticky="w", padx=14, pady=(0, 6))
        v = tk.StringVar(value=cfg.get(key, default))
        tk.Entry(root, textvariable=v, **ent_kw).grid(
            row=i, column=1, padx=(0, 14), pady=(0, 6))
        vars_[key] = v

    row_int = len(fields) + 1
    tk.Label(root, text="Intervalo (s):", **lbl_kw).grid(
        row=row_int, column=0, sticky="w", padx=14, pady=(0, 6))
    int_var = tk.StringVar(value=str(cfg.get("interval", _DEFAULT_INT)))
    tk.Entry(root, textvariable=int_var, width=6, bg="#161b22", fg="white",
             font=("Consolas", 10), insertbackground="white", relief="flat", bd=6
             ).grid(row=row_int, column=1, sticky="w", padx=(0, 14))

    def save():
        try: interval = max(1, int(int_var.get()))
        except ValueError: interval = _DEFAULT_INT
        _save_cfg({
            "api_url":  vars_["api_url"].get().strip() or _DEFAULT_URL,
            "api_key":  vars_["api_key"].get().strip() or _DEFAULT_KEY,
            "pc_name":  vars_["pc_name"].get().strip() or _DEFAULT_PC,
            "interval": interval,
        })
        root.destroy()

    frm = tk.Frame(root, bg="#0d1117")
    frm.grid(row=row_int + 1, column=0, columnspan=2, pady=(10, 14))
    tk.Button(frm, text="Salvar",   command=save,         bg="#238636", fg="white", **btn_kw).pack(side="left", padx=6)
    tk.Button(frm, text="Cancelar", command=root.destroy, bg="#21262d", fg="white", **btn_kw).pack(side="left", padx=6)

    root.mainloop()

# ── Callbacks do menu ─────────────────────────────────────────────────────────

def _toggle_startup(icon: pystray.Icon, item):
    if startup_enabled():
        startup_disable()
    else:
        startup_enable()
    icon.update_menu()


def _open_settings(icon: pystray.Icon, item):
    import subprocess
    subprocess.Popen([sys.executable, "--settings"])


def _quit(icon: pystray.Icon, item):
    icon.stop()
    sys.exit(0)

# ── Worker em background ──────────────────────────────────────────────────────

def _worker(icon: pystray.Icon):
    while True:
        cfg      = _load_cfg()
        api_url  = cfg.get("api_url",  _DEFAULT_URL)
        api_key  = cfg.get("api_key",  _DEFAULT_KEY)
        interval = cfg.get("interval", _DEFAULT_INT)

        try:
            data = collect_all()
            r    = requests.post(
                api_url, json=data,
                headers={"X-API-Key": api_key},
                timeout=5,
            )
            ts = datetime.now().strftime("%H:%M:%S")
            icon.title = (
                f"PC Monitor  OK  {ts}"
                if r.status_code == 200
                else f"PC Monitor  ERRO  HTTP {r.status_code}"
            )
        except requests.ConnectionError:
            icon.title = "PC Monitor  ERRO  API inacessivel"
        except Exception as e:
            icon.title = f"PC Monitor  ERRO  {str(e)[:60]}"

        time.sleep(max(1, interval))

# ── Ponto de entrada ──────────────────────────────────────────────────────────

def main():
    if len(sys.argv) > 1 and sys.argv[1] == "--settings":
        _run_settings()
        return

    icon = pystray.Icon(
        _APP_NAME,
        icon=_make_icon(),
        title="PC Status Monitor",
        menu=pystray.Menu(
            pystray.MenuItem("PC Status Monitor", None, enabled=False),
            pystray.Menu.SEPARATOR,
            pystray.MenuItem("Configuracoes", _open_settings),
            pystray.Menu.SEPARATOR,
            pystray.MenuItem(
                "Iniciar com o Windows",
                _toggle_startup,
                checked=lambda _: startup_enabled(),
            ),
            pystray.Menu.SEPARATOR,
            pystray.MenuItem("Sair", _quit),
        ),
    )
    threading.Thread(target=_worker, args=(icon,), daemon=True).start()
    icon.run()


if __name__ == "__main__":
    main()
