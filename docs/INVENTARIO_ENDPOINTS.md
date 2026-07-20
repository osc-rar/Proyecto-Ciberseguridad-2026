# Inventario de Endpoints

Proyecto de Ciberseguridad UCAB 2026 — rama `versión-vulnerable`.

Tabla unica y completa de **todas** las rutas de la aplicacion, basada en el
codigo real: [router.php](../router.php), [backend/crear_perfil.php](../backend/crear_perfil.php),
[backend/ver_portafolio.php](../backend/ver_portafolio.php) y
[api/api_mock.py](../api/api_mock.py).

- **Autenticacion:** ningun endpoint de la aplicacion implementa autenticacion
  (no hay login, sesiones ni tokens). La columna "Auth" lo refleja como **No**
  en todos los casos; se incluye porque es un hallazgo relevante para la rubrica.
- La columna "Riesgo / Vulnerabilidad" indica el mapeo OWASP/CWE cuando aplica, o
  "Ninguno" si la ruta no introduce una vulnerabilidad por si misma.

## Servidor de aplicacion PHP — `router.php` (puerto 8080)

| Metodo | Ruta | Entrada | Auth | Riesgo / Vulnerabilidad | Detalle |
|---|---|---|---|---|---|
| GET | `/` | — | No | Ninguno | Sirve `frontend/index.html` |
| GET | `/<archivo>.{css,js,png,jpg,ico,svg,json}` | Ruta del archivo | No | Ninguno | Sirve estaticos desde `frontend/`; solo extensiones de la allowlist |
| POST | `/backend/crear_perfil.php` | Campos `nombre`, `apellido`, `bio`, `url_repositorio` (form/POST) | No | **SSRF** — OWASP **API7:2023** / **CWE-918** | El campo `url_repositorio` llega sin validar a `file_get_contents()`. Ver [demo_ssrf.md](demo_ssrf.md) |
| OPTIONS | `/backend/crear_perfil.php` | — | No | Ninguno | Preflight CORS; responde 200 |
| GET | `/backend/ver_portafolio.php` | — (lee `usuarios.txt` + API mock) | No | **Consumo No Seguro de APIs** — OWASP **API10:2023** / **CWE-20**; propaga **CWE-79** (XSS) | Reenvia sin sanitizar el JSON de la API mock; el frontend lo inserta con `innerHTML`. Ver [demo_xss_api.md](demo_xss_api.md) |
| OPTIONS | `/backend/ver_portafolio.php` | — | No | Ninguno | Preflight CORS; responde 200 |
| GET/POST/… | Cualquier otra ruta | — | No | Ninguno | `router.php` responde 404 con `{"error":"No encontrado"}` |

**Notas de comportamiento (segun el codigo):**

- `crear_perfil.php` responde **405** a cualquier metodo distinto de POST/OPTIONS;
  `ver_portafolio.php` responde **405** a cualquier metodo distinto de GET/OPTIONS.
- Ambos backends emiten cabeceras **CORS permisivas** (`Access-Control-Allow-Origin: *`),
  lo que permite que el navegador del atacante consuma los endpoints sin restriccion.
- `crear_perfil.php` tambien **persiste** `nombre`, `apellido`, `bio` y
  `url_repositorio` en `data/usuarios.txt` sin sanitizar (riesgo secundario de
  inyeccion de saltos de linea en el almacenamiento).

## API simulada de terceros — `api/api_mock.py` (puerto 5000)

| Metodo | Ruta | Entrada | Auth | Riesgo / Vulnerabilidad | Detalle |
|---|---|---|---|---|---|
| GET | `/repos` | — | No | Fuente de datos no confiables (habilita **CWE-79** aguas abajo) | Devuelve la lista `REPOS` en memoria; por si sola es inocua, pero su contenido puede haber sido alterado por `/update_repos` |
| GET | `/` | — | No | Ninguno | Mensaje informativo con la lista de endpoints |
| POST | `/update_repos` | Cuerpo JSON (lista de repos) | No | **API10:2023** / **CWE-20** — punto de compromiso de la API | Reemplaza `REPOS` con **cualquier** JSON recibido, sin autenticacion ni validacion de estructura. Vector para inyectar el payload XSS. Ver [demo_xss_api.md](demo_xss_api.md) Paso 2 |
| GET/POST/… | Cualquier otra ruta | — | No | Ninguno | Responde 404 con `{"error":"Endpoint no encontrado"}` |

**Notas:**

- `/update_repos` acepta la peticion **directamente desde la red** (la API escucha
  en `0.0.0.0:5000`), por lo que el atacante en Kali puede comprometerla sin pasar
  por el SSRF. El SSRF (`crear_perfil.php`) sirve ademas para **descubrir** que esta
  API interna existe.
- La API tambien emite `Access-Control-Allow-Origin: *`.

## Resumen del mapeo OWASP / CWE

| Endpoint | OWASP | CWE | Documento de demo |
|---|---|---|---|
| `POST /backend/crear_perfil.php` | API7:2023 | CWE-918 | [demo_ssrf.md](demo_ssrf.md) |
| `POST /update_repos` (`api_mock.py`) | API10:2023 | CWE-20 | [demo_xss_api.md](demo_xss_api.md) |
| `GET /backend/ver_portafolio.php` | API10:2023 | CWE-20 (propaga CWE-79) | [demo_xss_api.md](demo_xss_api.md) |
| `innerHTML` en `frontend/script.js` (consecuencia) | — | CWE-79 | [demo_xss_api.md](demo_xss_api.md) |

Ver tambien el diagrama de componentes en
[ARQUITECTURA_COMPONENTES.md](ARQUITECTURA_COMPONENTES.md) y el flujo de datos de
cada vulnerabilidad en [FLUJO_DATOS.md](FLUJO_DATOS.md).
