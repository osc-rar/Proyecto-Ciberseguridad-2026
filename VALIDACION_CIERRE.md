# Validación de Cierre de Vulnerabilidades

Proyecto de Ciberseguridad UCAB 2026.

Este documento cierra el ciclo de las **dos vulnerabilidades obligatorias** del
proyecto. Para cada una se contrasta el mismo payload y comando `curl` contra las
dos ramas del laboratorio:

- `versión-vulnerable`: el exploit **funciona**.
- `versión-asegurada`: el exploit queda **bloqueado o neutralizado**, citando la
  función real del código responsable de la mitigación.

Los payloads provienen de [`docs/payloads.txt`](https://github.com/osc-rar/Proyecto-Ciberseguridad-2026/blob/docs/documentacion-rama-vulnerable/docs/payloads.txt).
Los bloques `[CAPTURA: ...]` son marcadores que el equipo debe reemplazar con
evidencia real (capturas de pantalla o salidas de terminal) del laboratorio.

> **Entorno de referencia:** VM víctima `192.168.56.100` (app PHP en `:8080`,
> API mock en `:5000`), VM atacante Kali `192.168.56.101`. Ajustar las IPs a la
> red host-only real antes de ejecutar.

---

## Vulnerabilidad 1 — SSRF (API7:2023 / CWE-918)

| Atributo | Valor |
|---|---|
| OWASP API Security Top 10 | API7:2023 — Server Side Request Forgery |
| CWE | CWE-918 — Server-Side Request Forgery |
| Endpoint | `POST /backend/crear_perfil.php` |
| Campo vector | `url_repositorio` |

### Payload usado

De `docs/payloads.txt`, sección 1.2 (acceso a servicio interno vía localhost):

```
http://127.0.0.1:5000/repos
```

Variantes de la misma clase en `payloads.txt`: `http://127.0.0.1:22` (escaneo de
puertos, 1.4), `file:///etc/passwd` (lectura de archivos locales, 1.8) y
`http://169.254.169.254/latest/meta-data/` (metadatos cloud, 1.7).

### Comando curl

```bash
VICTIMA=192.168.56.100

curl -s -X POST http://$VICTIMA:8080/backend/crear_perfil.php \
  -F "nombre=Atacante" \
  -F "url_repositorio=http://127.0.0.1:5000/repos" \
  | python3 -m json.tool
```

### Resultado esperado en `versión-vulnerable` (exploit funciona)

El servidor pasa `url_repositorio` directamente a `file_get_contents()` sin
validar destino ni esquema, actúa como proxy hacia el servicio interno y
**devuelve el contenido** en `preview_repositorio`. Esto convierte al servidor en
un oráculo para escanear la red interna y exfiltrar archivos.

```json
{
    "status": "perfil_creado",
    "url_solicitada": "http://127.0.0.1:5000/repos",
    "estado_conexion": "ok",
    "estado_http_externo": "HTTP/1.0 200 OK",
    "preview_repositorio": "[{\"nombre\": \"proyecto-ejemplo\", \"descripcion\": \"...\", ..."
}
```

El campo `preview_repositorio` contiene la respuesta de un servicio que el
atacante no debería poder alcanzar desde fuera de la VM.

`[CAPTURA: salida de curl en versión-vulnerable mostrando preview_repositorio con el JSON del servicio interno 127.0.0.1:5000]`

### Resultado esperado en `versión-asegurada` (bloqueado)

La petición se rechaza **antes** de llegar a `file_get_contents()`. La respuesta
es un `HTTP 400` sin `preview_repositorio`:

```json
{
    "error": "La URL de repositorio no es válida. Solo se permiten enlaces públicos a github.com, gitlab.com o bitbucket.org."
}
```

**Función responsable:** `url_repositorio_es_segura()` en
`backend/crear_perfil.php`. Aplica varias defensas en capas:

- **Allowlist de esquemas:** solo `http`/`https`; bloquea `file://`, `gopher://`,
  `dict://`, etc. (neutraliza el payload 1.8 `file:///etc/passwd`).
- **Allowlist de dominios** (`DOMINIOS_REPOSITORIO_PERMITIDOS`: github.com,
  gitlab.com, bitbucket.org) más una allowlist acotada de endpoints internos
  (`ENDPOINTS_INTERNOS_PERMITIDOS`) para el flujo legítimo.
- **Bloqueo de IPs privadas/reservadas:** resuelve el host con `gethostbyname()`
  y valida con `filter_var(..., FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)`,
  rechazando loopback, RFC1918 y link-local (neutraliza 1.2, 1.4, 1.6, 1.7).

Como defensa adicional, el `stream_context_create()` fija `follow_location => 0`
para impedir que un host permitido redirija (3xx) hacia una IP interna, y el
`preview_repositorio` legítimo se codifica con `htmlspecialchars()`.

`[CAPTURA: salida de curl en versión-asegurada mostrando el HTTP 400 con el mensaje de URL no válida y sin preview_repositorio]`

### Conclusión — SSRF

En `versión-vulnerable` el campo `url_repositorio` llega íntegro a
`file_get_contents()`, permitiendo escaneo interno y exfiltración. En
`versión-asegurada`, `url_repositorio_es_segura()` valida esquema, dominio e IP
resuelta antes de cualquier petición saliente, por lo que el exploit queda
bloqueado en origen. Vulnerabilidad **mitigada**.

---

## Vulnerabilidad 2 — Consumo No Seguro de APIs → XSS (API10:2023 / CWE-20 → CWE-79)

| Atributo | Valor |
|---|---|
| OWASP API Security Top 10 | API10:2023 — Unsafe Consumption of APIs |
| CWE | CWE-20 (validación) → CWE-79 (Cross-Site Scripting) |
| Vector de inyección | `POST /update_repos` en `api/api_mock.py` (API de terceros comprometida) |
| Punto de renderizado | `GET /backend/ver_portafolio.php` → `frontend/script.js` |

### Payload usado

De `docs/payloads.txt`, sección 2.2 (event handler que sí se ejecuta):

```json
[{"nombre":"repo-img","descripcion":"<img src=x onerror=alert(document.domain)>","url":"https://evil.com","lenguaje":"HTML"}]
```

### Comando curl

```bash
VICTIMA=192.168.56.100

# Paso 1 — comprometer la API de terceros inyectando el payload
curl -X POST http://$VICTIMA:5000/update_repos \
  -H "Content-Type: application/json" \
  -d '[{"nombre":"repo-img","descripcion":"<img src=x onerror=alert(document.domain)>","url":"https://evil.com","lenguaje":"HTML"}]'

# Paso 2 — la víctima carga el portafolio (o verificar con curl)
curl -s http://$VICTIMA:8080/backend/ver_portafolio.php | python3 -m json.tool
```

En ambas ramas `api_mock.py` acepta el payload sin autenticación (simula una API
de terceros comprometida, es intencional). La diferencia está en cómo lo procesa
`ver_portafolio.php` y cómo llega al navegador de la víctima.

### Resultado esperado en `versión-vulnerable` (exploit funciona)

`ver_portafolio.php` reenvía el JSON de la API **sin sanitizar** (confianza
ciega) y `frontend/script.js` lo inserta con `innerHTML` sin escape. La
`descripcion` del portafolio conserva el HTML crudo:

```json
"descripcion": "<img src=x onerror=alert(document.domain)>"
```

Cuando la víctima pulsa **"Cargar Portafolio"**, el navegador construye el `<img>`,
falla la carga de `src=x`, dispara `onerror` y **ejecuta** `alert(document.domain)`
en el contexto de la víctima (XSS almacenado).

`[CAPTURA: navegador de la víctima en versión-vulnerable mostrando el alert(document.domain) disparado al cargar el portafolio]`

### Resultado esperado en `versión-asegurada` (neutralizado)

La API sigue comprometida, pero el payload **no se ejecuta**: se renderiza como
texto inerte. En la respuesta de `ver_portafolio.php` la `descripcion` viaja ya
codificada como entidades HTML:

```json
"descripcion": "&lt;img src=x onerror=alert(document.domain)&gt;"
```

**Función responsable:** `escapar_valores_recursivo()` en
`backend/ver_portafolio.php`, que recorre el payload y aplica
`htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')` a cada cadena
antes de responder. Los `<` y `>` se convierten en `&lt;`/`&gt;`, por lo que el
navegador muestra el texto `<img src=x onerror=...>` en lugar de crear un
elemento ejecutable.

Como validación previa, `repo_es_valido()` (mismo archivo) descarta entradas de
la API que no cumplan la estructura y esquema de URL esperados (CWE-20), y
`perfil_es_valido()` valida el perfil leído de disco.

> **Nota de defensa en profundidad:** en `versión-asegurada`, `frontend/script.js`
> todavía usa `innerHTML` (no se corrigió en la capa cliente). La neutralización
> del XSS recae por completo en el escape del backend
> (`escapar_valores_recursivo`). Se recomienda, como mejora futura, escapar
> también en el frontend (`textContent`/DOMPurify) para no depender de una sola
> capa.

`[CAPTURA: respuesta de ver_portafolio.php en versión-asegurada mostrando la descripcion con &lt;img...&gt; y el navegador mostrando el payload como texto plano sin ejecutar]`

### Conclusión — Consumo No Seguro / XSS

En `versión-vulnerable` la cadena API → backend → frontend transporta el payload
sin controles y el navegador lo ejecuta. En `versión-asegurada`, aunque la API de
terceros siga comprometida, `repo_es_valido()` filtra la estructura y
`escapar_valores_recursivo()` codifica todo el contenido con `htmlspecialchars()`
antes de entregarlo, por lo que el XSS se muestra como texto y no se ejecuta.
Vulnerabilidad **neutralizada** (con recomendación de reforzar el frontend).

---

## Resumen

| Vulnerabilidad | OWASP / CWE | Estado en asegurada | Función responsable |
|---|---|---|---|
| SSRF | API7:2023 / CWE-918 | Bloqueado (HTTP 400) | `url_repositorio_es_segura()` — `backend/crear_perfil.php` |
| Consumo No Seguro → XSS | API10:2023 / CWE-20, CWE-79 | Neutralizado (escape HTML) | `escapar_valores_recursivo()` + `repo_es_valido()` — `backend/ver_portafolio.php` |
