# Flujo de Datos de las Vulnerabilidades

Proyecto de Ciberseguridad UCAB 2026 — rama `versión-vulnerable`.

Diagramas de flujo de datos (Mermaid) para las **dos vulnerabilidades
obligatorias**. Cada diagrama marca con **🔴 AQUI SE ROMPE LA SEGURIDAD** el punto
exacto del codigo donde falla la validacion o el escape.

Para el guion de explotacion ver [demo_ssrf.md](demo_ssrf.md) y
[demo_xss_api.md](demo_xss_api.md); para el mapeo de endpoints,
[INVENTARIO_ENDPOINTS.md](INVENTARIO_ENDPOINTS.md).

---

## 1. SSRF — `backend/crear_perfil.php` (API7:2023 / CWE-918)

El dato del atacante (`url_repositorio`) viaja desde el formulario hasta
`file_get_contents()` **sin ninguna validacion de destino ni de esquema**. Ese es
el punto de ruptura.

```mermaid
flowchart TD
    A["Atacante (Kali)<br/>POST url_repositorio=http://127.0.0.1:5000/repos"] --> B["router.php<br/>despacha /backend/crear_perfil.php"]
    B --> C["crear_perfil.php<br/>$_POST['url_repositorio'] (sin validar)"]
    C --> D{{"🔴 AQUI SE ROMPE LA SEGURIDAD<br/>file_get_contents($url_repositorio)<br/>crear_perfil.php — sin allowlist, acepta<br/>127.0.0.1, IPs internas y file://"}}
    D --> E["Destino interno/local<br/>127.0.0.1:5000, :22, file:///etc/passwd"]
    E --> F["preview_repositorio = substr(contenido, 0, 500)<br/>contenido interno SIN sanitizar"]
    F --> G["Respuesta JSON al atacante<br/>estado_conexion + preview_repositorio<br/>(oraculo de escaneo / exfiltracion)"]
    C --> H["file_put_contents(usuarios.txt)<br/>persistencia sin sanitizar"]

    style D fill:#ffdddd,stroke:#d00,stroke-width:3px
```

**Punto de ruptura (una sola causa raiz):** la variable `$url_repositorio`
proviene directamente de `$_POST` y se pasa integra a `file_get_contents()`. No hay
allowlist de dominios, no se bloquean rangos internos (RFC1918 / localhost /
link-local) ni se restringen esquemas (`file://`). Ademas, el contenido obtenido se
**devuelve** al cliente en `preview_repositorio`, lo que convierte al servidor en un
**oraculo** para escanear la red interna y exfiltrar archivos.

---

## 2. Consumo No Seguro de APIs → XSS (API10:2023 / CWE-20 → CWE-79)

Aqui hay **dos puntos de ruptura encadenados**: (a) la API acepta contenido
malicioso sin autenticacion/validacion, y (b) ese contenido llega hasta el
navegador porque ni el backend lo sanitiza ni el frontend lo escapa.

```mermaid
flowchart TD
    A["Atacante (Kali)<br/>POST /update_repos con descripcion maliciosa"] --> B{{"🔴 AQUI SE ROMPE LA SEGURIDAD (1)<br/>api_mock.py — /update_repos<br/>reemplaza REPOS sin auth ni validacion"}}
    B --> C["REPOS en memoria contiene<br/>&lt;img src=x onerror=alert(...)&gt;"]

    V["Victima (navegador)<br/>clic en 'Cargar Portafolio'"] --> W["GET /backend/ver_portafolio.php"]
    W --> X["ver_portafolio.php<br/>file_get_contents(localhost:5000/repos)<br/>+ json_decode() sin validar estructura"]
    C --> X
    X --> Y{{"🔴 AQUI SE ROMPE LA SEGURIDAD (2a)<br/>ver_portafolio.php reenvia el JSON<br/>de la API SIN sanitizar (confianza ciega)"}}
    Y --> Z["Frontend recibe el JSON"]
    Z --> ZZ{{"🔴 AQUI SE ROMPE LA SEGURIDAD (2b)<br/>script.js: listaRepos.innerHTML = repo.descripcion<br/>sin textContent ni DOMPurify"}}
    ZZ --> EXE["El navegador ejecuta el JavaScript<br/>en el contexto de la victima (XSS)"]

    style B fill:#ffdddd,stroke:#d00,stroke-width:3px
    style Y fill:#ffdddd,stroke:#d00,stroke-width:3px
    style ZZ fill:#ffdddd,stroke:#d00,stroke-width:3px
```

**Puntos de ruptura encadenados:**

1. **`api/api_mock.py` (`/update_repos`)** — acepta cualquier JSON y reemplaza la
   lista `REPOS` **sin autenticacion ni validacion de estructura**. Es donde entra
   el payload (CWE-20 / API10:2023).
2. **`backend/ver_portafolio.php`** — consulta la API y **reenvia el JSON tal cual**
   (`json_decode()` sin validar, `json_encode($payload)`): confianza ciega en un
   tercero (CWE-20 / API10:2023).
3. **`frontend/script.js`** — asigna `repo.descripcion` a `innerHTML` sin escapar,
   por lo que el navegador **ejecuta** el HTML/JS inyectado (CWE-79).

Ninguna capa por si sola completa el ataque; la cadena rota de confianza (API →
backend → frontend) es lo que lo hace explotable. Ver la "cadena de confianza rota"
detallada en [demo_xss_api.md](demo_xss_api.md) y el impacto en
[ANALISIS_IMPACTO.md](ANALISIS_IMPACTO.md).
