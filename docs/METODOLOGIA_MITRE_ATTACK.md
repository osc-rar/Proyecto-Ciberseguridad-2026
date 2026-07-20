# Metodologia — Mapeo MITRE ATT&CK®

Proyecto de Ciberseguridad UCAB 2026 — rama `versión-vulnerable`.

Este documento mapea la cadena de ataque completa del laboratorio a tacticas y
tecnicas del framework **MITRE ATT&CK® (Enterprise)**. No repite el detalle de
comandos y respuestas: cada paso **referencia** los guiones ya existentes en
[demo_ssrf.md](demo_ssrf.md) y [demo_xss_api.md](demo_xss_api.md).

> MITRE ATT&CK® es una marca registrada de The MITRE Corporation. Los IDs de
> tecnica (`Txxxx`) corresponden a la matriz Enterprise.

## Cadena de ataque

```
Reconocimiento → Acceso inicial (SSRF) → Descubrimiento (escaneo interno / lectura de archivos)
   → Manipulacion/persistencia via compromiso de la API → Entrega al cliente → Ejecucion de JavaScript
```

## Mapeo paso a paso

| Táctica | Técnica (ID) | Accion concreta en este proyecto | Endpoint / archivo |
|---|---|---|---|
| Reconocimiento (TA0043) | Gather Victim Network Information (**T1590**) | Comprobar visibilidad de la victima: `ping` y `nmap -p 8080,5000` desde Kali antes de atacar | Red host-only — ver [PRUEBA_CONECTIVIDAD.md](PRUEBA_CONECTIVIDAD.md) |
| Reconocimiento (TA0043) | Active Scanning: Vulnerability Scanning (**T1595.002**) | Identificar el formulario y el campo `url_repositorio` como entrada controlable; probar peticion legitima (baseline) | `POST /backend/crear_perfil.php` — [demo_ssrf.md](demo_ssrf.md) Paso 1 |
| Acceso inicial (TA0001) | Exploit Public-Facing Application (**T1190**) | Abusar del SSRF: enviar `url_repositorio=http://127.0.0.1:5000/repos` para que el servidor haga peticiones en nombre del atacante | `crear_perfil.php` (`file_get_contents` sin validar) — [demo_ssrf.md](demo_ssrf.md) Paso 2 |
| Descubrimiento (TA0007) | Network Service Discovery (**T1046**) | Escaneo de puertos internos via el oraculo SSRF: comparar `estado_conexion` para 22, 5000, 6379, 9999 y mapear servicios internos | `crear_perfil.php` — [demo_ssrf.md](demo_ssrf.md) Paso 3; payloads 1.3–1.6 en [payloads.txt](payloads.txt) |
| Descubrimiento (TA0007) | File and Directory Discovery (**T1083**) | Usar el esquema `file://` para enumerar/leer rutas del servidor victima | `crear_perfil.php` — [demo_ssrf.md](demo_ssrf.md) Paso 4 |
| Recoleccion (TA0009) | Data from Local System (**T1005**) | Exfiltrar `file:///etc/passwd` y `data/usuarios.txt` a traves del `preview_repositorio` | `crear_perfil.php` — [demo_ssrf.md](demo_ssrf.md) Paso 4 |
| Impacto / Persistencia (TA0040 / TA0003) | Stored Data Manipulation (**T1565.001**) | Comprometer la API de terceros: `POST /update_repos` reemplaza la lista `REPOS` con contenido malicioso que **persiste** (hasta reiniciar la API) | `api/api_mock.py` — [demo_xss_api.md](demo_xss_api.md) Paso 2; payloads 2.2–2.4 en [payloads.txt](payloads.txt) |
| Acceso inicial al cliente (TA0001) | Drive-by Compromise (**T1189**) | La victima carga el portafolio y el servidor le entrega el contenido malicioso de la API sin sanitizar (backend confia ciegamente) | `ver_portafolio.php` → `frontend/script.js` — [demo_xss_api.md](demo_xss_api.md) Paso 3 |
| Ejecucion (TA0002) | Command and Scripting Interpreter: JavaScript (**T1059.007**) | El payload (`<img src=x onerror=...>`) se inserta con `innerHTML` y el navegador ejecuta el JavaScript en el contexto de la victima | `frontend/script.js` — [demo_xss_api.md](demo_xss_api.md) Paso 4 |
| Acceso a credenciales (TA0006) | Steal Web Session Cookie (**T1539**) | Variante avanzada: el JS inyectado envia `document.cookie` a un listener del atacante (`fetch` a Kali) | `frontend/script.js` — [demo_xss_api.md](demo_xss_api.md) Paso 5b; payload 2.3 |

## Correspondencia con OWASP / CWE

El mapeo MITRE describe **como** se ejecuta la cadena; el mapeo OWASP/CWE describe
**que fallo de diseño** la habilita. Ambos se complementan:

| Fase MITRE | Vulnerabilidad subyacente | OWASP | CWE |
|---|---|---|---|
| T1190 / T1046 / T1083 / T1005 | SSRF en `crear_perfil.php` | API7:2023 | CWE-918 |
| T1565.001 | API sin autenticacion ni validacion (`/update_repos`) | API10:2023 | CWE-20 |
| T1189 | Backend confia ciegamente en la API (`ver_portafolio.php`) | API10:2023 | CWE-20 |
| T1059.007 | Renderizado con `innerHTML` sin escape | — | CWE-79 |

Ver [INVENTARIO_ENDPOINTS.md](INVENTARIO_ENDPOINTS.md) para el detalle por endpoint
y [ANALISIS_IMPACTO.md](ANALISIS_IMPACTO.md) para el impacto tecnico de cada fase.
